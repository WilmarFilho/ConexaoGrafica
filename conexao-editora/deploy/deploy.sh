#!/usr/bin/env bash
# Publica o tema e o plugin da biblioteca no site de produção. Roda como o usuário do cPanel:
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

echo "== plugin da biblioteca"
rsync -a --delete   "$REPO/conexao-editora/wp-content/plugins/conexao-biblioteca/"   "$RAIZ/wp-content/plugins/conexao-biblioteca/"

# os livros ficam fora da pasta pública; a pasta só é criada, nunca apagada
BIBLIOTECA="${BIBLIOTECA:-$HOME/biblioteca}"
mkdir -p "$BIBLIOTECA"
chmod 750 "$BIBLIOTECA"
$WP config has CONEXAO_BIBLIOTECA_DIR >/dev/null 2>&1 || $WP config set CONEXAO_BIBLIOTECA_DIR "$BIBLIOTECA" --type=constant
$WP plugin is-active conexao-biblioteca || $WP plugin activate conexao-biblioteca

echo "== banco e caches"
$WP core update-db
$WP rewrite flush --hard
$WP cache flush >/dev/null 2>&1 || true

echo "== ok: $(git -C "$REPO" rev-parse --short HEAD)"
