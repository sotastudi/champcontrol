# ChampControl

Aplicación web ligera para organizar campeonatos de artes marciales: crear eventos y categorías, inscribir competidores, generar cuadros de eliminación, anotar combates y publicar clasificaciones.

**Estado:** primera versión implementada en `web/`. Las funciones descritas abajo están implementadas, salvo las decisiones pendientes de confirmar: ver [docs/decisiones-pendientes.md](docs/decisiones-pendientes.md) (la regla del bronce está aprobada; las retiradas y descalificaciones están implementadas, con deshacer baja y sanciones pendientes) y [docs/menores.md](docs/menores.md) (inscripción de menores).

## Objetivo y alcance

ChampControl está pensado para organizadores de campeonatos pequeños o medianos que necesitan sustituir hojas de cálculo y cuadros preparados a mano. Cada organizador gestiona sus propios eventos desde una cuenta; el público consulta la información publicada mediante enlaces del evento.

La primera versión cubre un flujo completo: **crear evento → definir categorías → registrar participantes → cerrar inscripciones → generar cuadros → registrar resultados → publicar podios**. El diseño debe funcionar en ordenador y móvil, con especial atención a las pantallas usadas durante el campeonato.

### Funciones de la primera versión

- **Cuentas de organizador:** registro, acceso, cierre de sesión, recuperación de contraseña y panel con los eventos propios. Cada evento pertenece a un organizador; no se puede acceder a los datos privados de otro.
- **Eventos:** nombre, disciplina, lugar, fecha, descripción breve y estados `borrador`, `inscripciones abiertas`, `inscripciones cerradas`, `en curso` y `finalizado`. Página pública con la información que el organizador haya publicado.
- **Categorías:** nombre y criterios configurados por el organizador, por ejemplo modalidad, edad, peso, sexo o nivel. En esta versión el organizador asigna o valida la categoría; no se necesita un motor universal para todas las federaciones. La edad, si se usa, se calcula en la fecha del evento.
- **Competidores e inscripciones:** ficha básica (nombre, apellidos, club/equipo y datos necesarios para la categoría), alta manual por el organizador y formulario público opcional por evento. Una inscripción vincula un competidor con una categoría de un evento y pasa por `pendiente`, `confirmada` o `cancelada`. Se impiden duplicados de un mismo competidor en la misma categoría.
- **Listas:** inscritos por evento/categoría, pendientes, orden de combates y resultados; filtros, impresión desde el navegador y exportación CSV de inscritos y clasificaciones.
- **Cuadros o diagramas:** eliminación directa por categoría, con emparejamiento aleatorio inicial, posibilidad de ajustar emparejamientos antes de empezar, rondas, pases automáticos (*byes*) y vista imprimible. El organizador confirma la generación; si cambian las inscripciones, puede regenerar el cuadro mientras no haya resultados.
- **Combates:** número/orden, ronda, dos participantes y ganador. Se registran resultados uno a uno; el vencedor avanza automáticamente al siguiente combate. Una corrección exige editar el combate afectado y revisar los resultados dependientes antes de continuar.
- **Clasificación y podio:** puestos 1.º, 2.º y 3.º; opción de mostrar también el 4.º. Para distinguir 3.º y 4.º se disputa un combate por el bronce entre quienes perdieron las semifinales. Si se elige un podio de tres puestos, se muestra solo el ganador de ese combate. Esta regla está **aprobada**; por categoría puede elegirse también la alternativa de dos terceros sin combate.
- **Retiradas y descalificaciones:** con el campeonato en marcha (ya hay resultados), el organizador registra una baja con motivo privado. Los combates pendientes del participante se dan por ganados al rival por incomparecencia. El retirado conserva su puesto; el descalificado sale del podio y del CSV y el puesto queda vacante para resolver según el reglamento.
- **Reglas del campeonato por evento:** en el formulario del evento se eligen cuatro reglas dudosas (puesto vacante o no del descalificado, puesto del retirado que no llegó a combatir, regla del bronce por defecto y si se admite la baja antes de los resultados). Los valores por defecto reproducen el comportamiento descrito arriba. Ver [docs/decisiones-pendientes.md](docs/decisiones-pendientes.md).
- **Publicación:** página pública de categorías, cuadros y resultados, controlada por el organizador. Un evento puede permanecer privado mientras se prepara.

