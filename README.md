#  Sistema de Gestión de Centrales Telefónicas (SGCT) - Grandstream UCM

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
-  **Gestión de extensiones** con sincronización bajo demanda y actualización de IPs
-  **Desvíos de llamadas** — Configura Call Forwarding directamente desde la interfaz
-  **Gráficos y reportes** de llamadas entrantes, salientes y perdidas
-  **Estadísticas de colas (KPI)** — Volumen, abandono, ASA, rendimiento de agentes, alertas automáticas por umbrales
-  **Desvíos de llamadas** — Configura Call Forwarding (CFU, CFB, CFN) directamente desde la interfaz
-  **Exportación a Excel/PDF** de reportes personalizados
-  **Gestión de usuarios** con roles (Admin, Supervisor, Usuario) y permisos granulares
-  **Tarifas configurables** — Precios por minuto según destino (celular, nacional, internacional)
-  **Facturación automática** — Clasifica llamadas por tipo de destino chileno y calcula costos en CLP
-  **Interfaz moderna** con Tailwind CSS y Alpine.js
-  **Protección contra sincronizaciones simultáneas** mediante sistema de locks
-  **Job en background** para sincronización asíncrona de datos PBX

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

### Dependencias Principales (Composer)

| Paquete | Versión | Propósito |
|---------|---------|-----------|
| `laravel/framework` | ^11.0 | Framework principal |
| `laravel/breeze` | ^2.3 | Scaffolding de autenticación |
| `barryvdh/laravel-dompdf` | ^3.1 | Generación de PDFs server-side |
| `maatwebsite/excel` | ^3.1 | Export Excel con streaming (`FromQuery`) |

### Dependencias Principales (NPM)

| Paquete | Versión | Propósito |
|---------|---------|-----------|
| `alpinejs` | ^3.4.2 | Reactividad del lado del cliente |
| `tailwindcss` | ^3.1.0 | Framework CSS utility-first |
| `axios` | ^1.11.0 | Cliente HTTP con CSRF automático |
| `chart.js` | 4.4.1 (CDN) | Gráficos interactivos (pie, line, bar, area) |
| `font-awesome` | 6.5.2 (CDN) | Iconografía |

---

##  Instalación (recomendado usar CMD)

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
copy .env.example .env
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

### Crear tarifas por defecto

```bash
php artisan db:seed --class=SettingSeeder
```

Crea las tarifas iniciales: celular ($80 CLP/min), nacional ($40 CLP/min), internacional ($500 CLP/min).

### Ejecutar todos los seeders

```bash
php artisan db:seed
```

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
# Sincronizar todas las extensiones (modo completo con detalles SIP)
php artisan extensions:import --pbx=1

# Sincronizar en modo rápido (solo datos básicos, sin llamadas a getSIPAccount)
php artisan extensions:import --pbx=1 --quick

# Sincronizar una extensión específica (siempre modo completo)
php artisan extensions:import 1001 --pbx=1
```

Opciones disponibles:
| Opción | Descripción |
|--------|-------------|
| `--pbx=ID` | **Obligatorio.** ID de la central PBX |
| `--quick` | Modo rápido: solo datos básicos sin detalles SIP (~50 ext/seg vs ~5 ext/seg) |
| `target` | Extensión específica a sincronizar (ej: `1001`). Opcional |

Este comando:
- Obtiene la lista de extensiones configuradas en la central (`listUser`)
- Sincroniza nombres, estados y configuraciones
- En modo completo, consulta `getSIPAccount` por cada extensión para obtener DND, permisos, contraseña SIP y max_contacts
- Actualiza la información de forma inteligente (solo si hay cambios con `hasChanges()`)
- Asocia las extensiones a la central especificada
- Procesa en chunks de 50 con `gc_collect_cycles()` para controlar memoria

### Sincronizar Estadísticas de Colas

```bash
# Sincronizar colas de los últimos 7 días
php artisan sync:queue-stats --pbx=1

