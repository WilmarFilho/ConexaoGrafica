#!/usr/bin/env bash
# Primeira instalação do site em produção. Roda como o usuário do cPanel:
#   DB_NAME=... DB_USER=... DB_PASS=... SITE_URL=https://conexaoeditora.com.br \
#   ADMIN_USER=... ADMIN_MAIL=... bash ~/hub/conexao-editora/deploy/instalar.sh
#
# A senha do banco e a do administrador vêm do ambiente; nada fica gravado aqui.
set -euo pipefail

REPO="${REPO:-$HOME/hub}"
RAIZ="${RAIZ:-$HOME/sites/conexaoeditora}"
PHP="${PHP:-/opt/cpanel/ea-php83/root/usr/bin/php -d memory_limit=768M}"
WP="$PHP /usr/local/bin/wp --path=$RAIZ"
PROJETO="$REPO/conexao-editora"

: "${DB_NAME:?informe DB_NAME}"
: "${DB_USER:?informe DB_USER}"
: "${DB_PASS:?informe DB_PASS}"
: "${SITE_URL:?informe SITE_URL}"

mkdir -p "$RAIZ"

echo "== núcleo do WordPress"
if [ ! -f "$RAIZ/wp-settings.php" ]; then
  $WP core download --locale=pt_BR
fi

if [ ! -f "$RAIZ/wp-config.php" ]; then
  $WP config create --dbname="$DB_NAME" --dbuser="$DB_USER" --dbpass="$DB_PASS" \
    --locale=pt_BR --extra-php <<'PHP'
define('DISALLOW_FILE_EDIT', true);
define('WP_AUTO_UPDATE_CORE', 'minor');
PHP
fi

echo "== tema"
mkdir -p "$RAIZ/wp-content/themes/conexao-editora"
rsync -a --delete \
  "$PROJETO/wp-content/themes/conexao-editora/" \
  "$RAIZ/wp-content/themes/conexao-editora/"

echo "== conteúdo e configuração"
cd "$RAIZ"
SITE_URL="$SITE_URL" bash "$PROJETO/scripts/setup.sh"

echo "== catálogo da loja atual"
mkdir -p /tmp/conexao-scripts
ln -sfn "$PROJETO/scripts/dados" /tmp/conexao-scripts/dados 2>/dev/null || true
$WP eval-file "$PROJETO/scripts/importar-pubcon.php" || echo "  (importação do catálogo falhou; rode depois)"

echo "== textos das políticas"
for slug in politica-de-privacidade termos-de-uso politica-de-cookies; do
  arquivo="$PROJETO/scripts/dados/$slug.html"
  id=$($WP post list --post_type=page --name="$slug" --field=ID | tr -d '\r' | head -1)
  if [ -n "$id" ] && [ -f "$arquivo" ]; then
    $WP post update "$id" --post_content="$(cat "$arquivo")" >/dev/null
    echo "  $slug publicado"
  fi
done

echo
echo "Instalado em $SITE_URL"
