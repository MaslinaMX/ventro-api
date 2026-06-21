<?php

namespace App\Filament\Resources;

use App\Filament\Resources\TenantResource\Pages;
use App\Models\Tenant;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Actions\Action;
use Filament\Tables\Table;

class TenantResource extends Resource
{
    protected static ?string $model = Tenant::class;

    protected static ?string $navigationIcon = 'heroicon-o-building-storefront';

    protected static ?string $navigationLabel = 'Tenants';

    protected static ?string $modelLabel = 'Tenant';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                // ─── Info del negocio (readonly) ──────────────────────────
                Forms\Components\Section::make('Negocio')
                    ->columns(3)
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->label('Nombre')
                            ->disabled(),
                        Forms\Components\TextInput::make('owner_name')
                            ->label('Titular')
                            ->disabled(),
                        Forms\Components\TextInput::make('email')
                            ->label('Email')
                            ->disabled(),
                        Forms\Components\TextInput::make('slug')
                            ->label('Dominio')
                            ->disabled(),
                        Forms\Components\TextInput::make('id')
                            ->label('Tenant ID')
                            ->disabled(),
                        Forms\Components\TextInput::make('status')
                            ->label('Status tenant')
                            ->disabled(),
                    ]),

                // ─── Suscripción ──────────────────────────────────────────
                Forms\Components\Section::make('Costos y suscripción')
                    ->relationship('subscription')
                    ->columns(3)
                    ->schema([
                        Forms\Components\Select::make('plan')
                            ->label('Plan')
                            ->options([
                                'free_trial' => 'Free Trial',
                                'basic' => 'Basic',
                                'pro' => 'Pro',
                            ])
                            ->required(),
                        Forms\Components\Select::make('status')
                            ->label('Status suscripción')
                            ->options([
                                'trial' => 'Trial',
                                'active' => 'Activo',
                                'past_due' => 'Pago pendiente',
                                'cancelled' => 'Cancelado',
                            ])
                            ->required(),
                        Forms\Components\Select::make('period')
                            ->label('Periodo')
                            ->options([
                                'monthly' => 'Mensual',
                                'annual' => 'Anual',
                            ])
                            ->required(),
                        Forms\Components\TextInput::make('base_price')
                            ->label('Tarifa base')
                            ->numeric()
                            ->prefix('$')
                            ->required(),
                        Forms\Components\TextInput::make('included_branches')
                            ->label('Sucursales incluidas')
                            ->numeric()
                            ->required(),
                        Forms\Components\TextInput::make('extra_branch_cost')
                            ->label('Costo sucursal extra')
                            ->numeric()
                            ->prefix('$')
                            ->required(),
                        Forms\Components\DateTimePicker::make('trial_ends_at')
                            ->label('Trial vence')
                            ->nullable(),
                        Forms\Components\DateTimePicker::make('next_billing_at')
                            ->label('Próximo pago')
                            ->nullable(),
                        Forms\Components\Select::make('payment_method')
                            ->label('Método de pago')
                            ->options([
                                'spei' => 'SPEI',
                                'card' => 'Tarjeta',
                            ])
                            ->nullable(),
                    ]),

                // ─── Bloqueo ──────────────────────────────────────────────
                Forms\Components\Section::make('Control de estado')
                    ->relationship('subscription')
                    ->columns(2)
                    ->schema([
                        Forms\Components\Toggle::make('is_blocked')
                            ->label('Bloquear cuenta')
                            ->live(),
                        Forms\Components\TextInput::make('blocked_reason')
                            ->label('Motivo de bloqueo')
                            ->maxLength(255)
                            ->visible(fn (Forms\Get $get) => $get('is_blocked')),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Negocio')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('owner_name')
                    ->label('Titular')
                    ->searchable(),
                Tables\Columns\TextColumn::make('email')
                    ->searchable(),
                Tables\Columns\TextColumn::make('subscription.plan')
                    ->label('Plan')
                    ->badge()
                    ->color(fn ($state) => match ($state) {
                        'free_trial' => 'warning',
                        'basic' => 'info',
                        'pro' => 'success',
                        default => 'gray',
                    }),
                Tables\Columns\TextColumn::make('subscription.status')
                    ->label('Status')
                    ->badge()
                    ->color(fn ($state) => match ($state) {
                        'trial' => 'warning',
                        'active' => 'success',
                        'past_due' => 'danger',
                        'cancelled' => 'gray',
                        default => 'gray',
                    }),
                Tables\Columns\IconColumn::make('subscription.is_blocked')
                    ->label('Bloqueado')
                    ->boolean(),
                Tables\Columns\TextColumn::make('subscription.trial_ends_at')
                    ->label('Trial vence')
                    ->date()
                    ->sortable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Alta')
                    ->date()
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('plan')
                    ->relationship('subscription', 'plan')
                    ->options([
                        'free_trial' => 'Free Trial',
                        'basic' => 'Basic',
                        'pro' => 'Pro',
                    ]),
                Tables\Filters\TernaryFilter::make('is_blocked')
                    ->relationship('subscription', 'is_blocked')
                    ->label('Bloqueados'),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Action::make('bloquear')
                    ->label(fn ($record) => $record->subscription?->is_blocked ? 'Desbloquear' : 'Bloquear')
                    ->icon(fn ($record) => $record->subscription?->is_blocked
                        ? 'heroicon-o-lock-open'
                        : 'heroicon-o-lock-closed')
                    ->color(fn ($record) => $record->subscription?->is_blocked ? 'success' : 'danger')
                    ->requiresConfirmation()
                    ->action(function ($record) {
                        $sub = $record->subscription;
                        if (! $sub) {
                            return;
                        }
                        $sub->update([
                            'is_blocked' => ! $sub->is_blocked,
                            'blocked_at' => ! $sub->is_blocked ? now() : null,
                        ]);
                    }),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListTenants::route('/'),
            'edit' => Pages\EditTenant::route('/{record}/edit'),
        ];
    }
}
