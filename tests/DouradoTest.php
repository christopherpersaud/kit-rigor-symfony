<?php

declare(strict_types=1);

namespace KitRigor\Tests;

use KitRigor\Dourado;
use KitRigor\Tests\Support\PastaTemporaria;
use PHPUnit\Framework\AssertionFailedError;
use PHPUnit\Framework\TestCase;

final class DouradoTest extends TestCase
{
    use PastaTemporaria {
        setUp as criarPasta;
        tearDown as apagarPasta;
    }

    protected function setUp(): void
    {
        $this->criarPasta();
        Dourado::usarPasta($this->pasta);
        putenv('ATUALIZAR_DOURADOS');
    }

    protected function tearDown(): void
    {
        putenv('ATUALIZAR_DOURADOS');
        Dourado::usarPasta(null);
        $this->apagarPasta();
    }

    public function testSemOArquivoAprovadoReprovaEPedeParaGerar(): void
    {
        $this->expectException(AssertionFailedError::class);
        $this->expectExceptionMessage('Falta o arquivo dourado');

        Dourado::texto('saida.txt', 'a');
    }

    public function testComAVariavelGravaEDepoisCompara(): void
    {
        putenv('ATUALIZAR_DOURADOS=1');
        Dourado::texto('sub/saida.txt', "linha 1\n");
        putenv('ATUALIZAR_DOURADOS');

        self::assertSame("linha 1\n", $this->ler('sub/saida.txt'));
        Dourado::texto('sub/saida.txt', "linha 1\n");
    }

    public function testSaidaDiferenteReprovaComODiff(): void
    {
        $this->gravar('saida.txt', 'antes');

        try {
            Dourado::texto('saida.txt', 'depois');
            self::fail('A saída diferente deveria reprovar.');
        } catch (AssertionFailedError $falha) {
            self::assertStringContainsString('A saída mudou em relação a', $falha->getMessage());
            self::assertStringContainsString('ATUALIZAR_DOURADOS=1', $falha->getMessage());
        }
    }

    public function testXmlSaiIndentadoSemAssinaturaEComOsElementosIgnorados(): void
    {
        putenv('ATUALIZAR_DOURADOS=1');
        Dourado::xml('nota', '<nota xmlns:ds="http://www.w3.org/2000/09/xmldsig#"><dh>2026-10-23T10:00:00</dh><valor>10.00</valor><ds:Signature><ds:SignatureValue>abc</ds:SignatureValue></ds:Signature></nota>', ['dh']);

        self::assertSame(<<<'XML'
            <?xml version="1.0"?>
            <nota xmlns:ds="http://www.w3.org/2000/09/xmldsig#">
              <dh>(ignorado no teste dourado)</dh>
              <valor>10.00</valor>
            </nota>

            XML, $this->ler('nota.xml'));
    }

    public function testXmlPodeManterAAssinatura(): void
    {
        putenv('ATUALIZAR_DOURADOS=1');
        $documento = new \DOMDocument();
        $documento->loadXML('<a><Signature>x</Signature></a>');
        Dourado::xml('assinado', $documento, semAssinatura: false);

        self::assertStringContainsString('<Signature>x</Signature>', $this->ler('assinado.xml'));
    }

    public function testHtmlTrocaAsImagensEmbutidasEAsTrocasPedidas(): void
    {
        putenv('ATUALIZAR_DOURADOS=1');
        Dourado::html('danfe', '<img src="data:image/png;base64,iVBORw0KGgo="><p>Emitida em 23/10/2026 10:00</p>', ['#\d{2}/\d{2}/\d{4} \d{2}:\d{2}#' => '(data)']);

        self::assertSame('<img src="data:image/png;base64,(imagem)"><p>Emitida em (data)</p>', $this->ler('danfe.html'));
    }

    public function testJsonIndentadoSemEscaparBarrasNemAcentos(): void
    {
        putenv('ATUALIZAR_DOURADOS=1');
        Dourado::json('payload', ['url' => 'https://x/y', 'nome' => 'São Paulo', 'itens' => [1, 2]]);

        self::assertSame(<<<'JSON'
            {
                "url": "https://x/y",
                "nome": "São Paulo",
                "itens": [
                    1,
                    2
                ]
            }

            JSON, $this->ler('payload.json'));
    }
}
