<?php
use Slim\Routing\RouteCollectorProxy;

$group->group('/analytics', function (RouteCollectorProxy $subgroup) {
    $subgroup->get('/dashboard', 'App\Controllers\AnalyticsController:getDashboard');
    $subgroup->get('/matematico', 'App\Controllers\AnalyticsController:getMatematico');
    $subgroup->get('/prediccion', 'App\Controllers\AnalyticsController:getPrediccion');
});
