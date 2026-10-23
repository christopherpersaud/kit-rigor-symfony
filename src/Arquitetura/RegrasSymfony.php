<?php

declare(strict_types=1);

namespace KitRigor\Arquitetura;

use PHPat\Selector\Selector;
use PHPat\Selector\SelectorInterface;
use PHPat\Test\Builder\Rule;
use PHPat\Test\PHPat;

/**
 * Regras de arquitetura de um projeto Symfony organizado em módulos de domínio
 * (`src/Domain/<Módulo>/{Service,UseCase,DTO,...}`), conferidas pelo phpat dentro do PHPStan. Cada
 * método `test*` devolve uma regra; uma violação vira erro do PHPStan na linha da dependência, com o
 * motivo do `because()`.
 *
 * O projeto estende esta classe em tests/Arquitetura/RegrasDeArquitetura.php, declara o mapa de
 * módulos e as exceções, e a registra no phpstan.dist.neon com a tag `phpat.test`. Uma regra que
 * não se aplica ao projeto é desligada sobrescrevendo o método e devolvendo `[]`.
 *
 * Exceções e mudanças no mapa de módulos são decisão do usuário.
 */
abstract class RegrasSymfony
{
    /** Classe base dos controllers do Symfony (como texto: o kit não depende do FrameworkBundle). */
    private const string ABSTRACT_CONTROLLER = 'Symfony\Bundle\FrameworkBundle\Controller\AbstractController';

    /**
     * Módulos de src/Domain e os módulos de que cada um pode depender, além do módulo comum (que
     * todos usam e que não depende de nenhum). Um módulo novo entra aqui na mesma mudança que o cria.
     *
     * @return array<string, list<string>>
     */
    abstract protected function modulos(): array;

    /**
     * Namespace raiz do código do projeto.
     *
     * @return non-empty-string
     */
    protected function raiz(): string
    {
        return 'App';
    }

    /** Módulo compartilhado: todos podem usá-lo, e ele não usa nenhum outro. */
    protected function moduloComum(): string
    {
        return 'Comum';
    }

    /** @return list<class-string> controllers que ainda leem repositories direto, aceitos até serem refeitos */
    protected function controllersComRepository(): array
    {
        return [];
    }

    /** @return Rule|list<Rule> */
    public function testSoControllerDependeDeController(): Rule|array
    {
        return PHPat::rule()
            ->classes(Selector::all())
            ->excluding(Selector::inNamespace($this->ns('Controller')))
            ->shouldNotDependOn()
            ->classes(Selector::inNamespace($this->ns('Controller')))
            ->because('a camada web fica na ponta: domínio, entidades e services não conhecem controllers.');
    }

    /**
     * Entidades podem usar os objetos de valor do domínio (DTO), não quem orquestra.
     *
     * @return Rule|list<Rule>
     */
    public function testEntidadeNaoDependeDeServiceNemDeUseCase(): Rule|array
    {
        return PHPat::rule()
            ->classes(Selector::inNamespace($this->ns('Entity')))
            ->shouldNotDependOn()
            ->classes(...$this->servicesEUseCases())
            ->because('a entidade guarda estado e invariantes; quem orquestra são os services e UseCases.');
    }

    /** @return Rule|list<Rule> */
    public function testRepositoryNaoDependeDeServiceNemDeUseCase(): Rule|array
    {
        return PHPat::rule()
            ->classes(Selector::inNamespace($this->ns('Repository')))
            ->shouldNotDependOn()
            ->classes(...$this->servicesEUseCases())
            ->because('o repository só consulta e grava; regra de negócio fica nos services e UseCases.');
    }

    /** @return Rule|list<Rule> */
    public function testUseCaseEhFinal(): Rule|array
    {
        return PHPat::rule()
            ->classes(Selector::inNamespace($this->camada('UseCase'), true))
            ->shouldBeFinal()
            ->because('um UseCase é um fluxo fechado; variação entra por composição, não por herança.');
    }

    /** @return Rule|list<Rule> */
    public function testControllerEstendeAbstractController(): Rule|array
    {
        return PHPat::rule()
            ->classes(Selector::inNamespace($this->ns('Controller')))
            ->shouldExtend()
            ->classes(Selector::classname(self::ABSTRACT_CONTROLLER))
            ->because('controllers estendem AbstractController (boas práticas do Symfony).');
    }

    /** @return Rule|list<Rule> */
    public function testControllerNaoUsaRepository(): Rule|array
    {
        return PHPat::rule()
            ->classes(Selector::inNamespace($this->ns('Controller')))
            ->excluding(...array_map(Selector::classname(...), $this->controllersComRepository()))
            ->shouldNotDependOn()
            ->classes(Selector::inNamespace($this->ns('Repository')))
            ->because('o controller fica fino e delega aos services e UseCases do módulo.');
    }

    /**
     * Uma regra por módulo: ele não depende dos módulos fora da sua lista em modulos().
     *
     * @return iterable<string, Rule>
     */
    public function testModuloSoDependeDosModulosPermitidos(): iterable
    {
        $modulos = $this->modulos();
        $comum = $this->moduloComum();
        foreach ($modulos as $modulo => $permitidos) {
            $proibidos = $comum === $modulo
                ? array_diff(array_keys($modulos), [$comum])
                : array_diff(array_keys($modulos), [$modulo, $comum], $permitidos);
            if ([] === $proibidos) {
                continue;
            }

            $liberados = [...$permitidos, ...($comum === $modulo || !isset($modulos[$comum]) ? [] : [$comum])];
            yield $modulo => PHPat::rule()
                ->classes(Selector::inNamespace($this->ns('Domain\\'.$modulo)))
                ->shouldNotDependOn()
                ->classes(...array_map(fn (string $outro) => Selector::inNamespace($this->ns('Domain\\'.$outro)), array_values($proibidos)))
                ->because(\sprintf('o mapa de módulos (modulos() em %s) deixa %s depender só de: %s.', static::class, $modulo, implode(', ', $liberados) ?: 'nenhum'));
        }
    }

    /** @return list<SelectorInterface> */
    private function servicesEUseCases(): array
    {
        return [
            Selector::inNamespace($this->camada('Service|UseCase'), true),
            Selector::inNamespace($this->ns('Service')),
        ];
    }

    /** @return non-empty-string */
    private function ns(string $sufixo): string
    {
        return $this->raiz().'\\'.$sufixo;
    }

    /**
     * Expressão regular das camadas de todos os módulos: App\Domain\<Módulo>\UseCase\...
     *
     * @return non-empty-string
     */
    private function camada(string $camadas): string
    {
        return \sprintf('/^%s\\\\Domain\\\\[^\\\\]+\\\\(%s)(\\\\|$)/', preg_quote($this->raiz(), '/'), $camadas);
    }
}
