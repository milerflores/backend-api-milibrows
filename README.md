# MiliBrows Studio — Backend API

API REST monolítica modular desarrollada en **Laravel 13**, **PHP 8.3+** y **MySQL 8+ (InnoDB)** para atender el portal web de clientes, el panel administrativo y la aplicación móvil de **MiliBrows Studio**.

## Características principales

- **Identidad y acceso (`/api/v1/acceso`)**: registro con vinculación de ficha libre por DNI, sesiones web con cookies Sanctum + CSRF, tokens Bearer para app móvil (`cliente:movil`), recuperación de contraseña y autenticación multifactor (TOTP + códigos de recuperación, obligatoria para Administración).
- **Agenda multi-especialista (`/api/v1/citas`, `/api/v1/disponibilidad`)**: reservas con uno o varios servicios y distintas estilistas por servicio, validación transaccional de horarios, reprogramación, estados individuales (`pendiente`, `en_proceso`, `realizado`, `cancelado`, `no_realizado`), notas internas e historial de revisiones.
- **Cobros y descuentos (`/api/v1/caja`)**: cobros directos totales o parciales (Efectivo, Yape, Plin, Tarjeta, Transferencia), anulación con reversión/reajuste de puntos, descuentos automáticos por nivel, descuentos manuales por servicio y reportes de ingresos.
- **Fidelización por puntos (`/api/v1/fidelizacion`)**: acumulación de puntos sobre importes efectivamente pagados (`monto_por_punto` configurable), niveles **Glow**, **Golden** y **VIP**, ledger inmutable de movimientos (`ganado`, `reversion`, `ajuste`) y motivos de descuento.
- **Inventario (`/api/v1/inventario`)**: control en unidades enteras, alertas de reposición, movimientos (`entrada`, `salida`, `devolucion`, `ajuste`), entregas a estilistas y registro de agotamiento.
- **Notificaciones**: envío de confirmaciones y recordatorios por correo en un único intento (sin reintentos automáticos), invalidados automáticamente al cancelar o reprogramar.

## Requisitos previos

- **PHP** >= 8.3 (con extensiones `pdo_mysql`, `mbstring`, `openssl`, `fileinfo`, `gd`)
- **Composer** >= 2.x
- **MySQL** >= 8.0 (motor InnoDB, soporte para `CHECK` constraints y triggers)
- **Node.js** >= 20 (opcional, solo para regenerar el contrato OpenAPI con `scripts/generar-openapi.cjs`)

## Instalación y puesta en marcha

1. **Clonar el repositorio e instalar dependencias**
   ```bash
   composer install
   ```

2. **Configurar variables de entorno**
   ```bash
   cp .env.example .env
   php artisan key:generate
   ```
   Edita `.env` con tus credenciales de MySQL y verifica las URLs del frontend:
   ```dotenv
   DB_CONNECTION=mysql
   DB_HOST=127.0.0.1
   DB_PORT=3306
   DB_DATABASE=milibrows
   DB_USERNAME=root
   DB_PASSWORD=
   DB_TIMEZONE=-05:00

   FRONTEND_URL=http://localhost:5173
   SANCTUM_STATEFUL_DOMAINS=localhost,localhost:5173,localhost:5174,localhost:8000
   CORS_ALLOWED_ORIGINS=http://localhost:5173,http://localhost:5174
   ```

3. **Crear la base de datos y ejecutar migraciones**
   Crea la base `milibrows` en MySQL (`utf8mb4_unicode_ci`) y ejecuta:
   ```bash
   php artisan migrate
   php artisan storage:link
   ```

4. **Crear la cuenta administrativa inicial**
   ```bash
   php artisan milibrows:crear-admin admin@milibrows.com "Nombre" "ApellidoPaterno" "ApellidoMaterno"
   ```
   *(El comando solicitará la contraseña de forma interactiva: mínimo 10 caracteres con mayúsculas, minúsculas y números).*

5. **Opcional — Cargar datos DEMO (solo en entorno `local`)**
   ```bash
   php artisan db:seed --class=DatosPruebaSeeder
   ```
   Las credenciales generadas se guardan localmente en `storage/app/private/datos-prueba.json` (excluido de Git).

## Ejecutar en desarrollo

```bash
php artisan serve --port=8000
```

Para procesar correos de confirmación y recordatorios programados en segundo plano:
```bash
php artisan queue:work
php artisan schedule:work
```

## Pruebas automatizadas

Las pruebas ejecutan migraciones y concurrencia real contra una base MySQL independiente con sufijo `_pruebas` (por ejemplo, `milibrows_pruebas`) para no alterar los datos de desarrollo:

```powershell
# PowerShell
$env:DB_DATABASE="milibrows_pruebas"
php artisan test
```

```bash
# Bash
DB_DATABASE=milibrows_pruebas php artisan test
```

## Contrato OpenAPI

El contrato vigente (**v2.0.0**, 58 rutas / 77 operaciones bajo `/api/v1`) se encuentra en [`docs/openapi.yaml`](docs/openapi.yaml). Para regenerarlo tras cambios en rutas o esquemas:

```bash
node scripts/generar-openapi.cjs
```

## Estructura del proyecto

```text
app/
├── Console/Commands/    # Comando milibrows:crear-admin
├── Http/Middleware/     # AccesoHabilitado (MFA, roles, versión de sesión), RolRequerido, SoloSesionWeb
├── Models/              # Modelos Eloquent alineados al esquema físico en español
├── Modules/
│   ├── Acceso/          # Login, registro, MFA, recuperación y tokens móviles
│   ├── Administracion/  # Catálogo, configuración del estudio, horarios y resumen
│   ├── Agenda/          # Disponibilidad, reservas multi-estilista, reprogramación y descuentos manuales
│   ├── Avisos/          # Confirmaciones y recordatorios por correo
│   ├── Caja/            # Cobros parciales, anulaciones, métodos de pago e ingresos
│   ├── Clientes/        # Fichas, perfil, historial, pagos y fidelización
│   ├── Fidelizacion/    # Niveles, puntos, ajustes y motivos de descuento
│   ├── Inventario/      # Insumos, movimientos en unidades enteras y asignaciones
│   └── Personal/        # Empleados, cuentas, jornadas y servicios habilitados
├── Policies/            # CitaPolicy
└── Support/             # Helpers de paginación y conversión monetaria
database/
├── cambios/             # Scripts SQL de integridad, triggers y procedimientos
├── migrations/          # Migraciones ordenadas (incluye Yape y Plin)
├── modelo/              # Esquema físico exportado (DBML, JSON, SQL)
└── seeders/             # DatabaseSeeder y DatosPruebaSeeder
```
