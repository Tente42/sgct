#  Panel de Gestión de Llamadas - Grandstream UCM

<p align="center">
  <img src="https://img.shields.io/badge/Laravel-11-FF2D20?style=for-the-badge&logo=laravel&logoColor=white" alt="Laravel 11">
  <img src="https://img.shields.io/badge/PHP-8.2+-777BB4?style=for-the-badge&logo=php&logoColor=white" alt="PHP 8.2+">
  <img src="https://img.shields.io/badge/Tailwind-CSS-38B2AC?style=for-the-badge&logo=tailwind-css&logoColor=white" alt="Tailwind CSS">
  <img src="https://img.shields.io/badge/Alpine.js-3.x-8BC0D0?style=for-the-badge&logo=alpine.js&logoColor=white" alt="Alpine.js">
  <img src="https://img.shields.io/badge/Grandstream-UCM-0078D4?style=for-the-badge" alt="Grandstream UCM">
</p>

Panel de administración y monitoreo de llamadas para centrales telefónicas **Grandstream UCM**. Permite visualizar, filtrar, exportar y gestionar registros de llamadas (CDR) y extensiones de manera sencilla, segura y en tiempo real.

##  Características

-  **Dashboard interactivo** con estadísticas de llamadas en tiempo real
-  **Sistema Multi-Central** — Gestiona múltiples centrales PBX desde una sola interfaz
-  **Control de acceso por central** — Cada usuario solo ve las centrales que el admin le asigne
-  **Sincronización automática** de CDRs desde la central Grandstream
-  **Gestión de extensiones** con actualización de IPs bajo demanda
-  **Desvíos de llamadas** — Configura Call Forwarding directamente desde la interfaz
-  **Gráficos y reportes** de llamadas entrantes, salientes y perdidas
-  **Estadísticas de colas (KPI)** — Volumen, abandono, ASA, rendimiento de agentes
-  **Exportación a Excel/PDF** de reportes personalizados
-  **Gestión de usuarios** con roles (Admin, Supervisor, Usuario) y permisos granulares
-  **Tarifas configurables** — Precios por minuto según destino (celular, nacional, internacional)
-  **Interfaz moderna** con Tailwind CSS y Alpine.js
-  **Protección contra sincronizaciones simultáneas** mediante sistema de locks

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

###  URL de la Aplicación (MUY IMPORTANTE)

La variable `APP_URL` debe coincidir **exactamente** con la URL desde donde accedes a la aplicación:

```env
# Si accedes desde: http://localhost/panel-gestion-llamadas
APP_URL=http://localhost/panel-gestion-llamadas

# Si accedes desde otra PC en red: http://10.61.17.92/panel-gestion-llamadas
APP_URL=http://10.61.17.92/panel-gestion-llamadas

# Si usas Virtual Host: http://panel-llamadas.local
APP_URL=http://panel-llamadas.local

# En producción con dominio:
APP_URL=https://llamadas.tuempresa.com
```

>  **Nota:** Si `APP_URL` no coincide con la URL real, la sincronización y otras funciones AJAX fallarán con error 404.

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

> **Nota:** A partir de la versión con soporte Multi-Central, las credenciales de las centrales se configuran **directamente desde la interfaz web**, no en el archivo `.env`.

Las siguientes variables en `.env` son opcionales y solo se usan como valores por defecto:

```env
# IP o dominio de la central Grandstream (opcional)
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

El panel incluye comandos Artisan para sincronización con la central Grandstream.

> **Importante:** Todos los comandos requieren especificar la central PBX con `--pbx=ID`

### Ver centrales disponibles

Si ejecutas un comando sin especificar `--pbx`, verás la lista de centrales configuradas:

```bash
php artisan calls:sync
# Salida:
# No se especificó central. Centrales disponibles:
# +----+-------------------+-------------+
# | ID | Nombre            | IP          |
# +----+-------------------+-------------+
# | 1  | Central Principal | 10.36.1.10  |
# | 5  | Central Prueba    | 12.34.56.78 |
# +----+-------------------+-------------+
# Uso: php artisan calls:sync --pbx=1 --year=2024
```

### Sincronizar CDRs (Registros de Llamadas)

```bash
# Sincronizar llamadas del año actual
php artisan calls:sync --pbx=1

# Sincronizar desde un año específico
php artisan calls:sync --pbx=1 --year=2023
```

Opciones disponibles:
| Opción | Descripción |
|--------|-------------|
| `--pbx=ID` | **Obligatorio.** ID de la central PBX |
| `--year=YYYY` | Año desde el cual sincronizar (default: año actual) |

Este comando:
- Conecta con la API de la central Grandstream
- Descarga los registros de llamadas (CDR)
- Almacena los datos en la base de datos local
- Evita duplicados automáticamente
- Asocia las llamadas a la central especificada

### Sincronizar Extensiones

```bash
# Sincronizar todas las extensiones
php artisan extensions:import --pbx=1

