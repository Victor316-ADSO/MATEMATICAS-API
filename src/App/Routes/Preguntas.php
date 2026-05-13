<?php
use Slim\Routing\RouteCollectorProxy;

$group->group('/preguntas', function(RouteCollectorProxy $subgroup){

    // Obtener todas las preguntas
    $subgroup->get('', 'App\Controllers\PreguntasController:getPreguntas');
    
});
