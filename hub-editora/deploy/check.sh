#!/usr/bin/env bash
# Verificação obrigatória antes de publicar (roda no clone de testes ~/hub-ci):
#   1. auditoria de vulnerabilidades nas dependências (composer audit)
#   2. sintaxe de todo o código da aplicação
#   3. suíte de testes automatizados
# Qualquer falha impede o deploy.sh de seguir.
set -euo pipefail

PHP=${PHP:-/opt/cpanel/ea-php83/root/usr/bin/php}
COMPOSER=${COMPOSER:-$HOME/bin/composer}
CI=${CI_DIR:-$HOME/hub-ci/hub-editora}

cd "$CI/.."
git pull -q --ff-only
cd "$CI"

echo "== 1/3 vulnerabilidades nas dependências"
"$PHP" -d allow_url_fopen=1 "$COMPOSER" audit --no-dev --locked --format=summary

echo "== 2/3 sintaxe"
erros=0
while IFS= read -r f; do "$PHP" -l "$f" >/dev/null || erros=1; done < <(find app routes config database -name '*.php')
[ "$erros" -eq 0 ] || { echo "erro de sintaxe"; exit 1; }

echo "== 3/3 testes"
"$PHP" artisan test --compact 2>&1 | tail -3

echo "== verificação OK: $(git rev-parse --short HEAD)"