# Sincronizar una extensión específica
php artisan extensions:import 1001 --pbx=1
```

Este comando:
- Obtiene la lista de extensiones configuradas en la central
- Sincroniza nombres, estados y configuraciones
- Actualiza la información de forma inteligente (solo si hay cambios)
- Asocia las extensiones a la central especificada

### Sincronización desde la Web

También puedes sincronizar directamente desde la interfaz web:

1. Ir a **Gestión de Centrales PBX**
2. Seleccionar una central sin datos
3. Se mostrará la página de **Configuración Inicial**
4. Marcar qué sincronizar (extensiones, llamadas, año)
5. Click en **Iniciar Sincronización**

> El sistema incluye protección contra sincronizaciones simultáneas. Si otro usuario ya está sincronizando, verás un mensaje de espera.


##  Ejecutar con XAMPP

### 1. Iniciar servicios de XAMPP

Abre el **Panel de Control de XAMPP** e inicia:
-  **Apache**
-  **MySQL**

### 2. Configurar APP_URL (OBLIGATORIO)

Edita el archivo `.env` y configura `APP_URL` según cómo vas a acceder:

```env
# Si solo usas localhost:
APP_URL=http://localhost/panel-gestion-llamadas

# Si accedes desde otra PC en la red (cambia la IP por la de tu servidor):
APP_URL=http://10.61.17.92/panel-gestion-llamadas
```

>  **Importante:** Sin esta configuración, la sincronización fallará con error 404.

### 3. Limpiar caché después de cambiar .env

```bash
php artisan config:clear
php artisan cache:clear
php artisan route:clear
```

### 4. Acceder a la aplicación

- **Mismo PC:** `http://localhost/panel-gestion-llamadas`
- **Otra PC en red:** `http://[IP-DEL-SERVIDOR]/panel-gestion-llamadas`

> Gracias al archivo `.htaccess` en la raíz, ya no necesitas agregar `/public` a la URL.

### 5. Configurar Virtual Host (Opcional)

Para acceder mediante un dominio personalizado (sin subdirectorio), edita el archivo `httpd-vhosts.conf`:

**Windows:** `C:\xampp\apache\conf\extra\httpd-vhosts.conf`

```apache
<VirtualHost *:80>
    DocumentRoot "C:/xampp/htdocs/panel-gestion-llamadas/public"
    ServerName panel-llamadas.local
    <Directory "C:/xampp/htdocs/panel-gestion-llamadas/public">
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

Si usas Virtual Host, actualiza `.env`:
```env
APP_URL=http://panel-llamadas.local
```

### 6. Compilar assets en desarrollo (opcional)

Si necesitas modificar estilos o JavaScript:

```bash
npm run dev
```

---

##  Estructura del Proyecto

```
├── app/
│   ├── Console/Commands/           # Comandos Artisan personalizados
│   │   ├── SyncCalls.php           # Sincronización de CDRs
│   │   ├── ImportarExtensiones.php # Sincronización de extensiones
│   │   ├── SyncQueueStats.php      # Sincronización de estadísticas de colas
│   │   └── TestApiCommands.php     # Testing interactivo de la API Grandstream
│   ├── Exports/                    # Exportaciones Excel
│   │   └── CallsExport.php        # Exportación de llamadas a Excel
│   ├── Http/Controllers/           # Controladores
│   │   ├── CdrController.php       # Dashboard, reportes, sincronización CDR
│   │   ├── ExtensionController.php # Gestión de extensiones y desvíos
│   │   ├── PbxConnectionController.php # Gestión multi-central
│   │   ├── UserController.php      # CRUD de usuarios + API modal
│   │   ├── StatsController.php     # KPIs de colas de llamadas
│   │   ├── SettingController.php   # Tarifas de llamadas
│   │   ├── IPController.php        # Monitor de IPs en tiempo real
│   │   ├── AuthController.php      # Login/Logout personalizado
│   │   └── EstadoCentral.php       # Uptime de la central
│   ├── Models/                     # Modelos Eloquent
│   │   ├── Call.php                # Llamadas (CDR) — con Global Scope por PBX
│   │   ├── Extension.php           # Extensiones — con Global Scope por PBX
│   │   ├── PbxConnection.php       # Centrales PBX (relación N:M con User)
│   │   ├── QueueCallDetail.php     # Detalles de colas — con Global Scope por PBX
│   │   ├── User.php                # Usuarios (relación N:M con PbxConnection)
│   │   └── Setting.php             # Configuración clave-valor (tarifas)
│   ├── Services/                   # Servicios
│   │   ├── GrandstreamService.php  # Conexión con API Grandstream UCM
│   │   └── CallBillingAnalyzer.php # Análisis de facturación de llamadas
│   └── Traits/                     # Traits reutilizables
│       └── GrandstreamTrait.php    # Wrapper del servicio Grandstream
├── database/
│   ├── migrations/                 # Migraciones de BD
│   │   └── ...                     # ~20 migraciones incluyendo pivot table
│   └── seeders/                    # Seeders de datos iniciales
│       ├── UserSeeder.php          # Crea admin + usuario desde .env
│       ├── SettingSeeder.php       # Tarifas por defecto
│       └── PbxConnectionSeeder.php # Central de ejemplo
├── resources/views/                # Vistas Blade
│   ├── layouts/                    # Layouts principales (app, guest, sidebar)
│   ├── pbx/                        # Selector de centrales + gestión usuarios (modal)
│   │   ├── index.blade.php         # Lista centrales + modal usuarios (Alpine.js)
│   │   └── setup.blade.php         # Configuración inicial / sincronización
│   ├── users/                      # Vistas standalone de usuarios (CRUD)
│   ├── stats/                      # KPIs de colas
│   └── ...                         # Dashboard, configuración, gráficos, etc.
└── routes/
    └── web.php                     # Todas las rutas de la aplicación
