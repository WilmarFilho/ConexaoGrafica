# Conexão Gráfica — WordPress

Site da Conexão Gráfica: catálogo de produtos gráficos configuráveis, blog e páginas
montadas com seções reutilizáveis. **Sem page builder** — PHP, templates e shortcodes.

Produção: `graficaconexao.com.br` — WordPress 7.0.x / PHP 8.2 / cPanel (conta
`clientescx`). Deploy automático a cada push na `main`: ver [DEPLOY.md](DEPLOY.md).

## Ambiente local

```bash
docker compose up -d
```

| Serviço    | URL                     | Acesso                  |
|------------|-------------------------|-------------------------|
| WordPress  | http://localhost:8080   | instalar na primeira vez |
| phpMyAdmin | http://localhost:8082   | `conexao` / `conexao`   |

Versões batem com produção: WordPress 7.0.2 sobre PHP 8.2.32.

Depois de instalar o WP:

1. **Plugins → Conexão Core → Ativar** (registra o CPT Produto e as taxonomias).
2. **Aparência → Temas → Conexão Gráfica → Ativar**.
3. **Configurações → Links permanentes → Salvar** (garante `/produtos/nome-do-produto`).
4. **Configurações → Conexão** → coloque o número do WhatsApp com DDI (ex.: `5521999999999`).

**Painel → Conexão** é o mapa das seções: mostra onde se edita cada uma, quantos
itens ela exibe e quantos estão sem imagem. Comece por ali.

As regras de permalink ficam em `docker/apache-wordpress.conf`, e não no
`.htaccess`. A imagem oficial do Apache traz `AllowOverride None`, o que faz o
`.htaccess` do WordPress ser ignorado e todo permalink bonito virar 404 — e o
WP-CLI não consegue regerar esse arquivo. Com as regras no Apache, um
`docker compose up` num volume limpo já sobe com os links funcionando.

O core do WordPress vive num volume Docker. Só o que está em
`wp-content/plugins/conexao-core` e `wp-content/themes/conexao` é montado do disco —
é esse o código versionado.

## Estrutura

```
wp-content/
  plugins/conexao-core/        ← tipos de conteúdo, campos, configurações
    conexao-core.php           ← cabeçalho, constantes, requires
    includes/
      post-types.php           ← CPT Produto (a aba no menu do admin)
      taxonomies.php           ← Categorias e Soluções
      meta-produto.php         ← metaboxes: dados, configuração, blocos, galeria
      admin-columns.php        ← colunas e filtros da listagem
      admin-assets.php         ← CSS/JS do admin
      settings.php             ← Configurações → Conexão (WhatsApp)
      helpers.php              ← leitura dos dados no front
  themes/conexao/              ← apresentação
    single-cnx_produto.php     ← página do produto (galeria + configurador)
    assets/js/produto.js       ← seleção de opções → link wa.me
```

A separação é proposital: **o plugin guarda os dados, o tema só desenha**. Trocar o
tema amanhã não apaga um produto sequer.

## Por que não WooCommerce

Não há checkout: o cliente escolhe quantidade, tipo de arte e prazo, e o botão
"Solicitar Orçamento" abre o WhatsApp com a mensagem pronta. O Woo traria carrinho,
pedidos, impostos e frete — nada disso é usado, e cada um deles é uma tela a mais
para manter. O CPT próprio faz o que precisa e nada além.

## Como o produto é modelado

Um produto tem **grupos de configuração**, cadastrados em cada produto no admin:

| Grupo            | Texto de apoio           | Opções (uma por linha)                                  |
|------------------|--------------------------|---------------------------------------------------------|
| Quantidade       | Mínimo de 500 unidades   | 500 unidades / 1.000 unidades / 1.500 unidades / + 2.000 |
| Arte do material | Escolha a melhor opção   | Já tenho arte pronta / Preciso ajustar / Preciso criar   |
| Prazo desejado   | Qual a urgência          | Prazo flexível / Tenho urgência / Ainda planejando       |

Cada grupo vira um bloco de botões na página. As escolhas alimentam o "Resumo" e são
concatenadas na mensagem do WhatsApp:

```
Olá! Vim pelo site e quero um orçamento.

Produto: Cartão de Visita
Quantidade: 1.000 unidades
Arte do material: Já tenho arte pronta
Prazo desejado: Tenho urgência

https://graficaconexao.com.br/produtos/cartao-de-visita
```

