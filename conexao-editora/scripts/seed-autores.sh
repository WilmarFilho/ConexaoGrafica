#!/usr/bin/env bash
# Autores em destaque no menu: foto e ordem de exibição.
set -euo pipefail

CLI="wp"
command -v wp >/dev/null 2>&1 || CLI="docker compose exec -T -u 33 cli wp"

export MSYS_NO_PATHCONV=1
export MSYS2_ARG_CONV_EXCL='*'

# dentro do container as imagens ficam em /scripts/autores; no servidor,
# ao lado deste arquivo
if [ "$CLI" = "wp" ]; then
  FOTOS="${FOTOS:-$(cd "$(dirname "$0")" && pwd)/autores}"
else
  FOTOS="${FOTOS:-/scripts/autores}"
fi

# nome do autor|arquivo da foto
AUTORES='Edemundo Dias|edemundo-dias.png
Rui Gilberto Ferreira|rui-gilberto-ferreira.png
Paulo Roberto Cunha|paulo-roberto-cunha.png
Leonardo Reis|leonardo-reis.png
Roberto Murillo Limongi|roberto-murillo-limongi.png
Fábio Bagnoli|fabio-bagnoli.png
Waldemar Naves do Amaral|waldemar-naves-do-amaral.png'

ordem=0

while IFS='|' read -r nome arquivo <&3; do
  [ -n "$nome" ] || continue
  ordem=$((ordem + 1))

  $CLI term create autor "$nome" >/dev/null 2>&1 || true
  id=$($CLI term list autor --name="$nome" --field=term_id | tr -d '\r' | head -1)

  if [ -z "$id" ]; then
    echo "  ! não consegui criar o autor: $nome"
    continue
  fi

  $CLI term meta update "$id" conexao_ordem "$ordem" >/dev/null

  if [ -z "$($CLI term meta get "$id" conexao_foto 2>/dev/null | tr -d '\r')" ]; then
    foto=$($CLI media import "$FOTOS/$arquivo" --title="$nome" --porcelain | tr -d '\r')
    $CLI term meta update "$id" conexao_foto "$foto" >/dev/null
    echo "  + $nome"
  else
    echo "  = $nome"
  fi
done 3<<< "$AUTORES"
