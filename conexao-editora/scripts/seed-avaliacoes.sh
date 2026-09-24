#!/usr/bin/env bash
# Avaliações de exemplo nos livros em destaque, para o cartão da home mostrar
# as estrelas como no layout. Some quando o catálogo real tiver avaliações.
set -euo pipefail

CLI="wp"
command -v wp >/dev/null 2>&1 || CLI="docker compose exec -T -u 33 cli wp"

export MSYS_NO_PATHCONV=1
export MSYS2_ARG_CONV_EXCL='*'

for id in $($CLI eval 'foreach (wc_get_products(["featured" => true, "limit" => 8]) as $p) { echo $p->get_id(), PHP_EOL; }' | tr -d '\r'); do
  existentes=$($CLI eval "echo (int) get_comments(['post_id' => $id, 'type' => 'review', 'count' => true]);" | tr -d '\r')

  if [ "${existentes:-0}" -gt 0 ]; then
    echo "  = produto $id já tem avaliações"
    continue
  fi

  for nota in 5 4 4 5 4; do
    comentario=$($CLI comment create --comment_post_ID="$id" --comment_type=review \
      --comment_author="Leitor" --comment_author_email="leitor@exemplo.com.br" \
      --comment_content="Ótima leitura, recomendo." --comment_approved=1 --porcelain | tr -d '\r')
    $CLI comment meta update "$comentario" rating "$nota" >/dev/null
  done

  $CLI eval "WC_Comments::get_average_rating_for_product(wc_get_product($id)); WC_Comments::get_rating_counts_for_product(wc_get_product($id));" >/dev/null
  echo "  + avaliações no produto $id"
done
