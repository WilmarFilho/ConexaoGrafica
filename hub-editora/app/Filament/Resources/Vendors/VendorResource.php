<?php

namespace App\Filament\Resources\Vendors;

use App\Filament\Resources\Vendors\Pages\CreateVendor;
use App\Filament\Resources\Vendors\Pages\EditVendor;
use App\Filament\Resources\Vendors\Pages\ListVendors;
use App\Filament\Resources\Vendors\RelationManagers\CommissionsRelationManager;
use App\Filament\Resources\Vendors\RelationManagers\PayoutsRelationManager;
use App\Filament\Resources\Vendors\RelationManagers\ProductsRelationManager;
use App\Models\Vendor;
use App\Models\VendorCommission;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Enums\FontWeight;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;
use UnitEnum;

/**
 * Vendedores do multivendor (autores e parceiros). Só administradores: aqui
 * ficam dados de repasse (CPF/CNPJ, chave PIX, banco).
 */
class VendorResource extends Resource
{
    protected static ?string $model = Vendor::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBuildingStorefront;

    protected static string|UnitEnum|null $navigationGroup = 'Vendedores';

    protected static ?string $navigationLabel = 'Vendedores';

    protected static ?string $modelLabel = 'vendedor';

    protected static ?string $pluralModelLabel = 'vendedores';

    protected static ?string $slug = 'vendedores';

    protected static ?int $navigationSort = 50;

    protected static ?string $recordTitleAttribute = 'name';

