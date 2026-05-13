<?php
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Factory\AppFactory;

require __DIR__ . '/../../vendor/autoload.php';

$aux = new \DI\Container();
AppFactory::setContainer($aux);
$app = AppFactory::create();
$app->setBasePath('/cuestionario-api');

$container = $app->getContainer();

$app->get('/hello/{name}', function (Request $request, Response $response, $args) {
    $name = $args['name'];
    $response->getBody()->write("Hello, $name");
    return $response;
});

$app->get('/', function (Request $request, Response $response) {
    $payload = [
        'message' => 'API disponible',
        'endpoints' => [
            '/api/test',
            '/api/auth/login',
            '/api/programas',
            '/api/preguntas',
            '/api/cuestionario',
            '/api/usuario',
            '/api/respuestas'
        ]
    ];

    $response->getBody()->write(json_encode($payload));
    return $response->withHeader('Content-Type', 'application/json');
});

// Cargar rutas, configuración y dependencias
require __DIR__ . '/Routes.php';

require __DIR__ . '/Config.php';

require __DIR__ . '/Dependencies.php';


$app->run();
