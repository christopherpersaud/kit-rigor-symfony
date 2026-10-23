<?php

declare(strict_types=1);

namespace KitRigor;

use Composer\InstalledVersions;

/**
 * Configuração do kit no projeto: a raiz (onde está o composer.json) e o bloco `extra.rigor` do
 * composer.json. Toda chave é opcional; o padrão é o layout de um projeto Symfony.
 *
 *     "extra": {
 *         "rigor": {
 *             "specs": "docs/modulos",
 *             "testes-php": ["tests"],
 *             "testes-js": ["assets"],
 *             "dourados": "tests/Dourado",
 *             "verificar": {"comando": "composer check:rapido", "caminhos": ["."], "ignorar": ["*.md"]},
 *             "cobertura": {"fonte": "src", "trava": "tests/cobertura.php", "agrupar": ["Domain"]}
 *         }
 *     }
 */
final readonly class Config
{
    /**
     * @param list<string> $testesPhp
     * @param list<string> $testesJs
     * @param list<string> $caminhosVerificados
     * @param list<string> $ignorados
     * @param list<string> $agrupar
     */
    public function __construct(
        public string $raiz,
        public string $specs = 'docs/modulos',
        public array $testesPhp = ['tests'],
        public array $testesJs = ['assets'],
        public string $dourados = 'tests/Dourado',
        public string $comandoVerificar = 'composer check:rapido',
        public array $caminhosVerificados = ['.'],
        public array $ignorados = ['*.md', '.agent-memory/*', '.agents/*', '.claude/skills/*'],
        public string $fonteCobertura = 'src',
        public string $travaCobertura = 'tests/cobertura.php',
        public array $agrupar = ['Domain'],
    ) {
    }

    /** A configuração do projeto em que o kit está instalado (o pacote raiz do Composer). */
    public static function doProjeto(): self
    {
        $raiz = realpath(InstalledVersions::getRootPackage()['install_path']);

        return self::daPasta(false === $raiz ? (string) getcwd() : $raiz);
    }

    public static function daPasta(string $raiz): self
    {
        $composer = json_decode((string) @file_get_contents($raiz.'/composer.json'), true);
        $extra = \is_array($composer) && \is_array($composer['extra'] ?? null) ? $composer['extra'] : [];
        $rigor = \is_array($extra['rigor'] ?? null) ? $extra['rigor'] : [];
        $verificar = \is_array($rigor['verificar'] ?? null) ? $rigor['verificar'] : [];
        $cobertura = \is_array($rigor['cobertura'] ?? null) ? $rigor['cobertura'] : [];
        $padrao = new self($raiz);

        return new self(
            raiz: $raiz,
            specs: self::texto($rigor, 'specs') ?? $padrao->specs,
            testesPhp: self::lista($rigor, 'testes-php') ?? $padrao->testesPhp,
            testesJs: self::lista($rigor, 'testes-js') ?? $padrao->testesJs,
            dourados: self::texto($rigor, 'dourados') ?? $padrao->dourados,
            comandoVerificar: self::texto($verificar, 'comando') ?? $padrao->comandoVerificar,
            caminhosVerificados: self::lista($verificar, 'caminhos') ?? $padrao->caminhosVerificados,
            ignorados: self::lista($verificar, 'ignorar') ?? $padrao->ignorados,
            fonteCobertura: self::texto($cobertura, 'fonte') ?? $padrao->fonteCobertura,
            travaCobertura: self::texto($cobertura, 'trava') ?? $padrao->travaCobertura,
            agrupar: self::lista($cobertura, 'agrupar') ?? $padrao->agrupar,
        );
    }

    public function caminho(string $relativo): string
    {
        return $this->raiz.'/'.$relativo;
    }

    /** @param array<mixed> $dados */
    private static function texto(array $dados, string $chave): ?string
    {
        return \is_string($dados[$chave] ?? null) ? $dados[$chave] : null;
    }

    /**
     * @param array<mixed> $dados
     *
     * @return list<string>|null
     */
    private static function lista(array $dados, string $chave): ?array
    {
        if (!\is_array($dados[$chave] ?? null)) {
            return null;
        }

        return array_values(array_filter($dados[$chave], \is_string(...)));
    }
}
