#!/usr/bin/env bash
# Fotos dos posts da home, iguais às do layout.
set -euo pipefail

CLI="wp"
command -v wp >/dev/null 2>&1 || CLI="docker compose exec -T -u 33 cli wp"

export MSYS_NO_PATHCONV=1
export MSYS2_ARG_CONV_EXCL='*'

# os posts saem em nome da editora, como no layout
autor=$($CLI user list --role=administrator --field=ID | tr -d '' | head -1)
$CLI user update "$autor" --display_name="Conexão Editora" >/dev/null

# título do post|categoria|arquivo da foto|data (o mais novo vira o destaque grande)
POSTS='A importância da leitura na educação familiar|Educação Familiar|post-educacao-familiar.png|2026-08-05 09:00:00
Do manuscrito ao livro: como funciona o processo editorial|Literatura|post-manuscrito.png|2026-07-21 09:00:00
Livros de saúde: conhecimento que transforma o cuidado|Mastologia|post-saude.png|2026-07-18 09:00:00'

while IFS='|' read -r titulo categoria foto data <&3; do
  [ -n "$titulo" ] || continue

  id=$($CLI post list --post_type=post --title="$titulo" --field=ID 2>/dev/null | tr -d '\r' | head -1)

  if [ -z "$id" ]; then
    echo "  ! post não encontrado: $titulo"
    continue
  fi

  $CLI post update "$id" --post_date="$data" --post_author="$autor" >/dev/null
  $CLI term create category "$categoria" >/dev/null 2>&1 || true
  $CLI post term set "$id" category "$categoria" >/dev/null 2>&1 || true

  if [ -z "$($CLI post meta get "$id" _thumbnail_id 2>/dev/null | tr -d '\r')" ]; then
    $CLI media import "/scripts/capas/$foto" --post_id="$id" --featured_image --title="$titulo" >/dev/null
    echo "  + foto de: $titulo"
  else
    echo "  = já tinha foto: $titulo"
  fi
done 3<<< "$POSTS"
