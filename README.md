#  Panel de Gestión de Llamadas - Grandstream UCM

<p align="center">
  <img src="https://img.shields.io/badge/Laravel-11-FF2D20?style=for-the-badge&logo=laravel&logoColor=white" alt="Laravel 11">
  <img src="https://img.shields.io/badge/PHP-8.2+-777BB4?style=for-the-badge&logo=php&logoColor=white" alt="PHP 8.2+">
  <img src="https://img.shields.io/badge/Tailwind-CSS-38B2AC?style=for-the-badge&logo=tailwind-css&logoColor=white" alt="Tailwind CSS">
  <img src="https://img.shields.io/badge/Grandstream-UCM-0078D4?style=for-the-badge" alt="Grandstream UCM">
</p>

Panel de administración y monitoreo de llamadas para centrales telefónicas **Grandstream UCM**. Permite visualizar, filtrar, exportar y gestionar registros de llamadas (CDR) y extensiones de manera sencilla, segura y en tiempo real.

##  Características

-  **Dashboard interactivo** con estadísticas de llamadas
-  **Sincronización automática** de CDRs desde la central Grandstream
-  **Gestión de extensiones** con estado en tiempo real
-  **Gráficos y reportes** de llamadas entrantes, salientes y perdidas
-  **Exportación a Excel/PDF** de reportes personalizados
-  **Autenticación segura** con roles de usuario
-  **Interfaz moderna** con Tailwind CSS

---

##  Requisitos del Sistema

| Requisito | Versión Mínima |
|-----------|----------------|
| XAMPP | 8.2+ (incluye PHP y MySQL) |
| PHP | 8.2 o superior |
| Composer | 2.x |
| Node.js | 18.x o superior |
| NPM | 9.x o superior |
| MySQL | 8.0 |
| Central Grandstream | UCM con API habilitada |

