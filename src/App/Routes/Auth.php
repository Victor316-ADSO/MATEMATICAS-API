<?php
use Slim\Routing\RouteCollectorProxy;

$group->group('/auth', function(RouteCollectorProxy $subgroup){

    // Login de usuario
    $subgroup->post('/login', 'App\Controllers\AuthController:login');

    // Registro de egresado (personas + egresados + contacto opcional)
    $subgroup->post('/registro', 'App\Controllers\AuthController:registro');

    // Verificar token JWT
    $subgroup->get('/verify', 'App\Controllers\AuthController:verifyToken');

    // Refrescar token JWT
    $subgroup->post('/refresh', 'App\Controllers\AuthController:refreshToken');

    // Logout (cerrar sesión)
    $subgroup->post('/logout', 'App\Controllers\AuthController:logout');

    // Obtener texto de autorización de tratamiento de datos
    $subgroup->get('/autorizacion/get', 'App\Controllers\AuthController:getAutorizacion');
    
    // Registrar aceptación de tratamiento de datos
    $subgroup->post('/autorizacion/set', 'App\Controllers\AuthController:setAutorizacion');
});
