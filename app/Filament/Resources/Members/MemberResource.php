<?php

namespace App\Filament\Resources\Members;

use App\Filament\Resources\Members\Pages\CreateMember;
use App\Filament\Resources\Members\Pages\EditMember;
use App\Filament\Resources\Members\Pages\ListMembers;
use App\Models\Group;
use App\Models\Member;
use App\Models\User;
use App\Services\ActivityLogger;
use App\Services\MemberAccountSync;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

class MemberResource extends Resource
{
    protected static ?string $model = Member::class;

    public static function getNavigationLabel(): string
    {
        return __('members.model_label');
    }

    protected static string | BackedEnum | null $navigationIcon = Heroicon::OutlinedUsers;

    protected static ?int $navigationSort = 12;

    public static function getNavigationGroup(): string | \UnitEnum | null
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
                ->live()
                ->required()
                ->afterStateUpdated(function (Set $set, Get $get, ?string $state): void {
                    $currentGroup = (string) ($get('group') ?? '');
                    if ($state === Member::TYPE_TEACHER && ($currentGroup === '' || $currentGroup === 'KARYAWAN')) {
                        $set('group', MemberAccountSync::defaultGroupForType(Member::TYPE_TEACHER));
                    } elseif ($state === Member::TYPE_EMPLOYEE && ($currentGroup === '' || $currentGroup === 'GURU')) {
                        $set('group', MemberAccountSync::defaultGroupForType(Member::TYPE_EMPLOYEE));
                    }
                }),

            Select::make('group')
                ->label(__('members.group_label'))
                ->required()
                ->searchable()
                ->preload()
                ->options(function (Get $get): array {
                    $options = Group::query()->orderBy('label')->pluck('label', 'label')->all();
                    $current = trim((string) ($get('group') ?? ''));
                    if ($current !== '' && ! array_key_exists($current, $options)) {
                        $options[$current] = $current;
                    }

                    return $options;
                })
                ->createOptionForm([
                    TextInput::make('label')
                        ->label(__('members.group_label'))
                        ->required()
                        ->maxLength(120),
                ])
                ->createOptionUsing(fn (array $data): string => MemberAccountSync::ensureGroupExists((string) ($data['label'] ?? '')) ?? ''),

            DatePicker::make('birth_date')
                ->label(__('members.birth_date_label'))
                ->displayFormat('d/m/Y')
                ->maxDate(now())
                ->helperText(__('members.birth_date_helper')),

            Toggle::make('aktif')
                ->label(__('members.active_label'))
                ->default(true)
                ->helperText(__('members.active_helper')),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with('user'))
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

                IconColumn::make('has_account')
                    ->label(__('members.has_account_label'))
                    ->state(fn (Member $record): bool => self::findLinkedUser($record) !== null)
                    ->boolean(),

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

                SelectFilter::make('group')
                    ->label(__('members.group_label'))
                    ->options(fn (): array => Group::query()->orderBy('label')->pluck('label', 'label')->all()),
            ])
            ->actions([
                Action::make('buatAkun')
                    ->label(__('members.create_account'))
                    ->icon('heroicon-o-key')
                    ->color('info')
                    ->visible(fn (Member $record): bool => filled($record->code) && self::findLinkedUser($record) === null)
                    ->action(function (Member $record): void {
                        $user = MemberAccountSync::provisionAccountForMember($record);

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

                Action::make('resetPasswordAwal')
                    ->label(__('members.reset_password'))
                    ->icon('heroicon-o-arrow-path')
                    ->color('warning')
                    ->requiresConfirmation()
                    ->visible(fn (Member $record): bool => in_array(auth()->user()?->role, ['superadmin', 'admin'], true)
                        && filled($record->code)
                        && self::findLinkedUser($record) !== null)
                    ->action(function (Member $record): void {
                        abort_unless(in_array(auth()->user()?->role, ['superadmin', 'admin'], true), 403);

                        $user = MemberAccountSync::provisionAccountForMember(
                            $record,
                            explicitPassword: null,
                            forcePasswordChange: true,
                            resetExistingPassword: true,
                        );

                        ActivityLogger::log(
                            'member.password_reset',
                            "Mereset password awal akun {$record->name} (ID: {$record->code})",
                            $user,
                            ['member_id' => $record->id, 'uses_dob_password' => $record->birth_date !== null],
                        );

                        Notification::make()
                            ->title(__('members.password_reset_title'))
                            ->body($record->birth_date
                                ? __('members.account_created_body_dob', ['code' => $user->code])
                                : __('members.account_created_body', ['code' => $user->code]))
                            ->success()
                            ->send();
                    }),

                EditAction::make()->label(__('common.edit')),
                DeleteAction::make()->label(__('common.delete')),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    BulkAction::make('buatAkunMassal')
                        ->label(__('members.bulk_create_accounts'))
                        ->icon('heroicon-o-key')
                        ->color('info')
                        ->requiresConfirmation()
                        ->action(function (Collection $records): void {
                            $created = 0;

                            foreach ($records as $member) {
                                if (! $member instanceof Member || blank($member->code)) {
                                    continue;
                                }

                                if (self::findLinkedUser($member) !== null) {
                                    continue;
                                }

                                MemberAccountSync::provisionAccountForMember($member);
                                $created++;
                            }

                            if ($created > 0) {
                                ActivityLogger::log(
                                    'member.bulk_accounts_created',
                                    "Membuat akun login massal untuk {$created} anggota",
                                    null,
                                    ['count' => $created],
                                );
                            }

                            Notification::make()
                                ->title(__('members.bulk_accounts_created_title', ['count' => $created]))
                                ->success()
                                ->send();
                        })
                        ->deselectRecordsAfterCompletion(),
                ]),
            ]);
    }

    private static function findLinkedUser(Member $record): ?User
    {
        if ($record->relationLoaded('user') && $record->user) {
            return $record->user;
        }

        if (filled($record->code)) {
            return User::where('member_id', $record->id)
                ->orWhere(fn ($q) => $q->where('role', 'users')->where('code', $record->code))
                ->first();
        }

        return User::where('member_id', $record->id)->first();
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
