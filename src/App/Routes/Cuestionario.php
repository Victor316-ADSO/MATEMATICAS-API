<?php
use Slim\Routing\RouteCollectorProxy;

$group->group('/cuestionario', function(RouteCollectorProxy $subgroup){

    // Responder cuestionario
    $subgroup->post('/responder', 'App\Controllers\CuestionarioController:responder');
    
});

// Ruta para obtener respuestas del usuario (fuera del grupo cuestionario)
$group->get('/mis-respuestas', 'App\Controllers\CuestionarioController:getMisRespuestas');
