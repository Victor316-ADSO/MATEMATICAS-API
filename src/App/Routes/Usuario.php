<?php
use Slim\Routing\RouteCollectorProxy;

$group->group('/usuario', function(RouteCollectorProxy $subgroup){

    // Obtener perfil del usuario
    $subgroup->get('/perfil', 'App\Controllers\UsuarioController:getPerfil');

    // Actualizar perfil del usuario
    $subgroup->put('/perfil', 'App\Controllers\UsuarioController:updatePerfil');
    
    // Obtener datos de contacto
    $subgroup->get('/contacto', 'App\Controllers\UsuarioController:getContacto');
    
    // Actualizar datos de contacto
    $subgroup->post('/contacto', 'App\Controllers\UsuarioController:updateContacto');
    
});
