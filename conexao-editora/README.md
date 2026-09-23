# Conexão Editora — loja nova (ambiente local)

WordPress + WooCommerce com tema próprio, rodando em Docker enquanto o domínio
definitivo não é registrado.

## Subir

```bash
docker compose up -d
bash scripts/setup.sh        # primeira vez: instala WP, WooCommerce, páginas e conteúdo de exemplo
```

Loja: http://localhost:8092 · Painel: http://localhost:8092/wp-admin
E-mails do ambiente (contato, cadastro, recuperação de senha): http://localhost:8026
(usuário e senha ficam no fim de `scripts/setup.sh`, valem só neste ambiente local).

## Comandos do dia a dia

```bash
docker compose exec -u 33 cli wp plugin list
docker compose exec -u 33 cli wp rewrite flush
docker compose logs -f wp
```

## Como o projeto está organizado

- `wp-content/themes/conexao-editora/` — o tema, única parte versionada do site.
  O núcleo do WordPress e os plugins ficam em volumes do Docker, fora do git.
- `scripts/setup.sh` — deixa o ambiente igual ao de qualquer outra máquina:
  instala o WordPress, ativa o tema e o WooCommerce, cria as páginas, os menus,
  as categorias de livro e alguns produtos de exemplo.

## Pendências conhecidas

- **Logo:** o arquivo em `assets/img/` é um recorte do design, só para o
  desenvolvimento. Precisa ser trocado pelo logo oficial em SVG.
- **Tipografia:** o tema usa Figtree, aproximação da fonte do layout. Quando a
  fonte oficial chegar, basta trocar em `assets/css/theme.css`.
- **Domínio:** ao registrar, trocar a URL do site e reapontar as integrações.
