<?php

namespace App\Http\Controllers;

use App\Filament\Pages\Integracoes;
use App\Integrations\Bling\BlingClient;
use App\Models\Channel;
use App\Models\SyncLog;
use Filament\Notifications\Notification;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Throwable;

/**
 * Autorização OAuth do Bling: /bling/connect manda para o Bling,
 * /bling/callback recebe o code e guarda os tokens. Só para quem está
 * logado no painel (o link de redirecionamento cadastrado no Bling é o callback).
 */
class BlingOAuthController extends Controller
{
    public function connect(Request $request): RedirectResponse
    {
        if (! BlingClient::isConfigured()) {
            Notification::make()->title('Preencha o Client ID e o Client Secret do Bling antes de conectar.')->danger()->send();

            return redirect(Integracoes::getUrl());
        }

        $state = Str::random(40);
        $request->session()->put('bling.oauth_state', $state);

        return redirect()->away(BlingClient::fromConfig()->authorizeUrl($state));
    }

    public function callback(Request $request): RedirectResponse
    {
        $expected = $request->session()->pull('bling.oauth_state');

        if (! $expected || ! hash_equals($expected, (string) $request->query('state', ''))) {
            Notification::make()->title('Autorização do Bling inválida (state não confere). Tente de novo.')->danger()->send();

            return redirect(Integracoes::getUrl());
        }

        if ($request->filled('error')) {
            Notification::make()->title('Bling recusou a autorização')->body((string) $request->query('error_description', $request->query('error')))->danger()->persistent()->send();

            return redirect(Integracoes::getUrl());
        }

        try {
            BlingClient::fromConfig()->exchangeCode((string) $request->query('code'));
            SyncLog::record(Channel::bySlug(Channel::BLING), SyncLog::MANUAL, 'settings.changed', null, 'Bling autorizado (OAuth)');
            Notification::make()->title('Bling conectado')->body('Os pedidos da Amazon passam a ser importados a cada 15 minutos.')->success()->send();
        } catch (Throwable $e) {
            SyncLog::record(Channel::bySlug(Channel::BLING), SyncLog::MANUAL, 'settings.tested', null, $e->getMessage(), [], 'error');
            Notification::make()->title('Falha ao conectar ao Bling')->body(mb_substr($e->getMessage(), 0, 300))->danger()->persistent()->send();
        }

        return redirect(Integracoes::getUrl());
    }
}
