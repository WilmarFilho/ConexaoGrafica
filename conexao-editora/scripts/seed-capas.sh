#!/usr/bin/env bash
# Livros que aparecem na faixa de abertura, com as capas enviadas pela editora.
# As imagens ficam em scripts/capas e entram como imagem destacada do produto.
set -euo pipefail

CLI="wp"
command -v wp >/dev/null 2>&1 || CLI="docker compose exec -T -u 33 cli wp"

# no Git Bash do Windows, caminhos que começam com / viram C:\... antes de chegar
# no container; isso desliga a conversão
export MSYS_NO_PATHCONV=1
export MSYS2_ARG_CONV_EXCL='*'

# título|preço|categoria|autores|arquivo da capa
LIVROS='A História da Faculdade de Medicina da UFG|297|História|Antônio Fernando Carneiro, Waldemar Naves do Amaral|faculdade-medicina-ufg.png
A História da SBUS 30|249|Medicina|Rui Gilberto Ferreira, Waldemar Naves do Amaral, Sang Choon Cha|sbus-30.png
A História do Cremego|199|História|Paulo Roberto Cunha Vencio, Waldemar Naves do Amaral, Leonardo Mariano Reis|cremego.png
História do Hospital e Maternidade Dona Íris|229|Medicina|Heylha Mar Costa P. da Silva, Waldemar Naves do Amaral|hospital-dona-iris.png'

destaques=""

while IFS='|' read -r titulo preco categoria autores capa <&3; do
  [ -n "$titulo" ] || continue

  id=$($CLI post list --post_type=product --title="$titulo" --field=ID 2>/dev/null | tr -d '\r' | head -1)

  if [ -z "$id" ]; then
    id=$($CLI post create --post_type=product --post_status=publish --post_title="$titulo" \
      --post_excerpt="$titulo, publicado pela Conexão Editora." \
      --post_content="Descrição de exemplo. O texto definitivo vem na migração do catálogo." --porcelain | tr -d '\r')
    echo "  + $titulo"
  else
    echo "  = $titulo"
  fi

  $CLI post meta update "$id" _regular_price "$preco" >/dev/null
  $CLI post meta update "$id" _price "$preco" >/dev/null
  $CLI post meta update "$id" _stock_status "instock" >/dev/null
  $CLI post meta update "$id" _weight "0.6" >/dev/null
  # a ordem na faixa de abertura é a do layout
  ordem=$((${ordem:-0} + 1))
  $CLI post update "$id" --menu_order="$ordem" >/dev/null
  $CLI post term set "$id" product_cat "$categoria" >/dev/null 2>&1 || true
  $CLI post term set "$id" product_type simple >/dev/null 2>&1 || true

  IFS=',' read -ra nomes <<< "$autores"
  for nome in "${nomes[@]}"; do
    nome="$(echo "$nome" | sed 's/^ *//;s/ *$//')"
    [ -n "$nome" ] && $CLI post term add "$id" autor "$nome" >/dev/null 2>&1 || true
  done

  # a capa só é importada uma vez
  if [ -z "$($CLI post meta get "$id" _thumbnail_id 2>/dev/null | tr -d '\r')" ]; then
    $CLI media import "/scripts/capas/$capa" --post_id="$id" --featured_image --title="$titulo" >/dev/null
  fi

  $CLI post term add "$id" product_visibility featured >/dev/null 2>&1 || true
  destaques="$destaques $id"
done 3<<< "$LIVROS"

# na abertura ficam só estes quatro; os demais saem do destaque
for outro in $($CLI post list --post_type=product --field=ID | tr -d '\r'); do
  case " $destaques " in
    *" $outro "*) ;;
    *) $CLI post term remove "$outro" product_visibility featured >/dev/null 2>&1 || true ;;
  esac
done

echo "  capas prontas"
