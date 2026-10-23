<?php

// Trava de cobertura de linhas conferida por vendor/bin/rigor-cobertura no job "cobertura" do CI
// (AGENTS.md, bloco do kit, "Checks"). As chaves de `pisos` são as pastas de src/ (Domain/<módulo>
// para os módulos); uma pasta fora da lista usa o `padrao`, e src/ inteiro não fica abaixo do `total`.
//
// Cada piso é a cobertura medida arredondada para baixo menos a `margem`, para um refactor pequeno
// não reprovar. Os pisos só sobem: quando o rigor-cobertura avisa que um piso pode subir, suba-o na
// mesma mudança. Baixar um piso ou pôr um arquivo em `sem_teste` é decisão do usuário, com o motivo
// ao lado.
//
// Na instalação do kit, a skill rigor-instalar mede a cobertura e preenche `total` e `pisos`.

return [
    'margem' => 2,
    'total' => 0,
    'padrao' => 90,
    'pisos' => [
    ],
    // Arquivos com código e nenhuma linha coberta, aceitos por enquanto: caminho => motivo.
    'sem_teste' => [],
];
