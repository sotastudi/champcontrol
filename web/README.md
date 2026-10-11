# ChampControl (código Laravel)

Aplicación Laravel 13 / PHP 8.4. Descripción del proyecto en el [README raíz](../README.md); decisiones abiertas en [docs/decisiones-pendientes.md](../docs/decisiones-pendientes.md).

```bash
source scripts/dev-env.sh && composer install && cp .env.example .env
php artisan key:generate && npm install && php artisan migrate && npm run dev
```

DocumentRoot de MAMP PRO: `champcontrol/web/public`. Pruebas: `php artisan test`; estilo: `vendor/bin/pint --test`.
