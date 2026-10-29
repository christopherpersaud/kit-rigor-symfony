<?php

declare(strict_types=1);

namespace KitRigor\Tests\Hook;

use KitRigor\Config;
use KitRigor\Hook\Stop;
use KitRigor\Tests\Support\PastaTemporaria;
use KitRigor\Verificar\Verificador;
use PHPUnit\Framework\TestCase;

final class StopTest extends TestCase
{
    use PastaTemporaria {
        setUp as criarPasta;
        tearDown as apagarPasta;
    }

    private const string SESSAO = '{"session_id": "abc-123", "hook_event_name": "Stop", "stop_hook_active": false}';

    protected function setUp(): void
    {
        $this->criarPasta();
        $this->iniciarGit();
        $this->gravar('.gitignore', "/var/\n");
        $this->gravar('src/A.php', 'a');
        putenv('VERIFICAR_DESLIGADO');
    }

    protected function tearDown(): void
    {
        putenv('VERIFICAR_DESLIGADO');
        $this->apagarPasta();
    }

    public function testVerdeDeixaOTurnoTerminarEmSilencio(): void
    {
        self::assertNull($this->hook('true')->executar(self::SESSAO));
    }

    public function testFalhaBloqueiaComOMotivoNoFormatoDoClaudeEDoCodex(): void
    {
        $saida = $this->hook('echo "1 teste falhou"; exit 1')->executar(self::SESSAO);

        $json = json_decode((string) $saida, true);
        self::assertIsArray($json);
        self::assertSame('block', $json['decision']);
        self::assertIsString($json['reason']);
        self::assertStringStartsWith('A verificação (echo "1 teste falhou"; exit 1) falhou (tentativa 1 de 3).', $json['reason']);
        self::assertStringEndsWith("Fim da saída:\n\n1 teste falhou", $json['reason']);
        self::assertStringContainsString('não desligue a checagem nem a contorne', $json['reason']);
    }

    public function testDepoisDoLimiteDeixaTerminarComAvisoEZeraOContador(): void
    {
        $hook = $this->hook('exit 1');
        for ($tentativa = 1; $tentativa <= Stop::MAX_BLOQUEIOS; ++$tentativa) {
            self::assertStringContainsString(\sprintf('(tentativa %d de 3)', $tentativa), (string) $hook->executar(self::SESSAO));
        }

        $saida = json_decode((string) $hook->executar(self::SESSAO), true);

        self::assertSame(['systemMessage' => 'A verificação continua falhando depois de 3 tentativas de correção. O turno terminou com a verificação vermelha; veja var/check/ultimo.log.'], $saida);
        self::assertStringContainsString('(tentativa 1 de 3)', (string) $hook->executar(self::SESSAO));
    }

    public function testOContadorEhPorSessaoEZeraQuandoFicaVerde(): void
    {
        $this->hook('exit 1')->executar(self::SESSAO);

        self::assertStringContainsString('(tentativa 1 de 3)', (string) $this->hook('exit 1')->executar('{"session_id": "outra"}'));
        self::assertNull($this->hook('true')->executar(self::SESSAO));
        $this->gravar('src/A.php', 'mudou');
        self::assertStringContainsString('(tentativa 1 de 3)', (string) $this->hook('exit 1')->executar(self::SESSAO));
    }

    public function testIdDeSessaoNaoSaiDaPastaDeEstado(): void
    {
        $this->hook('exit 1')->executar('{"session_id": "../../fora"}');

        self::assertFileExists($this->pasta.'/var/check/bloqueios-______fora');
    }

    public function testSoLayoutMudouOHookRodaOLeveENomeiaEleNoMotivo(): void
    {
        $this->hook('true')->executar(self::SESSAO);
        $config = new Config($this->pasta, comandoVerificar: 'exit 1', comandoLeve: 'echo "Twig com erro"; exit 1', arquivosLeves: ['templates/*']);
        $this->gravar('templates/a.html.twig', 'a');

        $json = json_decode((string) new Stop($config, new Verificador($config))->executar(self::SESSAO), true);

        self::assertIsArray($json);
        self::assertIsString($json['reason']);
        self::assertStringStartsWith('A verificação (echo "Twig com erro"; exit 1) falhou (tentativa 1 de 3).', $json['reason']);
        self::assertStringEndsWith("Fim da saída:\n\nTwig com erro", $json['reason']);
    }

    public function testDesligadoPeloAmbienteNaoRoda(): void
    {
        putenv('VERIFICAR_DESLIGADO=1');

        self::assertNull($this->hook('exit 1')->executar(self::SESSAO));
    }

    public function testForaDeUmRepositorioGitNaoRoda(): void
    {
        $this->apagarPasta();
        mkdir($this->pasta);

        self::assertNull($this->hook('exit 1')->executar(self::SESSAO));
    }

    private function hook(string $comando): Stop
    {
        $config = new Config($this->pasta, comandoVerificar: $comando);

        return new Stop($config, new Verificador($config));
    }
}
