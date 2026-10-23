<?php

declare(strict_types=1);

namespace KitRigor\Tests\Cobertura;

use KitRigor\Cobertura\Trava;
use PHPUnit\Framework\TestCase;

final class TravaTest extends TestCase
{
    private const string RAIZ = '/projeto';

    /** @var array{margem: int, total: int, padrao: int, pisos: array<string, int>, sem_teste: array<string, string>} */
    private const array TRAVA = [
        'margem' => 2,
        'total' => 80,
        'padrao' => 90,
        'pisos' => ['Domain/Cliente' => 70, 'Entity' => 50],
        'sem_teste' => [],
    ];

    public function testAgrupaOsModulosDoDomainEAsPastasDePrimeiroNivel(): void
    {
        $resultado = $this->conferir([
            'src/Domain/Cliente/Service/X.php' => [10, 8],
            'src/Domain/Cliente/Y.php' => [10, 8],
            'src/Entity/Z.php' => [10, 6],
            'src/Kernel.php' => [4, 4],
            'src/Vazio.php' => [0, 0],
        ]);

        self::assertSame([
            'Cobertura da fonte inteira: 76.5% (piso 80%)',
            '',
            'Cobertura de linhas por pasta (piso da trava):',
            '  .                      100.0%  piso  90%  (4/4)',
            '  Domain/Cliente          80.0%  piso  70%  (16/20)',
            '  Entity                  60.0%  piso  50%  (6/10)',
        ], $resultado->relatorio);
        self::assertSame(['a fonte caiu para 76.5%, abaixo do piso total de 80%.'], $resultado->erros);
        self::assertFalse($resultado->passou());
    }

    public function testPastaAbaixoDoPisoEPastaNovaNoPadraoReprovam(): void
    {
        $resultado = $this->conferir([
            'src/Domain/Cliente/X.php' => [10, 6],
            'src/Service/Nova.php' => [10, 8],
        ], ['total' => 0]);

        self::assertSame([
            'Domain/Cliente caiu para 60.0%, abaixo do piso de 70%.',
            'Service caiu para 80.0%, abaixo do piso de 90%.',
        ], $resultado->erros);
    }

    public function testPisoQuePodeSubirViraAviso(): void
    {
        $resultado = $this->conferir(['src/Entity/Z.php' => [100, 97]], ['total' => 0]);

        self::assertTrue($resultado->passou());
        self::assertSame(["o piso de 'Entity' pode subir de 50 para 95."], $resultado->avisos);
    }

    public function testNoLimiteDoPisoPassaSemAviso(): void
    {
        $resultado = $this->conferir(['src/Entity/Z.php' => [100, 52]], ['total' => 0]);

        self::assertTrue($resultado->passou());
        self::assertSame([], $resultado->avisos);
    }

    public function testArquivoComCodigoAZeroReprovaForaDaListaSemTeste(): void
    {
        $resultado = $this->conferir([
            'src/Entity/Z.php' => [10, 10],
            'src/Entity/SemTeste.php' => [3, 0],
            'src/Entity/Aceito.php' => [2, 0],
        ], ['total' => 0, 'pisos' => ['Entity' => 0], 'sem_teste' => ['src/Entity/Aceito.php' => 'motivo', 'src/Entity/Testado.php' => 'motivo']]);

        self::assertSame(['src/Entity/SemTeste.php não tem teste nenhum (0 de 3 linhas).'], $resultado->erros);
        self::assertContains('src/Entity/Testado.php já tem teste ou não existe mais: tire da lista sem_teste.', $resultado->avisos);
    }

    public function testCloverInvalidoReprova(): void
    {
        $resultado = new Trava(self::TRAVA, self::RAIZ)->conferir('não é xml');

        self::assertSame(['o relatório de cobertura não é um XML clover válido.'], $resultado->erros);
    }

    /**
     * @param array<string, array{int, int}> $arquivos caminho relativo à raiz => [linhas, cobertas]
     * @param array<string, mixed>           $trava
     */
    private function conferir(array $arquivos, array $trava = []): \KitRigor\Cobertura\Resultado
    {
        $xml = '<?xml version="1.0"?><coverage><project>';
        foreach ($arquivos as $caminho => [$linhas, $cobertas]) {
            $xml .= \sprintf('<file name="%s/%s"><metrics statements="%d" coveredstatements="%d"/></file>', self::RAIZ, $caminho, $linhas, $cobertas);
        }
        $xml .= '</project></coverage>';

        /** @var array{margem: int, total: int, padrao: int, pisos: array<string, int>, sem_teste: array<string, string>} $configurada */
        $configurada = array_replace(self::TRAVA, $trava);

        return new Trava($configurada, self::RAIZ)->conferir($xml);
    }
}
