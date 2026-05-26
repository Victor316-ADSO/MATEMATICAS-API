<?php
use Slim\Routing\RouteCollectorProxy;

$group->group('/quiz-adopcion', function (RouteCollectorProxy $subgroup) {
    $subgroup->get('', 'App\Controllers\QuizAdopcionController:getPreguntas');
    $subgroup->get('/estado', 'App\Controllers\QuizAdopcionController:getEstado');
    $subgroup->get('/ultimo-resultado', 'App\Controllers\QuizAdopcionController:getUltimoResultado');
    $subgroup->post('/enviar', 'App\Controllers\QuizAdopcionController:enviar');
});
