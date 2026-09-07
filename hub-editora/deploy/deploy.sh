#!/usr/bin/env bash
# Publica o Hub Editora na conta cPanel (rodar COMO O USUÁRIO da conta, no servidor).
#
#   ssh hubpubcon@servidor 'bash ~/hub/deploy/deploy.sh'
#
# Layout na conta:
#   ~/hub            clone do repositório (só a pasta hub-editora interessa)
#   ~/public_html -> ~/hub/hub-editora/public   (symlink; docroot do domínio)
#   ~/hub/hub-editora/.env                       segredos de produção (nunca no git)
set -euo pipefail

PHP=${PHP:-/opt/cpanel/ea-php83/root/usr/bin/php}
# Composer fica em ~/bin (o cPanel não traz um). allow_url_fopen é desligado no
# php.ini global; o Composer precisa dele, então liga só nesta chamada.
COMPOSER=${COMPOSER:-$HOME/bin/composer}
APP=${APP:-$HOME/hub/hub-editora}

cd "$APP/.."
echo "== git pull"
git pull --ff-only

cd "$APP"
echo "== composer install (sem dev)"
"$PHP" -d allow_url_fopen=1 "$COMPOSER" install --no-dev --no-interaction --prefer-dist --optimize-autoloader --no-progress 2>&1 | tail -3

echo "== migrações"
"$PHP" artisan migrate --force

echo "== caches"
"$PHP" artisan optimize:clear >/dev/null
"$PHP" artisan config:cache
"$PHP" artisan route:cache
"$PHP" artisan view:cache
"$PHP" artisan filament:optimize 2>/dev/null || true

# Fila: o worker com --stop-when-empty é recriado pelo cron; sinaliza reinício por garantia.
"$PHP" artisan queue:restart >/dev/null || true

echo "== ok: $(git -C "$APP/.." rev-parse --short HEAD)"
