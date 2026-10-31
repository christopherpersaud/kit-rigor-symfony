<?php

declare(strict_types=1);

use KitRigor\Config;
use KitRigor\Verificar\Escopo;

require dirname(__DIR__).'/vendor/autoload.php';

/** @param list<string> $comando */
function executar(array $comando): void
{
    echo '> '.implode(' ', $comando).PHP_EOL;
    $processo = proc_open($comando, [0 => STDIN, 1 => STDOUT, 2 => STDERR], $pipes, dirname(__DIR__));
    if (false === $processo) {
        throw new RuntimeException('Não foi possível iniciar a verificação.');
    }
    $codigo = proc_close($processo);
    if (0 !== $codigo) {
        exit($codigo);
    }
}

try {
    chdir(dirname(__DIR__));
    $config = Config::daPasta(dirname(__DIR__));
    $escopo = new Escopo($config);
    $modo = $argv[1] ?? 'check';
    if (!in_array($modo, ['check', 'leve', 'test', 'phpstan'], true)) {
        throw new InvalidArgumentException('Use check, leve, test ou phpstan.');
    }
    $arquivos = $escopo->arquivos;
    $testes = $escopo->testes;
    $argumentos = array_slice($argv, 2);
    if ([] !== $argumentos) {
        if ('check' === $modo) {
            throw new InvalidArgumentException('Atualize o manifesto para alterar o escopo do check.');
        }
        if ('test' === $modo) {
            $testes = $escopo->validarTestes($argumentos);
        } else {
            $arquivos = $escopo->validarArquivos($argumentos);
        }
    }
    if ('leve' === $modo && !$escopo->somenteLayout($arquivos)) {
        throw new InvalidArgumentException('O escopo não contém somente layout. Use composer check:rapido.');
    }

    if (in_array($modo, ['check', 'leve'], true)) {
        foreach (array_unique('leve' === $modo ? $arquivos : [...$arquivos, ...$testes]) as $arquivo) {
            if (str_ends_with($arquivo, '.php')) {
                executar([PHP_BINARY, '-l', $arquivo]);
            } elseif (str_ends_with($arquivo, '.twig')) {
                executar([PHP_BINARY, 'bin/console', 'lint:twig', $arquivo, '--env=test']);
            } elseif (str_ends_with($arquivo, '.yaml') || str_ends_with($arquivo, '.yml')) {
                executar([PHP_BINARY, 'bin/console', 'lint:yaml', $arquivo, '--parse-tags', '--env=test']);
            }
        }
        // Adapte ao diretório de assets e ao comando de build do projeto.
        if (array_any($arquivos, static fn (string $arquivo): bool => str_starts_with($arquivo, 'assets/') || in_array($arquivo, ['webpack.config.js', 'package.json', 'package-lock.json'], true))) {
            executar(['npm', 'run', 'dev']);
        }
    }
    if ('leve' === $modo) {
        echo 'Verificação leve concluída.'.PHP_EOL;
        exit(0);
    }
    if ('test' !== $modo) {
        $fontes = array_values(array_filter($arquivos, static function (string $arquivo) use ($config): bool {
            if (!str_ends_with($arquivo, '.php')) {
                return false;
            }
            foreach ($config->testesPhp as $pasta) {
                if (str_starts_with($arquivo, rtrim($pasta, '/').'/')) {
                    return false;
                }
            }

            return true;
        }));
        if ([] !== $fontes) {
            executar([PHP_BINARY, 'vendor/bin/phpstan', 'analyse', '--memory-limit=1G', ...$fontes]);
        }
    }
    if ('phpstan' !== $modo) {
        executar([PHP_BINARY, '-d', 'pcov.enabled=0', 'vendor/bin/phpunit', '--no-coverage', ...$testes]);
    }
} catch (InvalidArgumentException|RuntimeException|JsonException $erro) {
    fwrite(STDERR, $erro->getMessage().PHP_EOL);
    exit(2);
}
