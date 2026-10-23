<?php

namespace App\Filament\Pages\Auth;

use Filament\Auth\Pages\Login as BaseLogin;
use Illuminate\Contracts\Support\Htmlable;

/**
 * Login do painel com o layout do redesign: metade editorial à esquerda
 * (view filament.auth.login-hero, render hook do AdminPanelProvider) e o
 * formulário à direita. O desafio de dois fatores continua nesta mesma página.
 */
class Login extends BaseLogin
{
    public function getSubheading(): string|Htmlable|null
    {
        if (filled($this->userUndertakingMultiFactorAuthentication)) {
            return parent::getSubheading();
        }

        return 'Use o e-mail da sua conta no Hub.';
    }
}
