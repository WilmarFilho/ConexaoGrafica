<?php

namespace App\Filament\Resources\Users;

use App\Filament\Resources\Users\Pages\ListUsers;
use App\Models\SyncLog;
use App\Models\User;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Auth\Notifications\ResetPassword;
use Filament\Facades\Filament;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Enums\FontWeight;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use UnitEnum;

/**
 * Usuários do painel. Ninguém digita senha de outra pessoa: a conta nasce
 * com senha aleatória e a pessoa define a sua pelo link de redefinição,
 * enviado por e-mail daqui mesmo.
 *
 * Só administradores entram aqui. Cada pessoa tem um perfil (menor privilégio):
 * Administrador (usuários, integrações e auditoria) ou Expedição (pedidos e envio).
 */
class UserResource extends Resource
{
    protected static ?string $model = User::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUsers;

    protected static string|UnitEnum|null $navigationGroup = 'Administração';

    protected static ?string $navigationLabel = 'Usuários';

    protected static ?string $modelLabel = 'usuário';

    protected static ?string $pluralModelLabel = 'usuários';

    protected static ?string $slug = 'usuarios';

    protected static ?int $navigationSort = 90;

    protected static ?string $recordTitleAttribute = 'name';

    public static function canAccess(): bool
    {
        return (bool) auth()->user()?->isAdmin();
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name')->label('Nome')->required()->maxLength(120),
            TextInput::make('email')->label('E-mail')->email()->required()->maxLength(190)->unique(ignoreRecord: true)
                ->helperText('Os avisos de integração e o link de senha vão para este endereço.'),
            Select::make('role')->label('Perfil')
                ->options(User::ROLES)
                ->default(User::ROLE_EXPEDICAO)
                ->required()
                ->native(false)
                // ninguém rebaixa o próprio acesso (e fica sem administrador)
                ->disabled(fn (?User $record) => $record?->id === auth()->id())
                ->live()
                ->helperText('Expedição: pedidos, expedição e catálogo. Editor: cadastra livros e capítulos. Revisor: também aprova. Depósito: envia DOIs à Crossref. Vendedor: só as próprias vendas e repasses. Administrador: tudo, inclusive usuários, integrações e auditoria.'),
            Select::make('vendor_id')->label('Vendedor')
                ->relationship('vendor', 'name')
                ->searchable()->preload()
                ->visible(fn (Get $get) => $get('role') === User::ROLE_VENDEDOR)
                ->required(fn (Get $get) => $get('role') === User::ROLE_VENDEDOR)
                ->dehydrateStateUsing(fn ($state, Get $get) => $get('role') === User::ROLE_VENDEDOR ? $state : null)
                ->dehydratedWhenHidden()
                ->helperText('A conta só enxerga as vendas, comissões e repasses deste vendedor.'),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('name')
            ->columns([
                TextColumn::make('name')->label('Nome')->weight(FontWeight::Medium)->searchable(),
                TextColumn::make('email')->label('E-mail')->searchable()->copyable(),
                TextColumn::make('role')->label('Perfil')->badge()
                    ->formatStateUsing(fn (string $state, User $record) => (User::ROLES[$state] ?? $state).($record->vendor ? ' · '.$record->vendor->name : ''))
                    ->color(fn (string $state) => $state === User::ROLE_ADMIN ? 'primary' : 'gray'),
                TextColumn::make('situacao')->label('Situação')->badge()
                    ->state(fn (User $record) => match (true) {
                        $record->disabled_at !== null => 'Desativado',
                        $record->isLocked() => 'Bloqueado até '.$record->locked_until->format('H:i'),
                        default => 'Ativo',
                    })
                    ->color(fn (string $state) => $state === 'Ativo' ? 'success' : 'danger'),
                IconColumn::make('mfa')->label('2 fatores')->boolean()
                    ->state(fn (User $record) => filled($record->app_authentication_secret)),
                TextColumn::make('last_login_at')->label('Último acesso')->since()->placeholder('nunca'),
                TextColumn::make('created_at')->label('Criado')->since(),
            ])
            ->recordActions([
                Action::make('enviar_senha')
                    ->label('Enviar link de senha')
                    ->icon(Heroicon::OutlinedKey)
                    ->color('gray')
                    ->requiresConfirmation()
                    ->modalHeading(fn (User $record) => 'Enviar link de definição de senha para '.$record->email.'?')
                    ->modalDescription('A pessoa recebe um e-mail para escolher a própria senha. O link vale por 60 minutos.')
                    ->action(function (User $record) {
                        self::sendResetLink($record)
                            ? Notification::make()->title('Link enviado para '.$record->email)->success()->send()
                            : Notification::make()->title('Não foi possível enviar o e-mail')->danger()->send();
                    }),
                Action::make('desbloquear')
                    ->label('Desbloquear')
                    ->icon(Heroicon::OutlinedLockOpen)
                    ->color('warning')
                    ->visible(fn (User $record) => $record->isLocked())
                    ->requiresConfirmation()
                    ->action(function (User $record) {
                        $record->forceFill(['locked_until' => null, 'failed_logins' => 0])->saveQuietly();
                        SyncLog::record(null, SyncLog::MANUAL, 'user.unlocked', $record, 'Conta '.$record->email.' desbloqueada');
                        Notification::make()->title('Conta desbloqueada')->success()->send();
                    }),
                Action::make('desativar')
                    ->label('Desativar')
                    ->icon(Heroicon::OutlinedNoSymbol)
                    ->color('danger')
                    ->visible(fn (User $record) => $record->disabled_at === null && $record->id !== auth()->id())
                    ->requiresConfirmation()
                    ->modalHeading(fn (User $record) => 'Desativar '.$record->email.'?')
                    ->modalDescription('A pessoa perde o acesso na hora (inclusive sessões abertas). Use no desligamento ou mudança de função.')
                    ->action(function (User $record) {
                        $record->forceFill(['disabled_at' => now()])->saveQuietly();
                        SyncLog::record(null, SyncLog::MANUAL, 'user.disabled', $record, 'Usuário '.$record->email.' desativado');
                        Notification::make()->title('Usuário desativado')->success()->send();
                    }),
                Action::make('reativar')
                    ->label('Reativar')
                    ->icon(Heroicon::OutlinedArrowPath)
                    ->color('gray')
                    ->visible(fn (User $record) => $record->disabled_at !== null)
                    ->requiresConfirmation()
                    ->action(function (User $record) {
                        $record->forceFill(['disabled_at' => null])->saveQuietly();
                        SyncLog::record(null, SyncLog::MANUAL, 'user.enabled', $record, 'Usuário '.$record->email.' reativado');
                        Notification::make()->title('Usuário reativado')->success()->send();
                    }),
                EditAction::make()->label('Editar')->slideOver()
                    ->after(function (User $record) {
                        if ($record->wasChanged('role')) {
                            SyncLog::record(null, SyncLog::MANUAL, 'user.role_changed', $record, 'Perfil de '.$record->email.' alterado para '.$record->roleLabel());
                        }
                    }),
                DeleteAction::make()
                    ->label('Remover')
                    ->hidden(fn (User $record) => $record->id === auth()->id()),
            ])
            ->emptyStateHeading('Nenhum usuário');
    }

