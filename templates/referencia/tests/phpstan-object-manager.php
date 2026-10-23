<?php

use App\Kernel;

require __DIR__.'/../vendor/autoload.php';

// O tipo que o phpstan-doctrine deduz para o resultado das consultas depende da plataforma do banco.
// Fixe a de produção, para a análise não mudar com o .env de cada máquina (o CI lê o .env
// versionado). Com serverVersion, o Doctrine não tenta conectar. Definida antes do bootEnv, vale.
// $_SERVER['DATABASE_URL'] = $_ENV['DATABASE_URL'] = 'mysql://analise:analise@127.0.0.1:3306/analise?serverVersion=8.4.0&charset=utf8mb4';
(new Symfony\Component\Dotenv\Dotenv())->bootEnv(__DIR__.'/../.env');

$kernel = new Kernel('dev', true);
$kernel->boot();

return $kernel->getContainer()->get('doctrine')->getManager();
