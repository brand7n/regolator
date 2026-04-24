<?php

namespace App\Filament\Resources;

use App\Filament\Resources\UserResource\Pages;
use App\Models\User;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use libphonenumber\NumberParseException;
use libphonenumber\PhoneNumberFormat;
use libphonenumber\PhoneNumberUtil;

class UserResource extends Resource
{
    protected static ?string $model = User::class;

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('name')->required(),
                Forms\Components\TextInput::make('nerd_name')->label('Nerd Name'),
                Forms\Components\TextInput::make('email')->email()->required(),
                Forms\Components\TextInput::make('phone')
                    ->tel()
                    ->dehydrateStateUsing(function (?string $state): ?string {
                        if (! $state) {
                            return null;
                        }

                        try {
                            $phoneUtil = PhoneNumberUtil::getInstance();
                            $parsed = $phoneUtil->parse($state, 'US');
                            if ($phoneUtil->isValidNumber($parsed)) {
                                return $phoneUtil->format($parsed, PhoneNumberFormat::E164);
                            }
                        } catch (NumberParseException) {
                        }

                        return $state;
                    }),
                Forms\Components\TextInput::make('kennel'),
                Forms\Components\Select::make('shirt_size')
                    ->options([
                        'XS' => 'XS',
                        'SM' => 'SM',
                        'MD' => 'MD',
                        'LG' => 'LG',
                        'XL' => 'XL',
                        '2XL' => '2XL',
                        '3XL' => '3XL',
                    ]),
                Forms\Components\Toggle::make('short_bus')->label('Short Bus'),
                Forms\Components\Textarea::make('comment')->rows(3),
                Forms\Components\Toggle::make('is_admin')->label('Admin'),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('email')->searchable()->sortable(),
                Tables\Columns\IconColumn::make('email_verified_at')
                    ->label('Verified')
                    ->boolean()
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('email_verified_at')
                    ->label('Email Verified')
                    ->nullable(),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->defaultPaginationPageOption(100)
            ->paginated([50, 100, 'all']);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListUsers::route('/'),
            'create' => Pages\CreateUser::route('/create'),
            'edit' => Pages\EditUser::route('/{record}/edit'),
        ];
    }
}