Grupos marcados como obrigatórios travam o botão até serem preenchidos.

## Header

Três faixas, cada uma num partial de `template-parts/header/`:

| Faixa       | Arquivo         | Conteúdo                                          |
|-------------|-----------------|---------------------------------------------------|
| Topbar      | `topbar.php`    | WhatsApp do vendedor, menu institucional, CTA laranja |
| Branding    | `branding.php`  | Logo, busca, conta, orçamento e hamburguer        |
| Categorias  | `categorias.php`| Categorias em destaque                            |
| Menu mobile | `menu-mobile.php` | Painel lateral das telas estreitas              |

Abaixo de 900px a topbar e a faixa de categorias somem e o conteúdo delas migra
para o menu lateral. A barra passa a ser preta com logo, conta, carrinho e
hamburguer; a busca fica na linha de baixo, sobre fundo claro.

`lista-categorias.php` é a fonte única da lista: a faixa do desktop e o menu
mobile chamam o mesmo partial, com classes diferentes.

Locais de menu em **Aparência → Menus**:

- **Topbar (faixa preta)** — Produtos, Soluções, Blog, Contato. Sem menu atribuído,
  o tema cai num fallback com esses quatro links.
- **Categorias do header** — controla rótulo e **ordem** da terceira faixa. Sem menu,
  lista as categorias de produto de primeiro nível.

A logo vem de `assets/img/logo.png`. Uma logo definida em **Aparência → Personalizar
→ Identidade do site** tem prioridade sobre ela.

O número do WhatsApp e o destino do botão "Solicitar Orçamento" ficam em
**Configurações → Conexão**.

## Seções no mobile

Abaixo de 700px, toda seção em grade vira um carrossel de um card por vez, com
setas nas bordas. É o mesmo `assets/js/trilho.js` em todas: o script só liga as
setas quando o trilho realmente rola, então no desktop — onde as grades são
`grid` e não rolam — elas se escondem sozinhas, sem CSS extra.

O rodapé se reordena por `grid-template-areas`: marca centralizada, menu ao lado
das redes, contato, e pagamentos numa faixa própria. `display: contents` no
wrapper de extras solta pagamentos e redes para o grid posicioná-los
separadamente sem duplicar marcação.

## Cache dos assets

`style.css` e os scripts são versionados por `filemtime()` (`cnx_asset_ver()`).
Sem isso o navegador segura o CSS antigo entre uma edição e outra, e você acaba
depurando um arquivo que o browser nem baixou.

## Seções reutilizáveis (shortcodes)

**Página do WordPress não executa PHP.** Você cria a página e cola o *shortcode*;
o HTML mora no tema, em `template-parts/sections/`. É assim que uma seção é
reaproveitada em várias páginas sem duplicar código.

| Shortcode              | Renderiza                  | Conteúdo vem de              |
|------------------------|----------------------------|------------------------------|
| `[cnx_hero]`           | Carrossel de destaques     | CPT **Slides**               |
| `[cnx_diferenciais]`   | Faixa dos 4 selos          | Array filtrável em PHP       |
| `[cnx_categorias]`     | Categorias em destaque     | Taxonomia **Categorias**     |
| `[cnx_solucoes]`       | Cards por segmento         | Taxonomia **Soluções**       |
| `[cnx_mais_vendidos]`  | Vitrine de produtos        | Produtos marcados destaque   |
| `[cnx_banner]`         | Faixa com foto de fundo    | CPT **Banners**              |
| `[cnx_como_funciona]`  | As quatro etapas           | Array filtrável em PHP       |

A home é a página "Home", cujo conteúdo é literalmente:

```
[cnx_hero]

[cnx_diferenciais]

[cnx_categorias]

[cnx_solucoes]

[cnx_mais_vendidos]

[cnx_banner slug="parceria-estrategica"]

[cnx_como_funciona]
```

`[cnx_hero]` aceita `limite` (máximo de slides) e `autoplay` (segundos; `0`
desliga): `[cnx_hero autoplay="8"]`.

Os quatro selos mudam quase nunca — em vez de mais uma tela no admin, ficam num
array em `includes/shortcodes.php`, alterável pelo filtro `cnx_diferenciais`.

### Slides

Menu **Slides** no painel. Cada slide tem:

