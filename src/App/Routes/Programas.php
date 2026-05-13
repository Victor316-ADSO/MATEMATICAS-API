<?php
use Slim\Routing\RouteCollectorProxy;

$group->group('/programas', function(RouteCollectorProxy $subgroup){

    // Obtener todos los programas (solo técnicos laborales)
    $subgroup->get('', 'App\Controllers\ProgramasController:getProgramas');

    // Obtener un programa por ID
    $subgroup->get('/{programa_id}', 'App\Controllers\ProgramasController:getProgramaById');
    
});
