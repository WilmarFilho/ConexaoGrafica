<?php

namespace App\Filament\Resources\Users;

use App\Filament\Resources\Users\Pages\ListUsers;
use App\Models\User;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Enums\FontWeight;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;

/**
 * Usuários do painel. Ninguém digita senha de outra pessoa: a conta nasce
 * com senha aleatória e a pessoa define a sua pelo link de redefinição,
 * enviado por e-mail daqui mesmo.
 */
class UserResource extends Resource
{
    protected static ?string $model = User::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUsers;

    protected static ?string $navigationLabel = 'Usuários';

    protected static ?string $modelLabel = 'usuário';

    protected static ?string $pluralModelLabel = 'usuários';

    protected static ?string $slug = 'usuarios';

    protected static ?int $navigationSort = 90;

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name')->label('Nome')->required()->maxLength(120),
            TextInput::make('email')->label('E-mail')->email()->required()->maxLength(190)->unique(ignoreRecord: true)
                ->helperText('Os avisos de integração e o link de senha vão para este endereço.'),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('name')
            ->columns([
                TextColumn::make('name')->label('Nome')->weight(FontWeight::Medium)->searchable(),
                TextColumn::make('email')->label('E-mail')->searchable()->copyable(),
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
                        $status = Password::broker()->sendResetLink(['email' => $record->email]);

                        $status === Password::RESET_LINK_SENT
                            ? Notification::make()->title('Link enviado para '.$record->email)->success()->send()
                            : Notification::make()->title('Não foi possível enviar')->body(__($status))->danger()->send();
                    }),
                EditAction::make()->label('Editar')->slideOver(),
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
                $status = Password::broker()->sendResetLink(['email' => $record->email]);
                if ($status === Password::RESET_LINK_SENT) {
                    Notification::make()->title('Usuário criado')->body('Link para definir a senha enviado a '.$record->email)->success()->send();
                } else {
                    Notification::make()->title('Usuário criado, mas o e-mail não saiu')->body(__($status).' Use "Enviar link de senha" para tentar de novo.')->warning()->persistent()->send();
                }
            })
            ->successNotification(null);
    }
}