>  **Recomendado:** Usar [XAMPP](https://www.apachefriends.org/) como entorno de desarrollo local, ya que incluye Apache, PHP y MySQL preconfigurados.

---

##  Instalación (recomendado usar CMD y no powershell)

### 0. Editar PHP.ini

> Se encuentra en la ruta `/XAMPP/php` 

Aqui se debe editar la linea ;extension=gd y ;extension=zip
Quitandole los ; del principio
### 1. Clonar el repositorio en XAMPP

Clona el proyecto dentro de la carpeta `htdocs` de XAMPP:

```bash
cd C:\xampp\htdocs
git clone https://github.com/tu-usuario/panel-gestion-llamadas.git
cd panel-gestion-llamadas
```

> En Linux/Mac la ruta sería `/opt/lampp/htdocs/`

### 2. Instalar dependencias de PHP

```bash
composer install
```

### 3. Instalar dependencias de Node.js

```bash
npm install
```

### 4. Configurar el archivo de entorno

```bash
cp .env.example .env
```

### 5. Generar la clave de aplicación

```bash
php artisan key:generate
```

### 6. Compilar assets (CSS/JS)

```bash
npm run build
```

---

##  Configuración del `.env`

Edita el archivo `.env` con los valores correspondientes a tu entorno:

###  Base de Datos (OJO CON LOS # AL COMIENZO, QUEDAN COMO COMENTARIO)

Para **MySQL**:
```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=panel_llamadas
DB_USERNAME=tu_usuario
DB_PASSWORD=tu_contraseña
```


###  Configuración de Grandstream UCM

Estas variables son **obligatorias** para conectar con tu central telefónica:

```env
# IP o dominio de la central Grandstream
GRANDSTREAM_IP=192.168.1.100

# Puerto de la API (por defecto 7110)
GRANDSTREAM_PORT=7110

# Usuario con permisos de API en la central
GRANDSTREAM_USER=api_user

# Contraseña del usuario API
GRANDSTREAM_PASS=tu_contraseña_api

# Verificar SSL (false para certificados auto-firmados)
GRANDSTREAM_VERIFY_SSL=false
```

>  **Importante:** El usuario debe tener permisos de API habilitados en la central Grandstream UCM.

###  Credenciales del Seeder de Usuarios

Configura los usuarios que se crearán al ejecutar el seeder:

```env
# Usuario Administrador
ADMIN_USER=Administrador
ADMIN_MAIL=admin@tuempresa.com
ADMIN_PASS=contraseña_segura_admin

# Usuario Regular
USUARIO_USER=Usuario
USUARIO_MAIL=usuario@tuempresa.com
USUARIO_PASS=contraseña_segura_usuario
```

---

##  Base de Datos

### Ejecutar migraciones

```bash
php artisan migrate
```

### Crear usuarios iniciales

```bash
php artisan db:seed --class=UserSeeder
```

Este comando creará los usuarios configurados en las variables de entorno (`ADMIN_*` y `USUARIO_*`).

---

##  Comandos Personalizados

El panel incluye comandos Artisan para sincronización con la central Grandstream:

### Sincronizar CDRs (Registros de Llamadas)

```bash
php artisan calls:sync #<--- Este trae solo las llamadas del 2026 (en caso de que sean muchas llamadas lo mejor es ir año por año)
```

Opciones disponibles:
```bash
# Sincronizar un año específico
php artisan calls:sync --year=2023 #<--- con 2023 se traen todas las llamadas desde el 2023 hasta la fecha actual
```
- RECOMENDADO LA PRIMERA VEZ

Este comando:
- Conecta con la API de la central Grandstream
- Descarga los registros de llamadas (CDR)
- Almacena los datos en la base de datos local
- Evita duplicados automáticamente

### Sincronizar Extensiones

```bash
php artisan extensions:sync
php artisan extensions:import
```

Este comando:
- Obtiene la lista de extensiones configuradas en la central
- Sincroniza nombres, estados y configuraciones
- Actualiza la información en tiempo real


##  Ejecutar con XAMPP

### 1. Iniciar servicios de XAMPP

Abre el **Panel de Control de XAMPP** e inicia:
-  **Apache**
-  **MySQL**

### 2. Configurar Virtual Host (Opcional)

Para acceder mediante un dominio personalizado, edita el archivo `httpd-vhosts.conf`:

**Windows:** `C:\xampp\apache\conf\extra\httpd-vhosts.conf`

```apache
<VirtualHost *:80>
    DocumentRoot "C:/xampp/htdocs/panel-llamadas/public"
    ServerName panel-llamadas.local
    <Directory "C:/xampp/htdocs/panel-llamadas/public">
        AllowOverride All
        Require all granted
    </Directory>
</VirtualHost>
```

Y agrega al archivo `hosts`:

**Windows:** `C:\Windows\System32\drivers\etc\hosts`
```
127.0.0.1 panel-llamadas.local
```

### 3. Acceder a la aplicación

- **Sin Virtual Host:** `http://localhost/panel-llamadas/public`
- **Con Virtual Host:** `http://panel-llamadas.local`
- **En red local:** `http://[IP-DEL-SERVIDOR]/panel-llamadas/public`

### 4. Compilar assets en desarrollo (opcional)

Si necesitas modificar estilos o JavaScript:

```bash
npm run dev
```

---

##  Estructura del Proyecto

```
├── app/
│   ├── Console/Commands/     # Comandos Artisan personalizados
│   │   ├── SyncCalls.php     # Sincronización de CDRs
│   │   └── SyncExtensions.php # Sincronización de extensiones
│   ├── Exports/              # Exportaciones Excel
│   ├── Http/Controllers/     # Controladores
│   ├── Models/               # Modelos Eloquent
│   └── Traits/               # Traits reutilizables (GrandstreamTrait)
├── config/
│   └── services.php          # Configuración de servicios externos
├── database/
│   ├── migrations/           # Migraciones de BD
│   └── seeders/              # Seeders de datos iniciales
├── resources/
│   └── views/                # Vistas Blade
└── routes/
    └── web.php               # Rutas de la aplicación
```

---

##  Seguridad

- Las credenciales sensibles se manejan exclusivamente mediante variables de entorno
- El archivo `.env` está excluido del repositorio (`.gitignore`)
- Las contraseñas se almacenan con hash Bcrypt
- Protección CSRF en todos los formularios

---


