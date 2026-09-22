#!/usr/bin/env bash
# Livros de exemplo, com os títulos reais do catálogo, para as vitrines da home
# terem conteúdo enquanto a migração não acontece.
set -euo pipefail

CLI="wp"
command -v wp >/dev/null 2>&1 || CLI="docker compose exec -T -u 33 cli wp"

# título|preço|preço antigo|categoria|autores|em destaque
LIVROS='A História da Associação Médica de Goiás|297|0|História|Rui Gilberto Ferreira, Waldemar Naves do Amaral, Sang Choon Cha|1
Lembre-se de quem você é|197|0|Biografia|Rafael Ferreira Delval|1
A História do Cremego|199|0|História|Paulo Roberto Cunha Vencio, Waldemar Naves do Amaral, Leonardo Mariano Reis|1
O Vácuo do Poder: Colapso Institucional e Ascensão do Crime Organizado no Brasil|199|299|Direito|Edmundo Dias|1
Traços e Histórias de Goiás: Montividiu – Volume 1|397|0|História|George Morais Ferreira|0
Em Busca da Fertilidade|189|0|Medicina|Waldemar Naves do Amaral|0
Ultrassonografia Pediátrica|249|0|Medicina|Waldemar Naves do Amaral|0
Manual de Perinatologia|279|0|Medicina|Waldemar Naves do Amaral|0
Vínculos Parentais e o Fluxo da Vida|159|0|Educação Familiar|Denise Sisterolli Diniz|0
Porfia: Memórias de Um Médico Oncologista|179|0|Biografia|Ademar Lopes|0'

# lê a lista pelo descritor 3: o docker exec consome a entrada padrão
while IFS='|' read -r titulo preco antigo categoria autores destaque <&3; do
  [ -n "$titulo" ] || continue

  existente=$($CLI post list --post_type=product --title="$titulo" --field=ID 2>/dev/null | head -1)
  if [ -n "$existente" ]; then
    continue
  fi

  id=$($CLI post create --post_type=product --post_status=publish --post_title="$titulo" \
    --post_excerpt="$titulo, publicado pela Conexão Editora." \
    --post_content="Descrição de exemplo. O texto definitivo vem na migração do catálogo." --porcelain)

  if [ "$antigo" != "0" ]; then
    $CLI post meta update "$id" _regular_price "$antigo" >/dev/null
    $CLI post meta update "$id" _sale_price "$preco" >/dev/null
  else
    $CLI post meta update "$id" _regular_price "$preco" >/dev/null
  fi

  $CLI post meta update "$id" _price "$preco" >/dev/null
  $CLI post meta update "$id" _stock_status "instock" >/dev/null
  $CLI post meta update "$id" _manage_stock "no" >/dev/null
  $CLI post meta update "$id" _virtual "no" >/dev/null
  $CLI post meta update "$id" _weight "0.6" >/dev/null

  $CLI post term set "$id" product_cat "$categoria" >/dev/null 2>&1 || true
  $CLI post term set "$id" product_type simple >/dev/null 2>&1 || true
  [ "$destaque" = "1" ] && $CLI post term add "$id" product_visibility featured >/dev/null 2>&1 || true

  IFS=',' read -ra nomes <<< "$autores"
  for nome in "${nomes[@]}"; do
    nome="$(echo "$nome" | sed 's/^ *//;s/ *$//')"
    [ -n "$nome" ] && $CLI post term add "$id" autor "$nome" >/dev/null 2>&1 || true
  done

  echo "  + $titulo"
done 3<<< "$LIVROS"
