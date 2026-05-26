<?php
use Slim\Routing\RouteCollectorProxy;

// Ruta para responder a preflight OPTIONS sin agregar cabeceras CORS manualmente.
$app->options('/{routes:.+}', function ($request, $response, $args) {
    return $response;
});

// Agrupar rutas bajo el prefijo /api
$app->group('/api', function(RouteCollectorProxy $group){
    
    // Ruta de test
    $group->get('/test', function ($request, $response) {
        $response->getBody()->write(json_encode(['message' => 'API funcionando correctamente', 'status' => 'OK']));
        return $response->withHeader('Content-Type', 'application/json');
    });
    
    //===========================[Rutas de Autenticación]=========================
    require __DIR__ . '/Routes/Auth.php';
    
    //===========================[Rutas de Programas]=========================
    require __DIR__ . '/Routes/Programas.php';
    
    //===========================[Rutas de Preguntas]=========================
    require __DIR__ . '/Routes/Preguntas.php';
    
    //===========================[Rutas de Cuestionario]=========================
    require __DIR__ . '/Routes/Cuestionario.php';
    
    //===========================[Rutas de Usuario]=========================
    require __DIR__ . '/Routes/Usuario.php';
    
    //===========================[Rutas de Respuestas]=========================
    require __DIR__ . '/Routes/Respuestas.php';

    //===========================[Auth Administradores Analytics]=========================
    require __DIR__ . '/Routes/AdminAuth.php';

    //===========================[Analytics Matemático]=========================
    require __DIR__ . '/Routes/Analytics.php';

    //===========================[Quiz Adopción U(t)]=========================
    require __DIR__ . '/Routes/QuizAdopcion.php';
});


