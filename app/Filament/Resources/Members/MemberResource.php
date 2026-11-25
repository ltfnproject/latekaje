<?php

namespace App\Filament\Resources\Members;

use App\Filament\Resources\Members\Pages\CreateMember;
use App\Filament\Resources\Members\Pages\EditMember;
use App\Filament\Resources\Members\Pages\ListMembers;
use App\Models\Member;
use App\Models\User;
use App\Services\ActivityLogger;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class MemberResource extends Resource
{
    protected static ?string $model = Member::class;

    public static function getNavigationLabel(): string
    {
        return __('members.model_label');
    }

    protected static string | BackedEnum | null $navigationIcon = Heroicon::OutlinedUsers;

    protected static ?int $navigationSort = 12;

    public static function getNavigationGroup(): string|\UnitEnum|null
    {
        return __('common.nav_group_master');
    }

    public static function getModelLabel(): string
    {
        return __('members.model_label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('members.model_plural');
    }

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('code')
                ->label(__('members.code_label'))
                ->unique(ignoreRecord: true)
                ->maxLength(64)
                ->placeholder(__('members.code_placeholder')),

            TextInput::make('name')
                ->label(__('members.full_name_label'))
                ->required()
                ->maxLength(255),

            Select::make('member_type')
                ->label(__('members.member_type_label'))
                ->options(Member::memberTypeOptions())
                ->default(Member::TYPE_STUDENT)
                ->required(),

            TextInput::make('group')
                ->label(__('members.group_label'))
                ->required()
                ->maxLength(255)
                ->placeholder(__('members.group_placeholder')),

            DatePicker::make('birth_date')
                ->label(__('members.birth_date_label'))
                ->displayFormat('d/m/Y')
                ->maxDate(now())
                ->helperText(__('members.birth_date_helper')),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('code')
                    ->label(__('members.code_label'))
                    ->badge()
                    ->copyable()
                    ->searchable()
                    ->placeholder('-'),

                TextColumn::make('name')
                    ->label(__('members.name_label'))
                    ->searchable()
                    ->sortable(),

                TextColumn::make('member_type')
                    ->label(__('members.member_type_label'))
                    ->badge()
                    ->formatStateUsing(fn (?string $state): string => Member::memberTypeOptions()[$state ?? Member::TYPE_STUDENT] ?? __('members.type_student'))
                    ->color(fn (?string $state): string => match ($state) {
                        Member::TYPE_TEACHER => 'success',
                        Member::TYPE_EMPLOYEE => 'warning',
                        default => 'info',
                    })
                    ->sortable(),

                TextColumn::make('group')
                    ->label(__('members.group_label'))
                    ->badge()
                    ->searchable()
                    ->sortable(),

                TextColumn::make('birth_date')
                    ->label(__('members.birth_date_label'))
                    ->date('d/m/Y')
                    ->placeholder('-')
                    ->toggleable(),

                IconColumn::make('aktif')
                    ->label(__('members.active_label'))
                    ->boolean(),

                TextColumn::make('active_loans_count')
                    ->label(__('members.loans_label'))
                    ->counts('activeLoans')
                    ->badge()
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('member_type')
                    ->label(__('members.member_type_label'))
                    ->options(Member::memberTypeOptions()),
            ])
            ->actions([
                Action::make('buatAkun')
                    ->label(__('members.create_account'))
                    ->icon('heroicon-o-key')
                    ->color('info')
                    ->visible(fn ($record): bool => filled($record->code) && ! User::where('code', $record->code)->where('role', 'users')->exists())
                    ->action(function (Member $record): void {
                        $initialPassword = $record->defaultInitialPassword();

                        $user = User::updateOrCreate(
                            ['code' => $record->code, 'role' => 'users'],
                            [
                                'name' => $record->name,
                                'email' => $record->code.'@member.latekaje',
                                'password' => $initialPassword,
                                'member_id' => $record->id,
                                'is_active' => true,
                                'must_change_password' => true,
                            ]
                        );

                        ActivityLogger::log(
                            'member.account_created',
                            "Membuat akun login anggota {$record->name} (ID: {$record->code})",
                            $user,
                            ['member_id' => $record->id, 'member_type' => $record->member_type, 'uses_dob_password' => $record->birth_date !== null],
                        );

                        Notification::make()
                            ->title(__('members.account_created_title'))
                            ->body($record->birth_date
                                ? __('members.account_created_body_dob', ['code' => $user->code])
                                : __('members.account_created_body', ['code' => $user->code]))
                            ->success()
                            ->persistent()
                            ->send();
                    }),
                EditAction::make()->label(__('common.edit')),
                DeleteAction::make()->label(__('common.delete')),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListMembers::route('/'),
            'create' => CreateMember::route('/create'),
            'edit' => EditMember::route('/{record}/edit'),
        ];
    }
}