# Sincronizar una cola específica
php artisan sync:queue-stats --pbx=1 --queue=6500

# Sincronizar un rango de fechas específico
php artisan sync:queue-stats --pbx=1 --start-date=2026-01-01 --end-date=2026-01-31

# Sincronizar los últimos 30 días
php artisan sync:queue-stats --pbx=1 --days=30

# Forzar resincronización (elimina datos existentes del período)
php artisan sync:queue-stats --pbx=1 --force
```

Opciones disponibles:
| Opción | Descripción |
|--------|-------------|
| `--pbx=ID` | **Obligatorio.** ID de la central PBX |
| `--queue=XXXX` | Cola específica a sincronizar (ej: `6500`). Si no se indica, sincroniza todas |
| `--days=N` | Días hacia atrás a sincronizar (default: `7`) |
| `--start-date=YYYY-MM-DD` | Fecha inicio (sobreescribe `--days`) |
| `--end-date=YYYY-MM-DD` | Fecha fin (default: hoy) |
| `--force` | Elimina los datos existentes del período antes de resincronizar |

Este comando:
- Consulta el endpoint `queueapi` de la central Grandstream
- Descarga el detalle de llamadas por cola (caller, agente, tiempos de espera/conversación)
- Implementa deduplicación en 3 capas: batch en memoria, verificación en BD, y constraint catch
- Filtra ~22% de registros duplicados que la API Grandstream retorna

### Herramienta de Testing de API

```bash
# Menú interactivo de acciones disponibles
php artisan api:test --pbx=1

# Probar un endpoint específico
php artisan api:test --pbx=1 --action=cdrapi --days=7

# Probar cuenta SIP de una extensión
php artisan api:test --pbx=1 --action=getSIPAccount --extension=1001