    public static function canAccess(): bool
    {
        return (bool) auth()->user()?->isAdmin();
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->columns(3)
            ->components([
                Grid::make(1)->columnSpan(2)->schema([
                    Section::make('Identificação')
                        ->columns(2)
                        ->schema([
                            TextInput::make('name')->label('Nome público')->required()->maxLength(255)
                                ->helperText('Como aparece nas telas (autor, selo, parceiro).'),
                            TextInput::make('legal_name')->label('Nome completo / razão social')->maxLength(255)
                                ->helperText('Titular do repasse.'),
                            TextInput::make('document')->label('CPF ou CNPJ')->maxLength(20)
                                ->rule(fn () => function (string $attribute, $value, \Closure $fail) {
                                    $digits = preg_replace('/\D/', '', (string) $value);
                                    if ($digits !== '' && ! in_array(strlen($digits), [11, 14], true)) {
                                        $fail('Informe 11 dígitos (CPF) ou 14 (CNPJ).');
                                    }
                                }),
                            TextInput::make('email')->label('E-mail')->email()->maxLength(190),
                            TextInput::make('phone')->label('Telefone')->tel()->maxLength(30),
                        ]),

                    Section::make('Repasse')
                        ->description('Para onde vai o dinheiro. Guardado cifrado.')
                        ->columns(2)
                        ->schema([
                            Select::make('payout_method')->label('Forma')->options(Vendor::PAYOUT_METHODS)->native(false),
                            Textarea::make('payout_details')->label('Chave PIX ou dados bancários')->rows(3)->maxLength(1000)
                                ->helperText('Ex.: chave PIX; ou banco, agência, conta e tipo.'),
                        ]),

                    Section::make('Observações')
                        ->collapsed(fn (?Vendor $record) => blank($record?->notes))
                        ->schema([
                            Textarea::make('notes')->hiddenLabel()->rows(3)->maxLength(5000),
                        ]),
                ]),

                Grid::make(1)->columnSpan(1)->schema([
                    Section::make('Comissão')
                        ->schema([
                            TextInput::make('commission_pct')->label('Parte do vendedor')
                                ->numeric()->minValue(0)->maxValue(100)->step(0.01)->suffix('%')
                                ->default(Vendor::DEFAULT_COMMISSION_PCT)->required()
                                ->helperText('Sobre o valor dos itens (sem frete, já com desconto). Cada livro pode ter um % próprio.'),
                            DatePicker::make('commissions_start_at')->label('Comissões a partir de')
                                ->helperText('Pedidos anteriores não geram comissão no hub (já acertados fora dele).'),
                            Toggle::make('active')->label('Ativo')->default(true),
                        ]),

                    Section::make('Saldo')
                        ->schema([
                            TextEntry::make('saldo_disponivel')->label('A repassar')
                                ->state(fn (?Vendor $record) => $record ? Vendor::money($record->availableCents()) : null)
                                ->weight(FontWeight::Bold),
                            TextEntry::make('saldo_pendente')->label('Aguardando pagamento do cliente')
                                ->state(fn (?Vendor $record) => $record ? Vendor::money($record->pendingCents()) : null),
                            TextEntry::make('saldo_pago')->label('Já repassado')
                                ->state(fn (?Vendor $record) => $record ? Vendor::money($record->paidCents()) : null),
                            TextEntry::make('acesso')->label('Acesso ao portal')
                                ->state(fn (?Vendor $record) => $record?->users()->pluck('email')->implode(', ') ?: null)
                                ->placeholder('Ninguém. Crie um usuário com perfil Vendedor em Usuários.'),
                        ])
                        ->hiddenOn('create'),
                ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        $open = fn (Builder $q) => $q->where('status', VendorCommission::AVAILABLE)->whereNull('payout_id');

        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query
                ->withCount('products')
                ->withSum(['commissions as available_cents' => $open], 'amount_cents')
                ->withSum(['commissions as pending_cents' => fn (Builder $q) => $q->where('status', VendorCommission::PENDING)], 'amount_cents')
                ->withSum(['commissions as paid_cents' => fn (Builder $q) => $q->where('status', VendorCommission::PAID)], 'amount_cents'))
            ->defaultSort('name')
            ->columns([
                TextColumn::make('name')->label('Vendedor')->weight(FontWeight::Medium)
                    ->description(fn (Vendor $record) => $record->email)
                    ->searchable(['name', 'legal_name', 'email', 'document'])->sortable(),
                IconColumn::make('notes')->label('')
                    ->icon(fn (?string $state) => filled($state) ? Heroicon::OutlinedChatBubbleBottomCenterText : null)
                    ->color('warning')
                    ->tooltip(fn (Vendor $record) => filled($record->notes) ? Str::limit($record->notes, 300) : null),
                TextColumn::make('commission_pct')->label('Comissão')->alignEnd()
                    ->formatStateUsing(fn ($state) => number_format((float) $state, 2, ',', '.').'%')
                    ->extraAttributes(['class' => 'hub-mono']),
                TextColumn::make('products_count')->label('Livros')->badge()->color('gray'),
                TextColumn::make('available_cents')->label('A repassar')
                    ->formatStateUsing(fn ($state) => Vendor::money((int) $state))->placeholder(Vendor::money(0))
                    ->extraAttributes(['class' => 'hub-mono'])->alignEnd()->sortable(),
                TextColumn::make('pending_cents')->label('Aguardando pagamento')
                    ->formatStateUsing(fn ($state) => Vendor::money((int) $state))->placeholder(Vendor::money(0))
                    ->extraAttributes(['class' => 'hub-mono'])->alignEnd()->toggleable(),
                TextColumn::make('paid_cents')->label('Repassado')
                    ->formatStateUsing(fn ($state) => Vendor::money((int) $state))->placeholder(Vendor::money(0))
                    ->extraAttributes(['class' => 'hub-mono'])->alignEnd()->toggleable(),
                ToggleColumn::make('active')->label('Ativo'),
            ])
            ->filters([
                TernaryFilter::make('active')->label('Ativo'),
            ])
            ->recordActions([
                EditAction::make()->label('Abrir'),
            ])
            ->emptyStateHeading('Nenhum vendedor')
            ->emptyStateDescription('Cadastre os autores e parceiros que vendem pela editora e ligue os livros a eles.');
    }

    public static function getRelations(): array
    {
        return [
            ProductsRelationManager::class,
            CommissionsRelationManager::class,
            PayoutsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListVendors::route('/'),
            'create' => CreateVendor::route('/novo'),
            'edit' => EditVendor::route('/{record}'),
        ];
    }
}
