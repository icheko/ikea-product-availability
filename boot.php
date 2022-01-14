<?php

require __DIR__ . "/vendor/autoload.php";
require __DIR__ . "/helpers.php";

$dotenv = \Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->safeLoad();