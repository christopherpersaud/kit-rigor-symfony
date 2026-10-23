<?php

declare(strict_types=1);

namespace KitRigor\Tests\Support;

/** Uma pasta temporária por teste, apagada no fim; opcionalmente um repositório git. */
trait PastaTemporaria
{
    private string $pasta = '';

    protected function setUp(): void
    {
        $this->pasta = sys_get_temp_dir().'/kit-rigor-'.bin2hex(random_bytes(6));
        mkdir($this->pasta, 0o777, true);
    }

    protected function tearDown(): void
    {
        $itens = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($this->pasta, \FilesystemIterator::SKIP_DOTS), \RecursiveIteratorIterator::CHILD_FIRST);
        foreach ($itens as $item) {
            if (!$item instanceof \SplFileInfo) {
                continue;
            }
            $item->isDir() && !$item->isLink() ? rmdir($item->getPathname()) : unlink($item->getPathname());
        }
        rmdir($this->pasta);
    }

    private function gravar(string $relativo, string $conteudo): void
    {
        $caminho = $this->pasta.'/'.$relativo;
        if (!is_dir(\dirname($caminho))) {
            mkdir(\dirname($caminho), 0o777, true);
        }
        file_put_contents($caminho, $conteudo);
    }

    private function ler(string $relativo): string
    {
        return (string) file_get_contents($this->pasta.'/'.$relativo);
    }

    private function iniciarGit(): void
    {
        exec(\sprintf('git -C %s init -q 2>&1', escapeshellarg($this->pasta)), $saida, $codigo);
        self::assertSame(0, $codigo, implode("\n", $saida));
    }
}
