#!/usr/bin/env bash
# Roda como hubpubcon no servidor (chamado por testar-wip.sh): monta ~/hub-wip[-frente]
# com o código enviado e roda os testes. O vendor é uma cópia real do ~/hub-ci, feita
# uma vez por pasta e reaproveitada (symlink faria o PHPUnit carregar o autoload errado).
set -e
TAG="$1"
FRENTE="$2"
FILTRO="$3"
LINHAS="${4:-40}"
PHP=/opt/cpanel/ea-php83/root/usr/bin/php
WIP="$HOME/hub-wip${FRENTE:+-$FRENTE}"
mkdir -p "$WIP"
cd "$WIP"
if [ ! -d vendor/laravel ]; then
    rm -rf vendor
    cp -r "$HOME/hub-ci/hub-editora/vendor" vendor
fi
find . -mindepth 1 -maxdepth 1 ! -name vendor -exec rm -rf {} +
mkdir -p bootstrap/cache storage/framework/cache storage/framework/sessions storage/framework/views storage/framework/testing storage/logs storage/app/private
tar -xzf "/tmp/hub-wip-$TAG.tgz"
# Pacotes novos no composer.json que o vendor copiado não tem: instala só nesta pasta.
if ! cmp -s composer.lock "$HOME/hub-ci/hub-editora/composer.lock"; then
    "$PHP" -d allow_url_fopen=1 "$HOME/bin/composer" install --no-interaction --no-progress -q 2>&1 | tail -3 || true
fi
"$PHP" -d allow_url_fopen=1 "$HOME/bin/composer" dump-autoload -q 2>&1 | tail -3
cp "$HOME/hub-ci/hub-editora/.env" .env 2>/dev/null || true
if [ -n "$FILTRO" ]; then
    "$PHP" artisan test --compact --filter="$FILTRO" 2>&1 | tail -n "$LINHAS"
else
    "$PHP" artisan test --compact 2>&1 | tail -n "$LINHAS"
fi
