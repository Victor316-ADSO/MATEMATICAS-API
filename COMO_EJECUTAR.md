# 🚀 Cómo Ejecutar la API

## ✅ Opción 1: Apache de XAMPP (Recomendado)

### Pasos:
1. Abre **XAMPP Control Panel**
2. Click en **Start** de Apache (debe quedar verde)
3. Accede a: `http://localhost/back_egresados/`

### URLs:
```
http://localhost/back_egresados/
http://localhost/back_egresados/api/test
http://localhost/back_egresados/api/programas
http://localhost/back_egresados/api/preguntas
```

### Ventajas:
- ✅ No requiere cambios en el código
- ✅ Funciona con la configuración actual
- ✅ Múltiples proyectos simultáneamente

---

## ⚡ Opción 2: Servidor PHP Integrado

### Pasos:
1. Abre terminal en la carpeta del proyecto
2. Ejecuta:
   ```bash
   cd public
   php -S localhost:8080
   ```

3. **IMPORTANTE:** Comenta el basePath en `src/App/App.php`:
   ```php
   // $app->setBasePath('/back_egresados');
   ```

4. Accede a: `http://localhost:8080/`

### URLs:
```
http://localhost:8080/
http://localhost:8080/api/test
http://localhost:8080/api/programas
http://localhost:8080/api/preguntas
```

### Ventajas:
- ✅ No requiere XAMPP
- ✅ Más ligero
- ✅ Ideal para desarrollo rápido

### Desventajas:
- ⚠️ Requiere comentar el basePath
- ⚠️ Solo un proyecto a la vez
- ⚠️ Debes ejecutar desde carpeta `public/`

---

## 📝 Resumen Rápido

### Con XAMPP (Puerto 80):
```bash
# 1. Inicia Apache en XAMPP
# 2. Accede a:
http://localhost/back_egresados/
```

### Con Servidor PHP (Puerto 8080):
```bash
# 1. Comenta en src/App/App.php:
// $app->setBasePath('/back_egresados');

# 2. Ejecuta:
cd public
php -S localhost:8080

# 3. Accede a:
http://localhost:8080/
```

---

## 🔧 Solución de Problemas

### Error "Directory publicc does not exist"
- Verifica que estés en la carpeta correcta
- Debe ser: `C:\xampp\htdocs\back_egresados\public`

### Error 404 con Apache
- Verifica que basePath esté descomentado:
  ```php
  $app->setBasePath('/back_egresados');
  ```

### Error 404 con PHP Server
- Verifica que basePath esté comentado:
  ```php
  // $app->setBasePath('/back_egresados');
  ```

---

## 🎯 Recomendación

**Usa Apache de XAMPP** para:
- Desarrollo normal
- Trabajo con base de datos
- Pruebas completas

**Usa Servidor PHP** para:
- Pruebas rápidas
- Desarrollo sin XAMPP
- Testing de endpoints

---

**Configuración Actual:** Apache con basePath activado  
**URL Principal:** http://localhost/back_egresados/
