# PROYECTO KUMITECONTROL

## A. Descripción

KumiteControl es una aplicación web para gestionar campeonatos de artes marciales (kumite): eventos, categorías, inscripciones, cuadros de eliminación y resultados.

El código Laravel está en `web/`. Detalle funcional en [`docs/README.md`](docs/README.md).

## B. Stack tecnológico utilizado

**Stack:** Laravel 13, PHP 8.4, MySQL, Blade, Bootstrap 5.3, Vite, JavaScript, Composer, npm.

**Entorno y herramientas:** MAMP PRO (local), CDmon (producción: SSH/FTP), GitHub, Cursor/VSCode, Sublime Merge, Transmit/Ferry, asistentes IA (Claude, OpenCode).

## C. Información sobre su instalación y ejecución

Desde `web/`:

```bash
source scripts/dev-env.sh
composer install
cp .env.example .env
php artisan key:generate
npm install
# Configurar MySQL en .env (APP_URL, DB_*)
php artisan migrate
npm run dev
```

En MAMP PRO, el DocumentRoot debe apuntar a `web/public`.

Comandos diarios y deploy: [`web/README.md`](web/README.md). Preparación local ampliada: [`docs/README.md`](docs/README.md).

## D. Estructura del proyecto

```
kumitecontrol/
├── web/                         # Aplicación Laravel
│   ├── app/
│   │   ├── Enums/               # Estados y reglas de dominio
│   │   ├── Http/                # Controllers, Middleware, Requests
│   │   ├── Models/              # Eloquent (Event, Category, Bout…)
│   │   ├── Tournament/          # Cuadros, puntuación, podio, tatamis
│   │   ├── Registrations/       # Lógica de inscripciones
│   │   ├── Policies/            # Autorización por rol
│   │   ├── Support/             # Ayuda, CSV y utilidades
│   │   └── Notifications/       # Notificaciones
│   ├── database/                # Migrations, seeders, factories
│   ├── resources/
│   │   ├── views/
│   │   │   ├── public/          # Portada, eventos y resultados públicos
│   │   │   ├── panel/           # Panel (eventos, cuadros, tatamis…)
│   │   │   ├── auth/            # Login, registro, contraseña
│   │   │   ├── layouts/         # Plantillas base
│   │   │   └── pdf/             # Plantillas PDF del cuadro
│   │   ├── css/                 # Estilos (Vite)
│   │   └── js/                  # JavaScript (Vite)
│   ├── routes/web.php           # Rutas HTTP
│   ├── public/                  # DocumentRoot (MAMP / producción)
│   ├── tests/                   # Feature y Unit
│   ├── lang/es/                 # Traducciones
│   ├── deploy/                  # Deploy a CDmon
│   └── scripts/                 # Entorno local (dev-env.sh)
├── docs/                        # Especificación y decisiones
├── assets/                      # Recursos gráficos
└── README.md                    # Este documento
```

## E. Funcionalidades principales

- Roles: Organizador, Club, Controlador de tatami; el visitante consulta lo publicado.
- Entidades: Evento, Categoría, Competidor, Inscripción, Combate.
- Portada pública con eventos pasados, en curso y futuros (con inscripción si aplica).
- Cada club gestiona su plantel e inscribe competidores en los eventos en los que está aprobado.
- Generación de cuadros de eliminación según las reglas de la categoría.
- El público ve resultados en directo con un código de acceso del organizador.
- Los controladores de tatami anotan la puntuación y el resultado de cada combate.
