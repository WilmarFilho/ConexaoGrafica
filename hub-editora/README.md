# Hub Editora

Painel operacional único de pedidos e expedição da editora. Os canais de venda
(WooCommerce da Pubcon, checkouts Pagar.me das landing pages e Amazon Seller)
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
   - `* * * * * php artisan queue:work database --stop-when-empty` — fila.
5. `.env` de produção fora do docroot; credenciais das integrações **nunca**
   no repositório.

## Estrutura de integrações

```
app/Integrations/
  WooCommerce/   REST v3 + webhooks (order.created / order.updated)
  PagarMe/       Core v5 (orders) + webhooks (order.paid, charge.*)
  MelhorEnvio/   cotação → carrinho → compra → etiqueta → rastreio
  Amazon/        SP-API (fase 2; requer RDT para dados do comprador)
  Bling/         Amazon via Bling (fase 1) e NF-e sob demanda
```

Cada integração é isolada: mudar uma API não derruba as outras. Todo canal
tem webhook **e** varredura periódica — o webhook nunca é 100%.

## Regras que valem desde o primeiro dia

- Pedido-filho de vendor (MVX no WooCommerce) **não** entra: só `parent = 0`.
- Status interno padronizado (`NOVO … ENTREGUE / CANCELADO / PROBLEMA`) e
  uma tabela de tradução por canal.
- Idempotência por `(canal, id_externo)`: reprocessar um webhook não duplica.
- Toda chamada externa registra log auditável (quem, quando, o quê, resposta).

## Identidade visual

Azul principal `#2E6BD6`, sidebar `#E9F1FA` com texto `#1E3A5F`, fundo papel
`#F6F5F2`, bordas `#E6E3DD`. Fontes Nunito Sans (interface) e IBM Plex Mono
(IDs, rastreios e valores). Telas de referência: canvas "Hub Editora".
