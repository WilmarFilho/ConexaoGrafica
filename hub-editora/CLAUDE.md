# Hub Editora — orientações para sessões futuras

- **Não instale PHP nem Composer na máquina local.** Todo o ciclo roda em Docker:
  `docker compose up -d` e `docker compose exec app php artisan ...`.
  Composer também: `docker compose exec app composer ...`.
- **Alvo de produção é PHP 8.3** (EA-PHP do VPS cPanel). O `composer.json`
  fixa `config.platform.php = 8.3.33`; não suba isso. Não existe PHP 8.4 no servidor.
- Sem Node, Redis ou build no servidor: fila = `database`, agendador = cron do
  cPanel, assets do Filament já vêm compilados no vendor. Identidade visual é
  CSS puro em `resources/css/hub-theme.css`, registrado como asset.
- Framework: Laravel 13 + Filament 4. Painel em `/admin`.
- Regras de domínio que não estão no código de forma óbvia: veja `README.md`
  (pedidos-filho de vendor ficam de fora; idempotência por canal + id externo;
  status interno padronizado em `App\Enums\OrderStatus`).
- Segredos só no `.env` (produção fora do docroot). Nunca em código ou no banco.
- Telas de referência: canvas de design "Hub Editora" (sidebar azul-claro
  `#E9F1FA`, azul principal `#2E6BD6`, fundo papel `#F6F5F2`).
