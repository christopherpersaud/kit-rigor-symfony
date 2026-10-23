<?php

// Só regras que não mexem no layout: imports (sem uso, em ordem, agrupados) e linhas em branco.
// Assim o modelo serve a qualquer estilo de recuo; um projeto no layout do Symfony pode acrescentar
// '@Symfony' => true. O composer check:rapido roda em --dry-run; `vendor/bin/php-cs-fixer fix` aplica.
$finder = (new PhpCsFixer\Finder())
    ->in(__DIR__)
    ->exclude(['var', 'node_modules', 'public', 'assets/vendor'])
    ->notPath([
        'config/bundles.php',
        'config/reference.php',
    ])
;

return (new PhpCsFixer\Config())
    ->setRules([
        'no_unused_imports' => true,
        'ordered_imports' => ['imports_order' => ['class', 'function', 'const']],
        'blank_line_between_import_groups' => true,
        'single_line_after_imports' => true,
        'no_extra_blank_lines' => true,
        'line_ending' => true,
    ])
    ->setCacheFile(__DIR__.'/var/cache/php-cs-fixer.cache')
    ->setFinder($finder)
;
