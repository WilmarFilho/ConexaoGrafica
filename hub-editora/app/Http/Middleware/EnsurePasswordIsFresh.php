<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Filament\Notifications\Notification;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Senha com mais de um ano: só deixa passar para o perfil (trocar) ou sair. */
class EnsurePasswordIsFresh
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user instanceof User) {
            if ($user->password_changed_at === null) {
                $user->forceFill(['password_changed_at' => now()])->saveQuietly();
            } elseif ($user->passwordIsExpired()
                && ! $request->routeIs('filament.admin.auth.profile', 'filament.admin.auth.logout')
                && ! $request->is('livewire/*')) {
                Notification::make()
                    ->title('Sua senha tem mais de um ano')
                    ->body('Por política de segurança, troque a senha para continuar.')
                    ->warning()
                    ->persistent()
                    ->send();

                return redirect()->route('filament.admin.auth.profile');
            }
        }

        return $next($request);
    }
}
