<?php
require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../bootstrap.php';

use Iem\Application;
use Iem\OrmHelper;

//session_start();

OrmHelper::setEntityManager($entityManager);

$app = new Application();
$app->run();

?>