### Fuera de la primera versión

Pagos de inscripción, marcadores en directo, tatamis simultáneos, calendarios automáticos, rankings, clasificación por equipos, cabezas de serie, liguillas, doble eliminación, reglas de puntuación específicas de cada disciplina, aplicación móvil nativa y permisos para equipos de organizadores. Se podrán añadir tras validar el flujo básico con un campeonato real.

## Reglas de funcionamiento

1. Solo las inscripciones **confirmadas** entran en el cuadro; al generarlo, se guarda una versión estable de los participantes y emparejamientos.
2. Una categoría con un solo inscrito no genera combates. Su clasificación se revisa manualmente según el reglamento; no se le asigna un oro automáticamente. Con dos inscritos se genera una final. Con más inscritos se completan las rondas con pases automáticos cuando sea necesario.
3. El sorteo solo se modifica antes de anotar el primer resultado. Las bajas posteriores y sus efectos se resuelven manualmente por el organizador en la primera versión.
4. No se permite registrar el ganador de un combate hasta conocer ambos participantes, excepto el avance automático por un pase.
5. El sistema calcula la clasificación a partir de resultados validados; al finalizar el evento, una corrección requiere reabrirlo y revisar las clasificaciones afectadas.
6. Las páginas públicas muestran únicamente los datos necesarios: nombre deportivo, club, categoría, cuadro y resultado. Fecha de nacimiento, contacto y datos administrativos quedan fuera de la vista pública.

## Usuarios y acceso

| Perfil | Puede hacer |
| --- | --- |
| Visitante | Ver eventos, cuadros y resultados publicados; solicitar inscripción si está habilitada. |
| Organizador | Crear y gestionar solo sus eventos, inscripciones, cuadros, combates y publicaciones. |
| Administrador de plataforma | Gestionar incidencias y cuentas. Puede añadirse más adelante si se necesita. |

Los competidores no necesitan cuenta para la primera versión. El formulario público admite menores (menos de `ADULT_AGE` años, 18 por defecto, en la fecha del evento) solo si se aportan los datos del representante legal y su consentimiento; esos datos son privados y nunca se publican. Los textos legales siguen pendientes de revisión: ver [docs/menores.md](docs/menores.md).

## Modelo de datos orientativo

| Entidad | Datos principales | Relación |
| --- | --- | --- |
| `users` | nombre, email, contraseña | Organizador propietario de eventos. |
| `events` | `user_id`, nombre, disciplina, fecha, lugar, estado, visibilidad | Tiene categorías. |
| `categories` | `event_id`, nombre, criterios, tipo de podio, estado del cuadro | Pertenece a un evento. |
| `competitors` | nombre, apellidos, club, datos privados mínimos | Puede inscribirse en varios eventos. |
| `registrations` | `event_id`, `category_id`, `competitor_id`, estado | Inscripción única por competidor, evento y categoría. |
| `matches` | `category_id`, ronda, posición, participante A/B, ganador, estado | Modela el cuadro y el avance entre rondas. |

Los resultados y asignaciones de combate deben persistirse en base de datos; no deben depender del HTML del diagrama. Las relaciones entre combates (destino del ganador y, cuando proceda, de los perdedores de semifinal) se definirán explícitamente al implementar el generador. Se usarán migraciones, índices y claves foráneas. Las autorizaciones se comprobarán en cada operación sobre recursos de un evento, no solo al mostrar botones.

## Tecnología propuesta

