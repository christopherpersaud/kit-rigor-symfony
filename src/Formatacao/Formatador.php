<?php

declare(strict_types=1);

namespace KitRigor\Formatacao;

/** Usa os scripts do projeto, sem impor ferramenta nem configuração de estilo. */
final readonly class Formatador
{
    public function __construct(private string $raiz)
    {
    }

    public function executar(bool $ci): int
    {
        $composer = json_decode((string) file_get_contents($this->raiz.'/composer.json'), true, flags: \JSON_THROW_ON_ERROR);
        if (!\is_array($composer) || !\is_array($composer['scripts'] ?? null)) {
            return 0;
        }
        $scripts = $composer['scripts'];
        if (!isset($scripts['formatar'])) {
            return 0;
        }
        $script = $ci ? 'formatar:verificar' : 'formatar';
        if (!isset($scripts[$script])) {
            fwrite(STDERR, "O CI exige o script formatar:verificar (sem alterar arquivos) quando formatar está configurado.\n");

            return 1;
        }
        $processo = proc_open('composer --no-interaction run-script '.escapeshellarg($script), [0 => ['pipe', 'r'], 1 => STDOUT, 2 => STDERR], $pipes, $this->raiz);
        if (false === $processo) {
            return 1;
        }
        fclose($pipes[0]);

        return proc_close($processo);
    }
}
