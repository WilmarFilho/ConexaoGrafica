<?php

namespace App\Filament\Pages\Auth;

use App\Rules\SenhaDaPolitica;
use Filament\Auth\Pages\EditProfile as BaseEditProfile;
use Filament\Schemas\Components\Component;

/** Perfil com a política de senha completa (nome/e-mail, histórico e idade mínima). */
class EditProfile extends BaseEditProfile
{
    protected function getPasswordFormComponent(): Component
    {
        return parent::getPasswordFormComponent()
            ->rule(fn () => new SenhaDaPolitica($this->getUser(), checkMinAge: true));
    }
}
