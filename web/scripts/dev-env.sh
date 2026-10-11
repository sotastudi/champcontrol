#!/usr/bin/env bash
# Uso (desde web/): source scripts/dev-env.sh
# Pone PHP 8.4 de MAMP y Node 26 por delante en el PATH de esta shell.
unalias php composer 2>/dev/null
export PATH="/Applications/MAMP/bin/php/php8.4.17/bin:/usr/local/opt/node@26/bin:$PATH"
if ! php -r 'exit(PHP_MAJOR_VERSION === 8 && PHP_MINOR_VERSION === 4 ? 0 : 1);'; then
    echo "ERROR: php no es 8.4 (es $(php -r 'echo PHP_VERSION;'))" >&2
fi