# Probar colas
php artisan api:test --pbx=1 --action=queueapi --queue=6500 --stats-type=calldetail
```

> **Nota:** Este comando (~4200 líneas) es una herramienta de desarrollo/debugging, no de producción. Contiene 16+ subcomandos para inspeccionar todos los endpoints de la PBX Grandstream.

### Sincronización desde la Web

También puedes sincronizar directamente desde la interfaz web:

**Configuración Inicial (Setup):**
1. Ir a **Gestión de Centrales PBX**
2. Seleccionar una central sin datos
3. Se mostrará la página de **Configuración Inicial**
4. Marcar qué sincronizar (extensiones, llamadas, año)
5. Click en **Iniciar Sincronización**

**Sincronización rápida desde cada sección:**
- **Llamadas:** Botón "Sincronizar Ahora" en el Dashboard de llamadas (requiere permiso `can_sync_calls`)
- **Anexos:** Botón "Sincronizar Ahora" en la página de Configuración de Anexos (requiere permiso `can_sync_calls`). Se ejecuta via AJAX — la página muestra el progreso en tiempo real mientras sincroniza extensión por extensión. El sidebar muestra un indicador de progreso en el enlace "Anexos".

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
│   ├── Console/Commands/              # Comandos Artisan personalizados
│   │   ├── Concerns/
│   │   │   └── ConfiguresPbx.php      # Trait compartido: resolución de central PBX en CLI
│   │   ├── SyncCalls.php              # Sincronización de CDRs (calls:sync)
│   │   ├── ImportarExtensiones.php    # Sincronización de extensiones (extensions:import)
│   │   ├── SyncQueueStats.php         # Sincronización de estadísticas de colas (sync:queue-stats)
│   │   └── TestApiCommands.php        # Testing interactivo de la API Grandstream (api:test)
│   ├── Exports/                       # Exportaciones Excel
│   │   └── CallsExport.php           # Exportación de llamadas a Excel (FromQuery + streaming)
│   ├── Http/
│   │   ├── Controllers/               # Controladores
│   │   │   ├── Concerns/
│   │   │   │   └── ProcessesCdr.php   # Trait: consolidación de segmentos CDR
│   │   │   ├── Auth/                  # Controladores de autenticación (Breeze)
│   │   │   ├── AuthController.php     # Login/Logout personalizado
│   │   │   ├── CdrController.php      # Dashboard, reportes, sincronización CDR
│   │   │   ├── EstadoCentral.php      # Estado/uptime de la central
│   │   │   ├── ExtensionController.php# Gestión de extensiones y desvíos
│   │   │   ├── IPController.php       # Monitor de IPs en tiempo real
│   │   │   ├── PbxConnectionController.php # Gestión multi-central + wizard sync
│   │   │   ├── ProfileController.php  # Perfil de usuario (Breeze)
│   │   │   ├── SettingController.php  # Tarifas de llamadas
│   │   │   ├── StatsController.php    # KPIs de colas de llamadas
│   │   │   └── UserController.php     # CRUD de usuarios + API JSON para modal
│   │   ├── Middleware/                # Middleware personalizados
│   │   │   ├── AdminMiddleware.php    # Bloquea no-admin (alias: admin)
│   │   │   └── CheckPbxSelected.php   # Requiere central seleccionada (alias: pbx.selected)
│   │   └── Requests/
│   │       └── ProfileUpdateRequest.php
│   ├── Jobs/
│   │   └── SyncPbxDataJob.php         # Job background para sincronización (1h timeout)
│   ├── Models/                        # Modelos Eloquent
│   │   ├── Call.php                   # Llamadas (CDR) — con Global Scope por PBX
│   │   ├── Extension.php             # Extensiones — con Global Scope por PBX
│   │   ├── PbxConnection.php         # Centrales PBX (modelo central multi-tenant)
│   │   ├── QueueCallDetail.php       # Detalles de colas — con Global Scope por PBX
│   │   ├── Setting.php               # Configuración clave-valor (tarifas)
│   │   └── User.php                  # Usuarios con roles y 12 permisos booleanos
│   ├── Providers/
│   │   └── AppServiceProvider.php     # Binding dinámico de GrandstreamService
│   ├── Services/                      # Servicios
│   │   ├── GrandstreamService.php    # Cliente API Grandstream (challenge/login/cookie)
│   │   └── CallBillingAnalyzer.php   # Clasificador de llamadas facturables (5 criterios)
│   ├── Traits/                        # Traits reutilizables
│   │   └── GrandstreamTrait.php      # Wrapper del servicio para controladores
│   └── View/Components/
│       ├── AppLayout.php             # Layout autenticado
│       └── GuestLayout.php           # Layout invitados
├── database/
│   ├── migrations/                    # Migraciones de BD (~22 migraciones)
│   │   ├── create_users_table.php
│   │   ├── create_calls_table.php
│   │   ├── create_extensions_table.php
│   │   ├── create_settings_table.php
│   │   ├── create_pbx_connections_table.php
│   │   ├── create_queue_call_details_table.php
│   │   ├── create_pbx_connection_user_table.php  # Tabla pivot N:M
│   │   └── ...                        # + migraciones de campos adicionales
│   └── seeders/                       # Seeders de datos iniciales
│       ├── DatabaseSeeder.php         # Seeder principal
│       ├── UserSeeder.php             # Crea admin + usuario desde .env
│       ├── SettingSeeder.php          # Tarifas por defecto (80/40/500 CLP)
│       └── PbxConnectionSeeder.php    # Central de ejemplo
├── resources/views/                   # Vistas Blade
│   ├── layouts/                       # Layouts principales
│   │   ├── app.blade.php             # Layout autenticado (con sidebar + sync indicator)
│   │   ├── guest.blade.php           # Layout para invitados
│   │   ├── sidebar.blade.php         # Sidebar fijo lateral izquierdo
│   │   └── navigation.blade.php      # Navbar Breeze (no usado activamente)
│   ├── pbx/                           # Gestión de centrales PBX
│   │   ├── index.blade.php           # Lista centrales + modal usuarios (Alpine.js)
│   │   └── setup.blade.php           # Wizard de configuración inicial / sincronización
│   ├── users/                         # Vistas standalone de usuarios (CRUD)
│   │   ├── index.blade.php           # Listado de usuarios
│   │   ├── create.blade.php          # Crear usuario + permisos
│   │   └── edit.blade.php            # Editar usuario + permisos
│   ├── stats/                         # Estadísticas de colas
│   │   └── kpi-turnos.blade.php      # Dashboard KPI de colas (Chart.js)
│   ├── settings/
│   │   └── index.blade.php           # Configuración de tarifas
│   ├── auth/                          # Vistas de autenticación (Breeze)
│   ├── errors/
│   │   └── 419.blade.php             # Redirige a login (sesión expirada)
│   ├── reporte.blade.php             # Vista principal: tabla de llamadas + KPIs
│   ├── graficos.blade.php            # Gráficos de llamadas (pie + línea)
│   ├── configuracion.blade.php       # Gestión de extensiones/anexos
│   ├── login.blade.php               # Login personalizado (standalone)
│   ├── pdf_reporte.blade.php         # Template para exportación PDF (DomPDF)
│   
└── routes/
    ├── web.php                        # Rutas principales de la aplicación
    └── auth.php                       # Rutas de autenticación (Breeze)
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

| Permiso | Campo BD | Descripción |
|---------|----------|-------------|
| Sincronizar Llamadas | `can_sync_calls` | Ejecutar sincronización de CDRs desde la central |
| Sincronizar Extensiones | `can_sync_extensions` | Ejecutar sincronización de extensiones/anexos |
| Sincronizar Colas | `can_sync_queues` | Ejecutar sincronización de estadísticas de colas |
| Editar Extensiones | `can_edit_extensions` | Modificar datos de extensiones y desvíos en la central |
| Actualizar IPs | `can_update_ips` | Actualizar las IPs de los anexos |
| Editar Tarifas | `can_edit_rates` | Modificar precios por minuto |
| Gestionar Centrales PBX | `can_manage_pbx` | Crear, editar y eliminar centrales |
| Exportar PDF | `can_export_pdf` | Descargar reportes en PDF |
| Exportar Excel | `can_export_excel` | Descargar reportes en Excel |
| Ver Gráficos | `can_view_charts` | Acceder a gráficos y KPIs de colas |
| Ver Extensiones | `can_view_extensions` | Acceder a la sección de configuración de anexos |
| Ver Tarifas | `can_view_rates` | Acceder a la sección de tarifas |

> Los administradores tienen **todos los permisos** automáticamente (`hasPermission()` siempre retorna `true`).

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
- Las contraseñas SIP (`secret`) están en `$hidden` para serialización JSON
- Protección CSRF en todos los formularios (token meta tag + headers Axios/Fetch)
- Sistema de locks para prevenir sincronizaciones simultáneas
- **Control de acceso por central**: Los usuarios solo pueden acceder a las centrales que el admin les asigne
- **Verificación de autorización**: Al seleccionar una central, el sistema verifica que el usuario tenga permiso (`canAccessPbx()`)
- Middleware `admin` para proteger rutas administrativas
- Middleware `pbx.selected` para asegurar que haya una central activa en sesión
- **Global Scopes automáticos**: Filtro `WHERE pbx_connection_id = X` en Call, Extension y QueueCallDetail
- **Rate limiting**: `throttle:10,1` — máximo 10 intentos de login por minuto por IP
- **Regeneración de sesión**: Token regenerado en login/logout
- **SSL configurable** por conexión PBX (`verify_ssl`)
- **Protección SQL injection**: Whitelist en ORDER BY con `validateSort()`

---