```

---

##  Sistema Multi-Central

El panel soporta la gestión de **múltiples centrales PBX** desde una sola instalación:

### Flujo de Uso

1. **Login** → Se muestra la lista de centrales disponibles
2. **Filtrado por acceso** → Cada usuario solo ve las centrales que el admin le asignó
3. **Seleccionar Central** → Si tiene datos, va al Dashboard
4. **Central sin datos** → Se muestra página de Configuración Inicial (solo admin)
5. **Sincronizar** → Importa extensiones y/o llamadas desde la API Grandstream
6. **Dashboard** → Trabaja con los datos de esa central

### Control de Acceso por Central

El sistema controla qué centrales puede ver cada usuario mediante una **tabla pivot** (`pbx_connection_user`):

- **Administradores**: Ven TODAS las centrales automáticamente (no necesitan asignación)
- **Supervisores/Usuarios**: Solo ven las centrales que el admin les asigne
- **Asignación**: Se realiza desde el modal de gestión de usuarios en la página de centrales
- Al crear o editar un usuario, el admin puede marcar con checkboxes las centrales permitidas
- Cada central se muestra con su **nombre** e **IP** para fácil identificación

### Características

- Cada central tiene sus propios datos de llamadas y extensiones
- Los datos se filtran automáticamente por la central activa (Global Scopes)
- Puedes cambiar de central en cualquier momento
- Las credenciales de cada central se almacenan de forma segura (encriptadas)
- La selección de central verifica autorización del usuario antes de permitir acceso

### Agregar Nueva Central

1. Ir a **Gestión de Centrales PBX** (`/pbx`)
2. Click en **Agregar Central** (solo admin)
3. Completar: Nombre, IP, Puerto, Usuario, Contraseña
4. Guardar y Seleccionar

---

##  Gestión de Usuarios

Los usuarios se gestionan desde la **página de centrales PBX** (`/pbx`) a través de un modal interactivo.

### Roles

| Rol | Descripción |
|-----|-------------|
| **Admin** | Acceso total. Ve todas las centrales. Puede crear/editar usuarios y centrales |
| **Supervisor** | Permisos intermedios configurables. Solo ve centrales asignadas |
| **Usuario** | Permisos básicos configurables. Solo ve centrales asignadas |

### Permisos Granulares

Cada usuario (que no sea admin) tiene permisos individuales:

| Permiso | Descripción |
|---------|-------------|
| Sincronizar Llamadas | Ejecutar sincronización de CDRs desde la central |
| Editar Extensiones | Modificar datos de extensiones en la central |
| Actualizar IPs | Actualizar las IPs de los anexos |
| Gestionar Centrales PBX | Crear, editar y eliminar centrales |
| Editar Tarifas | Modificar precios por minuto |
| Exportar PDF | Descargar reportes en PDF |
| Exportar Excel | Descargar reportes en Excel |
| Ver Gráficos | Acceder a la sección de estadísticas |

> Los administradores tienen **todos los permisos** automáticamente.

### Asignación de Centrales

Al crear o editar un usuario, el admin puede:
- Marcar/desmarcar centrales individuales con checkboxes
- Usar "Seleccionar Todas" o "Ninguna" para asignación rápida
- Ver el nombre y la IP de cada central para identificarla fácilmente

---

##  Seguridad

- Las credenciales sensibles se manejan exclusivamente mediante variables de entorno
- El archivo `.env` está excluido del repositorio (`.gitignore`)
- Las contraseñas de usuario se almacenan con hash Bcrypt
- Las contraseñas de las centrales PBX se encriptan en la base de datos (Laravel `encrypted` cast)
- Protección CSRF en todos los formularios
- Sistema de locks para prevenir sincronizaciones simultáneas
- **Control de acceso por central**: Los usuarios solo pueden acceder a las centrales que el admin les asigne
- **Verificación de autorización**: Al seleccionar una central, el sistema verifica que el usuario tenga permiso
- Middleware `admin` para proteger rutas administrativas
- Middleware `pbx.selected` para asegurar que haya una central activa en sesión

---


