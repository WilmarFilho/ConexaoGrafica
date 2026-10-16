<?php

namespace App\Filament\Pages\Auth;

use App\Models\User;
use App\Rules\SenhaDaPolitica;
use Filament\Auth\Pages\PasswordReset\ResetPassword as BaseResetPassword;
use Filament\Schemas\Components\Component;

/**
 * Redefinição pelo link do e-mail com a mesma política do perfil. A idade
 * mínima não vale aqui: o link é a saída para quem esqueceu a senha ou
 * acabou de ganhar uma conta.
 */
class ResetPassword extends BaseResetPassword
{
    protected function getPasswordFormComponent(): Component
    {
        return parent::getPasswordFormComponent()
            ->rule(fn () => new SenhaDaPolitica(User::where('email', $this->email)->first()));
    }
}
