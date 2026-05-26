<?php
use Slim\Routing\RouteCollectorProxy;

$group->group('/admin/auth', function (RouteCollectorProxy $subgroup) {
    $subgroup->post('/login', 'App\Controllers\AdminAuthController:login');
    $subgroup->get('/verify', 'App\Controllers\AdminAuthController:verifyToken');
    $subgroup->post('/logout', 'App\Controllers\AdminAuthController:logout');
});
