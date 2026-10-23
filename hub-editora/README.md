# Hub Editora

Painel operacional único de pedidos e expedição da editora. Os canais de venda
(loja WooCommerce da Conexão Editora, checkouts Pagar.me das landing pages e Amazon Seller)
entram como origem de pedidos; a expedição sai por um só lugar, com etiqueta
do Melhor Envio e baixa automática do status no canal de origem.

## Stack e por quê

| Camada | Escolha | Motivo |
|---|---|---|
| Linguagem | PHP 8.3 | Roda numa conta cPanel comum do VPS (EA-PHP 8.3), como todos os outros sites. |
| Framework | Laravel 12 | Filas, agendador, cliente HTTP, migrações e testes prontos; sem Node nem Redis no servidor. |
| Painel | Filament 3 | Login, permissões, tabelas com filtros e **ações em lote** de fábrica — a Central de Expedição é isso. |
| Banco | MariaDB 10.11 | O que o VPS já tem. |
| Fila | driver `database` | Sem Redis no servidor; `queue:work` roda por cron do cPanel. |
| Front | Blade + Livewire (via Filament) | Nenhum build no servidor; assets compilados localmente e versionados. |

## Ambiente local

```bash
cp .env.example .env
docker compose up -d --build
docker compose exec app composer install
docker compose exec app php artisan key:generate
docker compose exec app php artisan migrate
```

Painel em <http://localhost:8095/admin>.

## Deploy (VPS cPanel)

1. Conta cPanel dedicada (ex.: `hubeditora`), subdomínio `hub.<dominio>` com
   docroot apontando para `public/`.
2. PHP 8.3 via MultiPHP, com a extensão `intl` habilitada (o Filament usa).
3. `composer install --no-dev --optimize-autoloader` na conta (Composer por
   projeto: `composer.phar` no diretório).
4. Cron do cPanel:
   - `* * * * * php artisan schedule:run` — sincronizações periódicas de segurança.
   - `* * * * * flock -n ~/.hub-queue.lock php artisan queue:work database --stop-when-empty --tries=3 --timeout=120 --max-time=50` — fila `default` (pedidos, webhooks, publicação).
   - `* * * * * flock -n ~/.hub-queue-longos.lock php -d memory_limit=1024M artisan queue:work database --queue=longos --stop-when-empty --tries=1 --timeout=11000` — fila `longos` (importação de PDF com IA e fichas PDF), separada para um livro grande não atrasar os pedidos.
5. `.env` de produção fora do docroot; credenciais das integrações **nunca**
   no repositório.

### Backup externo

`hub:backup` (diário, 02:40): `mysqldump` → gzip → AES-256-GCM → bucket S3
compatível em outro provedor e região, guardando `BACKUP_RETENTION_DAYS` dias.
`hub:backup-restore --conferir` (segundas, 06:15) decifra o mais recente e
alerta os administradores se algo falhar. Sem `BACKUP_*` no `.env`, os dois só
avisam e não fazem nada.

- Chave nova: `php artisan hub:backup --gerar-chave`. Guarde a chave no cofre de
  senhas, porque sem ela nenhum backup pode ser restaurado.
- Restaurar: `php artisan hub:backup-restore --listar`, depois
  `php artisan hub:backup-restore [arquivo] --saida=/caminho/hub.sql` e, por fim,
  `mysql banco < /caminho/hub.sql`. A restauração é manual, de propósito.

Teste de invasão anual: relatório em `docs/pentest-2026.md`.

## Estrutura de integrações

```
app/Integrations/
  WooCommerce/   REST v3 + webhooks (order.created / order.updated)
  PagarMe/       Core v5 (orders) + webhooks (order.paid, charge.*)
  MelhorEnvio/   cotação → carrinho → compra → etiqueta → rastreio
  Amazon/        SP-API: pedidos (RDT para endereço/comprador) e confirmação de envio; única fonte de dados da Amazon
```

Cada integração é isolada: mudar uma API não derruba as outras. Todo canal
tem webhook **e** varredura periódica — o webhook nunca é 100%.

A Amazon não tem webhook para o hub: varredura a cada 15 min pela SP-API.
Nenhum ERP ou intermediário é fonte de dados da Amazon (exigência da AUP).

### Cadastro dos webhooks

O webhook só avisa "o pedido X mudou"; o hub rebusca o pedido na API do
canal com a própria credencial e enfileira (`ImportChannelOrder`). Rajadas
do mesmo pedido em 60 s viram uma importação só.

| Canal | URL | Como configurar |
|---|---|---|
| WooCommerce | `POST /webhooks/woocommerce` | WooCommerce → Configurações → Avançado → Webhooks. Um para "Pedido criado" e outro para "Pedido atualizado", status Ativo, API v3. Segredo = `WOO_WEBHOOK_SECRET` (o Woo assina o corpo com HMAC-SHA256). |
| Pagar.me | `POST /webhooks/pagarme` | Dash → Configurações → Webhooks. Eventos `order.*` e `charge.paid/refunded/chargedback/payment_failed`. Ativar autenticação Basic com senha = `PAGARME_WEBHOOK_SECRET` (usuário livre). Alternativa: `?token=` na URL. |

Sem o segredo configurado no `.env` a rota responde 503 e não processa nada.

## Regras que valem desde o primeiro dia

- Pedido-filho de vendor (MVX no WooCommerce) **não** entra: só `parent = 0`.
  O multivendor mora no hub (abaixo): a comissão sai dos itens do pedido pai.
- Status interno padronizado (`NOVO … ENTREGUE / CANCELADO / PROBLEMA`) e
  uma tabela de tradução por canal.
- Idempotência por `(canal, id_externo)`: reprocessar um webhook não duplica.
- Toda chamada externa registra log auditável (quem, quando, o quê, resposta).

## Multivendor (vendedores, comissões e repasses)

Autores e parceiros que vendem pela editora (no lugar do MVX da loja). Menu
"Vendedores" (só administrador) e portal do vendedor (perfil Vendedor).

- O livro (produto) tem um vendedor; a comissão é `valor dos itens` (sem frete,
  já com desconto) × `%` do livro, senão o do vendedor (padrão 65%, como no MVX).
- Uma comissão por pedido + livro, recalculada a cada importação e a cada mudança
  de etapa/pagamento (`App\Vendors\Commissions`). Pedido sem pagamento: pendente;
  pago: a repassar; cancelado/estornado: cancelada.
- `Comissões a partir de` no vendedor: pedidos anteriores não geram comissão
  (acertados fora do hub).
- Repasse: junta as comissões a repassar até uma data (rascunho "a pagar"), a
  editora transfere e registra o pagamento. Comissão já repassada não muda; se o
  pedido cair depois, nasce estorno negativo que desconta no repasse seguinte.
- Portal do vendedor (`/admin/minhas-vendas`, `/admin/meus-repasses`): só as
  vendas e repasses dele, sem dados do cliente. Não vê pedidos, catálogo nem
  expedição.
- `php artisan hub:vendors-sync` recalcula; `hub:vendors-import-mvx arquivo.json
  --desde=AAAA-MM-DD` traz vendedores e livros do MVX.

## Identidade visual

Azul principal `#2E6BD6`, sidebar `#E9F1FA` com texto `#1E3A5F`, fundo papel
`#F6F5F2`, bordas `#E6E3DD`. Fontes Nunito Sans (interface) e IBM Plex Mono
(IDs, rastreios e valores). Telas de referência: canvas "Hub Editora".
