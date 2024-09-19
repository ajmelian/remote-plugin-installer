# Remote Plugin Installer

**Versión:** 1.0 
**Fecha**: 2024/09/06
**Autor:** Aythami Melián Perdomo <ajmelper@gmail.com>  
**Licencia:** GPLv3  
**Requiere PHP:** 5.6  
**Requiere WordPress:** 4.7

## Descripción

El **plugin "Remote Plugin Installer"** permite gestionar la instalación, activación, desactivación y listado de plugins en un sitio WordPress de forma remota.

### Funciones clave del plugin:

1. **Instalación remota de plugins**: Permite subir un archivo `.zip` de un plugin y automáticamente instalarlo y activarlo.

2. **Activación/desactivación de plugins**: Permite activar o desactivar cualquier plugin ya instalado, proporcionando el identificador del plugin.

3. **Listado de plugins**: Proporciona una lista de todos los plugins instalados en el sitio, mostrando su nombre, identificador y estado (activo o inactivo).

Este plugin es útil para desarrolladores que quieran automatizar o gestionar plugins de forma remota en sitios WordPress sin tener que acceder al panel de administración manualmente.

**Nota**: Para el correcto funcionamiento de este plugin, es necesario generar *Contraseña de Aplicación* para el usuario gestor. Las contraseñas de aplicación permiten la identificación a través de sistemas no interactivos, como XML-RPC o la API REST, evitando el proporcionar la contraseña real.

## Instalación del Plugin

1. **Descargar el Plugin:**
   
   - Descarga el archivo `remote-plugin-installer.zip`.

2. **Subir el Plugin:**
   
   - Accede al panel de administración de WordPress.
   - Ve a la sección de `Plugins > Añadir nuevo`.
   - Haz clic en `Subir Plugin` y selecciona el archivo `.zip` del plugin que descargaste.
   - Haz clic en `Instalar Ahora`.

3. **Activar el Plugin:**
   
   - Una vez instalado, haz clic en `Activar` para habilitar el plugin en tu sitio web.

## Uso del Plugin

El plugin ofrece varios endpoints REST para la gestión remota de otros plugins. Se requiere autenticación constante mediante credenciales y contraseña de aplicación.

### Subir e Instalar un Plugin

**Endpoint:** `/wp-json/remote-plugin-installer/v1/install-plugin`  
**Método:** `POST`  
**Parámetros:**

- *zipFile*: Path del Archivo `ZIP` del plugin en nuestro equipo local.

**Ejemplo de cURL para instalar un plugin:**

```bash
curl -X POST -F "zipFile=@/ruta/al/plugin.zip" --user "[USUARIO]:[CONTRASEÑA_APLICACIÓN]" http://tu-sitio-web.com/wp-json/remote-plugin-installer/v1/install-plugin
```

**Respuesta esperada:**

```json
{
    "code": "success",
    "message": "Plugin instalado y activado con éxito.",
    "plugin": "Plugin Name"
}
```

### Activar/Desactivar un Plugin

**Endpoint:** `/wp-json/remote-plugin-installer/v1/toggle-plugin`  
**Método:** `POST`  
**Parámetros:**

- *slug*: identificador del plugin en formato `plugin-folder/plugin-file.php`).
- *action*: acción a realizar, `activate` para activar el plugin, `deactivate` para desactivarlo.

**Ejemplo de cURL para activar/desactivar un plugin:**

```bash
curl -X POST -d "slug=nombre-del-plugin/nombre-del-plugin.php" -d "action=[activate|deactivate]" --user "[USUARIO]:[CONTRASEÑA_APLICACIÓN]" http://tu-sitio-web.com/wp-json/remote-plugin-installer/v1/toggle-plugin
```

**Respuesta esperada:**

- Si el plugin se activó correctamente:
  
  ```json
  {
    "code": "success",
    "message": "Plugin activate con éxito.",
    "plugin": "plugin-slug"
  }
  ```

- Si el plugin ya estaba activado y se desactivó:
  
  ```json
  {
    "message": "Plugin desactivado correctamente"
  }
  ```

### Listar Plugins Disponibles

**Endpoint:** `/wp-json/remote-plugin-installer/v1/list-plugins`  
**Método:** `GET`

**Ejemplo de cURL para listar los plugins instalados:**

```bash
curl -X GET --user "[USUARIO]:[CONTRASEÑA_APLICACIÓN]" http://tu-sitio-web.com/wp-json/remote-plugin-installer/v1/list-plugins
```

**Respuesta esperada:**

```json
[
    {
        "plugin_file": "plugin-folder/plugin-file.php",
        "plugin_name": "Plugin Name",
        "is_active": true
    },
    {
        "plugin_file": "another-plugin-folder/another-plugin-file.php",
        "plugin_name": "Another Plugin Name",
        "is_active": false
    }
]
```

## Gestión de Errores

- **403 Forbidden (Token no válido o no proporcionado):**
  Si no se proporciona un token o el token es incorrecto, la API devolverá:
  
  ```json
  {
    "code": "invalid_token",
    "message": "Token inválido o no proporcionado.",
    "data": {
      "status": 403
    }
  }
  ```

- **400 Bad Request (Error en los parámetros):**
  Si faltan parámetros obligatorios, como el slug del plugin o el archivo ZIP, la API devolverá:
  
  ```json
  {
    "code": "rest_invalid_param",
    "message": "Debe proporcionar el slug del plugin.",
    "data": {
      "status": 400
    }
  }
  ```

- **401 Unauthorized (Error en los parámetros):** 
  Si la acción solicitada no ha sido ejecutada porque carece de credenciales válidas de autenticación, la API devolverá:
  
  ```json
  {
    "code": "rest_forbidden",
    "message": "Lo siento, no tienes permisos para hacer eso.",
    "data": {
      "status": 401
    }
  }
  ```

- **500 Internal Server Error (Error interno):**
  Si algo falla en el servidor, como un error al mover un archivo o al activar un plugin, se devolverá un error genérico:
  
  ```json
  {
    "message": "Error al activar el plugin: detalle_del_error"
  }
  ```