- **Título do post** — rótulo interno, só aparece na listagem do admin.
- **Título exibido** — o texto na tela. Aceita `<strong>` porque o negrito muda de
  lugar entre os slides: `<strong>Impressão profissional</strong> para empresas...`
  no primeiro, `Papelaria profissional <strong>para Advogados</strong>` no segundo.
- **Descrição**, **dois botões** (texto + link).
- **Imagem do produto** (imagem destacada) — a arte da coluna direita.
- **Imagem de fundo** — opcional; sem ela usa `assets/img/hero-bg.png`.
- **Ordem** (em Atributos) — define a sequência no carrossel.

O carrossel é JavaScript próprio, sem biblioteca: setas, pontos, autoplay com
pausa no hover e no foco, swipe no touch, setas do teclado e `prefers-reduced-motion`
respeitado. O script só é carregado nas páginas que usam `[cnx_hero]`.

## Rodapé

Duas seções fixas em todas as páginas, em `template-parts/footer/`:

| Faixa    | Arquivo      | Conteúdo                                              |
|----------|--------------|-------------------------------------------------------|
| Captura  | `cta.php`    | "10% OFF", botão de orçamento e formulário de desconto |
| Rodapé   | `rodape.php` | Logo, menu, contato, pagamentos, redes e links legais  |

Não são shortcodes: aparecem sempre, direto no `footer.php`.

Contato, horário, redes e a descrição curta vêm de **Configurações → Conexão**.
Nenhum telefone ou e-mail está cravado no template.

Locais de menu adicionais: **Menu do rodapé** e **Rodapé — links legais** (com
fallback para Política de Privacidade, Termos de uso e Política de Cookies).

### Formulário de desconto

O envio vai para `admin-post.php` e volta com redirect (padrão PRG), então
recarregar a página não reenvia o formulário. Cada inscrição vira um post do CPT
**Leads**, e um e-mail de aviso é disparado para o e-mail comercial.

- As opções de "Tipo de serviço" são as categorias de produto — a lista se mantém
  sozinha conforme o catálogo cresce.
- Proteções: nonce, honeypot escondido no CSS e recusa de e-mail repetido.
- **Leads → Exportar CSV** baixa a lista com BOM (o Excel abre os acentos certos).

O `wp_mail()` do container local não tem MTA, então a notificação falha em
desenvolvimento — isso é esperado. Em produção o cPanel entrega normalmente; se
não entregar, instale um plugin de SMTP.

## Deploy para produção

Código e conteúdo seguem caminhos diferentes — essa é a regra que evita dor de cabeça.

### Código (plugin + tema) — sempre por Git

O repositório contém *apenas* `wp-content/plugins/conexao-core` e
`wp-content/themes/conexao`. O core do WP e os uploads ficam de fora.

Todo push na `main` dispara o GitHub Actions, que checa a sintaxe PHP e envia as
duas pastas por `rsync`. A configuração dos segredos está em [DEPLOY.md](DEPLOY.md).

Nunca edite arquivo direto no File Manager do cPanel: o próximo deploy sobrescreve
a alteração sem avisar. `DISALLOW_FILE_EDIT` já está ligado localmente pelo mesmo
motivo.

### Conteúdo (banco + uploads)

O banco local é descartável: serve para testar. Produtos, posts e páginas reais são
cadastrados **em produção**. Se em algum momento for preciso levar o banco daqui
para lá (por exemplo, na carga inicial do catálogo):

```bash
docker compose exec wordpress wp --allow-root db export /tmp/dump.sql
docker compose cp wordpress:/tmp/dump.sql ./dump.sql
```

Importe pelo phpMyAdmin do cPanel e rode o search-replace **pelo WP-CLI do servidor**
(nunca com `find & replace` de SQL, que corrompe os campos serializados):

```bash
wp search-replace 'http://localhost:8080' 'https://graficaconexao.com.br' --all-tables --precise
```

Uploads vão por SFTP/rsync para `wp-content/uploads` — nunca pelo Git.

### Antes de cada deploy

- Rodar `docker compose exec wordpress php -l` nos arquivos alterados (ou o CI).
- Conferir o log: `docker compose exec wordpress tail -f wp-content/debug.log`.
- Em produção, `WP_DEBUG_DISPLAY` fica desligado — erro nenhum aparece para o cliente.
