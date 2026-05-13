<?php
use Psr\Container\ContainerInterface;

// Inyección de dependencia de base de datos
$container->set('db', function(ContainerInterface $c){
    
    $config = $c->get('db_settings');
    $host = $config->host;
    $dbname = $config->dbname;
    $user = $config->user;
    $password = $config->password;

    $opt=[
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_OBJ,
    ];

    $dsn = "mysql:host={$host};dbname={$dbname};charset=utf8";

    return new PDO($dsn, $user, $password, $opt);
});
