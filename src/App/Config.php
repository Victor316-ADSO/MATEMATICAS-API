<?php
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/../../');
$dotenv->load();


$container->set('db_settings', function(){
    return (object)[
        'host' => $_ENV['DB_HOST'],
        'dbname' => $_ENV['DB_NAME'],
        'user'=> $_ENV['DB_USER'],
        'password' => $_ENV['DB_PASS'],
    ];
});