#!/usr/bin/env bash
# Publica o tema no site de produção. Roda como o usuário do cPanel:
#   bash ~/hub/conexao-editora/deploy/deploy.sh
set -euo pipefail

REPO="${REPO:-$HOME/hub}"
RAIZ="${RAIZ:-$HOME/sites/conexaoeditora}"
PHP="${PHP:-/opt/cpanel/ea-php83/root/usr/bin/php -d memory_limit=768M}"
WP="$PHP /usr/local/bin/wp --path=$RAIZ"

echo "== código"
git -C "$REPO" pull --ff-only

echo "== tema"
rsync -a --delete \
  "$REPO/conexao-editora/wp-content/themes/conexao-editora/" \
  "$RAIZ/wp-content/themes/conexao-editora/"

echo "== banco e caches"
$WP core update-db
$WP rewrite flush --hard
$WP cache flush >/dev/null 2>&1 || true

echo "== ok: $(git -C "$REPO" rev-parse --short HEAD)"
