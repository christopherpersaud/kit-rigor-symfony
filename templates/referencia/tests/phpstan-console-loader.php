<?php

use App\Kernel;
use Symfony\Bundle\FrameworkBundle\Console\Application;

require __DIR__.'/../vendor/autoload.php';
(new Symfony\Component\Dotenv\Dotenv())->bootEnv(__DIR__.'/../.env');

$kernel = new Kernel('dev', true);

return new Application($kernel);
