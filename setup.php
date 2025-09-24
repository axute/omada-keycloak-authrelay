<?php

require_once("vendor/autoload.php");
if( !file_exists( __DIR__ . '/.env' ) ) {
    $dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
    $dotenv->load();
}
