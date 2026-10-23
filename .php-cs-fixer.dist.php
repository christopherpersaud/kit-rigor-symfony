<?php

$finder = (new PhpCsFixer\Finder())
    ->in([__DIR__.'/src', __DIR__.'/tests', __DIR__.'/templates'])
    ->append([__DIR__.'/bin/rigor', __DIR__.'/bin/rigor-verificar', __DIR__.'/bin/rigor-cobertura', __DIR__.'/bin/rigor-hook-stop'])
    ->exclude(['Fixtures'])
;

return (new PhpCsFixer\Config())
    ->setRiskyAllowed(true)
    ->setRules([
        '@Symfony' => true,
        'declare_strict_types' => false,
        'native_function_invocation' => ['include' => ['@compiler_optimized'], 'scope' => 'namespaced'],
    ])
    ->setCacheFile(__DIR__.'/var/php-cs-fixer.cache')
    ->setFinder($finder)
;