- **Backend:** Laravel 13, PHP 8.4 y Composer. Laravel 13 requiere PHP 8.3 o superior.
- **Base de datos:** MySQL mediante Eloquent y migraciones; comprobar la versión y compatibilidad de MySQL de MAMP PRO y del servidor final antes de iniciar el proyecto.
- **Interfaz:** Blade, Bootstrap 5.3 y JavaScript puntual para formularios y visualización del cuadro. Vite para compilar CSS y JS. Mantener las acciones principales funcionando mediante peticiones normales; evitar una SPA en la primera versión.
- **Autenticación:** componentes nativos de Laravel para sesiones, contraseñas y restablecimiento. La interfaz de acceso se hará con Blade y Bootstrap; registrar usuarios no equivale a tener autorización sobre eventos ajenos.
- **Pruebas:** pruebas de las reglas del cuadro (1, 2, 3 y 5 participantes, pases, final y bronce), avance de ganadores, inscripciones duplicadas y permisos entre organizadores.

Esta combinación favorece el desarrollo en PHP y permite una interfaz familiar sin añadir infraestructura como Redis, WebSockets o colas al principio.

## Preparación local con MAMP PRO

Requisitos: MAMP PRO con Apache, **PHP 8.4 tanto para el sitio como para la terminal**, MySQL compatible, Composer y Node.js con npm. Verificar en la terminal `php -v`, `composer --version` y `node -v`; si la terminal usa otro PHP, seleccionar el binario PHP 8.4 de MAMP antes de ejecutar Composer o Artisan.

El código de la aplicación está en `web/`. Desde esa carpeta:

```bash
cd web
source scripts/dev-env.sh   # PHP 8.4 de MAMP y Node en el PATH (ver nota abajo)
composer install
cp .env.example .env
php artisan key:generate
npm install
```

`scripts/dev-env.sh` tiene fijadas las rutas de esta máquina (MAMP PHP 8.4.17 y Node 26): hay que editarlo en otro equipo.

Crear una base de datos MySQL vacía y ajustar `web/.env` con los valores reales de MAMP PRO (`DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD`, `DB_PORT`; `APP_URL=https://champcontrol.test`). Después:

```bash
php artisan migrate
npm run dev
```

En MAMP PRO, crear el host `champcontrol.test` y apuntar el **DocumentRoot a `champcontrol/web/public`**. Activar `mod_rewrite` y permitir las reglas `.htaccess` de Laravel. No mover `public/index.php`. La configuración de correo puede usar `MAIL_MAILER=log` hasta configurar SMTP para recuperar contraseñas.

Pruebas y estilo: `php artisan test` y `vendor/bin/pint --test`.

## Despliegue básico en Apache
El servidor debe tener PHP 8.4 con las extensiones requeridas por Laravel, Composer disponible para instalar dependencias, MySQL, y permisos de escritura para storage/ y bootstrap/cache/. Configurar el host Apache para servir exclusivamente public/, subir el código sin .env, crear un .env propio en el servidor, instalar dependencias con composer install --no-dev, ejecutar las migraciones y compilar los recursos antes o durante el despliegue. Ajustar APP_ENV=production, APP_DEBUG=false, APP_URL con HTTPS y configurar copias de seguridad de base de datos.

## Orden de desarrollo
1. Crear proyecto Laravel, entorno MAMP, estilos básicos y acceso de organizadores.
2. Modelos, migraciones y permisos de eventos y categorías.
3. Alta manual y formulario público de inscripciones; listas y confirmación.
4. Generador de cuadros de eliminación, pases y ajustes previos al inicio.
5. Registro de combates, clasificación, podios y páginas públicas.
6. Impresión, CSV, revisión con datos de un evento real y despliegue.
Primer hito verificable: un organizador crea un evento con una categoría, confirma cinco inscripciones, genera un cuadro con los pases necesarios, anota final y combate por el bronce, y publica la clasificación sin exponer datos privados.

## Decisiones pendientes
Ver [docs/decisiones-pendientes.md](docs/decisiones-pendientes.md) (regla del bronce, retiradas y descalificaciones, varias categorías por persona, etc.) y [docs/menores.md](docs/menores.md).

## Referencias
- Smoothcomp y su explicación de cuadros y resultados: referencia de recorrido del participante y visualización pública.
- MartialMatch: referencia para inscripciones, cuadros y publicación de resultados.
- Requisitos y despliegue de Laravel 13 y documentación oficial: referencia para instalación y requisitos del servidor.
Estos sitios sirven como inspiración funcional; ChampControl comienza con un alcance deliberadamente más reducido.
