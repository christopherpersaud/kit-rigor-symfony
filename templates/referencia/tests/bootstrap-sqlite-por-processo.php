<?php

// Modelo do kit para tests/bootstrap.php em projetos com Doctrine ORM + dama/doctrine-test-bundle:
// banco SQLite, cache e travas numa pasta por processo (var/test-run/<pid>), para o paratest e
// execuções simultâneas não se atrapalharem, e schema criado uma vez das entidades. Precisa do
// .env.test com DATABASE_URL="sqlite:///%env(TEST_RUN_DIR)%/data.db" (ou deixe este arquivo
// definir) e, se o projeto usa symfony/lock e rate limiter, LOCK_DSN e o cache apontando para
// %env(TEST_RUN_DIR)%. Funde com o bootstrap que o projeto já tem; não copie por cima.

use App\Kernel;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Component\Dotenv\Dotenv;

require dirname(__DIR__).'/vendor/autoload.php';

// Cada execução do PHPUnit tem a sua pasta em var/test-run/<pid>, com o banco SQLite, o cache dos
// limitadores de taxa e as travas (lock). Assim duas execuções ao mesmo tempo (duas sessões do
// agente, o hook, o terminal ou os processos do paratest) não apagam o schema nem gastam o limite ou a trava uma da outra.
// Definido antes do bootEnv, o Dotenv não sobrescreve estes valores com os do .env.test; um
// DATABASE_URL vindo de fora continua valendo. A pasta é apagada no fim.
$pastaDaExecucao = sprintf('%s/var/test-run/%d', dirname(__DIR__), getmypid());
if (!is_dir($pastaDaExecucao)) {
    mkdir($pastaDaExecucao.'/lock', 0777, true);
}
$daExecucao = ['TEST_RUN_DIR' => $pastaDaExecucao, 'LOCK_DSN' => 'flock://'.$pastaDaExecucao.'/lock'];
// Os processos do paratest herdam o ambiente do processo principal, que também passa por aqui: um
// DATABASE_URL que aponta para var/test-run é de outra execução e é trocado pelo deste processo.
$deFora = getenv('DATABASE_URL');
$deFora = false !== $deFora ? $deFora : ($_SERVER['DATABASE_URL'] ?? null);
if (!is_string($deFora) || str_contains($deFora, '/var/test-run/')) {
    $daExecucao['DATABASE_URL'] = 'sqlite:///'.$pastaDaExecucao.'/data.db';
}
foreach ($daExecucao as $nome => $valor) {
    putenv($nome.'='.$valor);
    $_ENV[$nome] = $_SERVER[$nome] = $valor;
}
$apagar = static function (string $pasta): void {
    if (!is_dir($pasta)) {
        return;
    }
    $itens = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($pasta, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
    foreach ($itens as $item) {
        $item->isDir() ? rmdir((string) $item) : unlink((string) $item);
    }
    rmdir($pasta);
};
// Uma execução interrompida (Ctrl+C, timeout) não chega ao shutdown: a pasta dela fica para trás
// e é apagada aqui, pela próxima, quando o processo dono já não existe.
foreach (glob(dirname($pastaDaExecucao).'/*', GLOB_ONLYDIR) ?: [] as $pasta) {
    if (!is_dir('/proc/'.basename($pasta))) {
        $apagar($pasta);
    }
}
register_shutdown_function(static fn () => $apagar($pastaDaExecucao));
unset($daExecucao, $nome, $valor, $pasta, $deFora);

(new Dotenv())->bootEnv(dirname(__DIR__).'/.env');

if ($_SERVER['APP_DEBUG']) {
    umask(0000);
}

// Banco SQLite de teste (DATABASE_URL do .env.test) criado UMA vez por execução, a partir do
// mapeamento das entidades (as migrations são MySQL). Tem de ser aqui, antes de o PHPUnit começar:
// o dama/doctrine-test-bundle envolve cada teste numa transação com rollback, e um DDL feito
// dentro dela seria desfeito junto. A partir daí os testes só gravam e o rollback limpa.
$kernel = new Kernel('test', (bool) $_SERVER['APP_DEBUG']);
$kernel->boot();

$em = $kernel->getContainer()->get('doctrine')->getManager();
if (!$em instanceof EntityManagerInterface) {
    throw new LogicException('O container de teste não devolveu o EntityManager.');
}
$schemaTool = new SchemaTool($em);
$metadata = $em->getMetadataFactory()->getAllMetadata();
$schemaTool->dropSchema($metadata);
$schemaTool->createSchema($metadata);

$kernel->shutdown();
unset($kernel, $em, $schemaTool, $metadata);
