# Documentación de Endpoints - Programas y Autorización

## Cambios Implementados

### 1. Filtro de Programas Técnicos Laborales

El endpoint de programas ahora **solo retorna programas técnicos laborales**.

**Endpoint:** `GET /api/programas`

**Respuesta exitosa:**
```json
{
  "success": true,
  "message": "Programas técnicos laborales obtenidos correctamente",
  "data": {
    "programas": [
      {
        "codigo": "123",
        "nombre": "TECNICO LABORAL EN SISTEMAS"
      },
      {
        "codigo": "456",
        "nombre": "TÉCNICO LABORAL EN ADMINISTRACIÓN"
      }
    ]
  }
}
```

### 2. Obtener Texto de Autorización

Obtiene el texto de autorización de tratamiento de datos desde el API externo.

**Endpoint:** `GET /api/auth/autorizacion/get`

**Respuesta exitosa:**
```json
{
  "success": true,
  "message": "Autorización obtenida correctamente",
  "data": {
    "contenido": "<html>Texto de autorización...</html>"
  }
}
```

**Ejemplo de uso (JavaScript):**
```javascript
$.ajax({
    url: 'http://localhost/back_egresados/public/api/auth/autorizacion/get',
    type: 'GET',
    dataType: 'json',
    success: function(response) {
        if (response.success) {
            $('#capa_authdb').html(response.data.contenido);
        }
    }
});
```

### 3. Registrar Aceptación de Autorización

Registra que un usuario ha aceptado el tratamiento de datos.

**Endpoint:** `POST /api/programas/autorizacion/set`

**Body (JSON):**
```json
{
  "dni": "1234567890"
}
```

**Respuesta exitosa:**
```json
{
  "success": true,
  "message": "Autorización registrada correctamente",
  "data": {
    "resultado": {
      // Respuesta del API externo
    }
  }
}
```

**Ejemplo de uso (JavaScript):**
```javascript
$.ajax({
    url: 'http://localhost/back_egresados/public/api/programas/autorizacion/set',
    type: 'POST',
    dataType: 'json',
    contentType: 'application/json',
    data: JSON.stringify({
        dni: '1234567890'
    }),
    success: function(response) {
        if (response.success) {
            console.log('Autorización guardada');
        }
    }
});
```

## Integración Completa

### HTML del Formulario
```html
<form id="formulario">
    <div class="input-field">
        <input type="text" id="iden_pers" name="iden_pers" required>
        <label for="iden_pers">Identificación</label>
    </div>
    
    <div class="input-field">
        <select id="codi_prog" name="codi_prog" required>
            <option value="">Cargando programas...</option>
        </select>
        <label>Programa de Egreso</label>
    </div>
    
    <div id="capa_authdb">
        <!-- Aquí se cargará el texto de autorización -->
    </div>
    
    <p>
        <label>
            <input type="checkbox" id="acepto" name="acepto" value="1">
            <span>Acepto el tratamiento de datos</span>
        </label>
    </p>
    
    <button type="button" id="btn_iniciar" class="btn">Iniciar</button>
    
    <div id="mensaje"></div>
</form>
```

### JavaScript Completo
```javascript
const API_BASE_URL = 'http://localhost/back_egresados/public/api';

// Cargar programas técnicos laborales al iniciar
$(document).ready(function() {
    cargarProgramasTecnicosLaborales();
    autorizacion_get();
});

function cargarProgramasTecnicosLaborales() {
    $.ajax({
        url: `${API_BASE_URL}/programas`,
        type: 'GET',
        dataType: 'json',
        success: function(response) {
            if (response.success && response.data.programas) {
                let options = '<option value="">Seleccione un programa</option>';
                response.data.programas.forEach(function(programa) {
                    options += `<option value="${programa.codigo}">${programa.nombre}</option>`;
                });
                $("#codi_prog").html(options);
                M.FormSelect.init(document.querySelectorAll('select'));
            }
        }
    });
}

function autorizacion_get() {
    $.ajax({
        url: `${API_BASE_URL}/programas/autorizacion/get`,
        type: 'GET',
        dataType: 'json',
        beforeSend: function() {
            $("#capa_authdb").html('<div style="text-align:center;"><img src="./comun/loading.gif" width=32> Procesando...</div>');
        },
        success: function(response) {
            if (response.success && response.data.contenido) {
                let contenido = response.data.contenido;
                contenido = contenido.replaceAll('<li>', '<li style="padding-left: 10px;">');
                $('#capa_authdb').html(contenido);
            }
        }
    });
}

function autorizacion_set(dni) {
    $.ajax({
        url: `${API_BASE_URL}/programas/autorizacion/set`,
        type: 'POST',
        dataType: 'json',
        contentType: 'application/json',
        data: JSON.stringify({ dni: dni }),
        beforeSend: function() {
            $("#mensaje").html('<div style="text-align:center;"><img src="./comun/loading.gif" width=32> Procesando...</div>');
        },
        success: function(response) {
            if (response.success) {
                $('#mensaje').html("Autorización guardada correctamente...");
            }
        }
    });
}

function iniciar() {
    var iden_pers = $("#iden_pers").val();
    var codi_prog = $("#codi_prog").val();
    
    if (iden_pers == "") {
        M.toast({html: 'El campo Identificación es requerido', classes: 'rounded', outDuration: 50});
        return;
    }
    if (codi_prog == "") {
        M.toast({html: 'El campo Programa de egreso es requerido', classes: 'rounded', outDuration: 50});
        return;
    }
    if ($('#acepto').is(':checked') == false) {
        M.toast({html: 'El campo Acepto es requerido', classes: 'rounded', outDuration: 50});
        return;
    }
    
    // Registrar autorización
    autorizacion_set(iden_pers);
    
    // Continuar con tu lógica de session.php
    $.ajax({
        url: 'session.php',
        type: 'post',
        dataType: "json",
        data: $('#formulario').serialize(),
        beforeSend: function() {
            $("#mensaje").html("<img src='./comun/loading.gif' width='32'> Procesando...");
        },
        success: function(response) {
            if (response.error == false) {
                location = response.resp;
            } else {
                $("#mensaje").html(response.resp);
            }
        }
    });
}
```

## API Externo Consumido

El backend consume el siguiente API externo de la universidad:

- **GET Autorización:** `https://axis.uninunez.edu.co/apiLDAP/api/authdb/get`
  - Body: `{"dbcod": "21"}`

- **SET Autorización:** `https://axis.uninunez.edu.co/apiLDAP/api/authdb/set`
  - Body: `{"dbcod": "21", "app": "EGRESADOS-UPDATE", "userdni": "dni", "ip": "ip_cliente"}`

## Notas Importantes

1. **Filtro de Programas:** El endpoint `/api/programas` ahora filtra automáticamente solo los programas que contengan "TECNICO LABORAL" o "TÉCNICO LABORAL" en su nombre.

2. **IP del Cliente:** El backend captura automáticamente la IP del cliente (incluyendo X-Forwarded-For para proxies).

3. **CORS:** Si el frontend está en un dominio diferente, asegúrate de configurar CORS en el backend.

4. **Validaciones:** Todas las validaciones se mantienen en el frontend como en tu código original.

## Pruebas con cURL

### Obtener programas técnicos laborales
```bash
curl -X GET http://localhost/back_egresados/public/api/programas
```

### Obtener texto de autorización
```bash
curl -X GET http://localhost/back_egresados/public/api/programas/autorizacion/get
```

### Registrar autorización
```bash
curl -X POST http://localhost/back_egresados/public/api/programas/autorizacion/set \
  -H "Content-Type: application/json" \
  -d '{"dni": "1234567890"}'
```