    public static function getPages(): array
    {
        return [
            'index' => ListUsers::route('/'),
        ];
    }

    /** Ação de criação: senha aleatória nunca vista e link de senha já enviado. */
    public static function createAction(): CreateAction
    {
        return CreateAction::make()
            ->label('Novo usuário')
            ->slideOver()
            ->mutateDataUsing(function (array $data) {
                $data['password'] = Str::random(40);

                return $data;
            })
            ->after(function (User $record) {
                if (self::sendResetLink($record)) {
                    Notification::make()->title('Usuário criado')->body('Link para definir a senha enviado a '.$record->email)->success()->send();
                } else {
                    Notification::make()->title('Usuário criado, mas o e-mail não saiu')->body('Use "Enviar link de senha" para tentar de novo.')->warning()->persistent()->send();
                }
            })
            ->successNotification(null);
    }

    /**
     * Mesmo e-mail e mesma página de redefinição que o "Esqueci a senha" do painel:
     * o link precisa apontar para a rota do Filament, não para a rota padrão do Laravel.
     */
    public static function sendResetLink(User $user): bool
    {
        try {
            $token = Password::broker()->createToken($user);
            $notification = new ResetPassword($token);
            $notification->url = Filament::getResetPasswordUrl($token, $user);
            $user->notify($notification);

            return true;
        } catch (\Throwable) {
            return false;
        }
    }
}
