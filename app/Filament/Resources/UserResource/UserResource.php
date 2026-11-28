<?php

namespace App\Filament\Resources\UserResource;

use App\Filament\Resources\UserResource\Pages\CreateUser;
use App\Filament\Resources\UserResource\Pages\EditUser;
use App\Filament\Resources\UserResource\Pages\ListUsers;
use App\Models\Group;
use App\Models\Member;
use App\Models\User;
use App\Services\ActivityLogger;
use App\Services\MemberAccountSync;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Hash;

class UserResource extends Resource
{
    protected static ?string $model = User::class;

    protected static string | BackedEnum | null $navigationIcon = Heroicon::OutlinedShieldCheck;

    protected static ?int $navigationSort = 30;

    public static function getNavigationGroup(): string | \UnitEnum | null
    {
        return __('common.nav_group_system');
    }

    public static function getNavigationLabel(): string
    {
        return __('common.nav_users');
    }

    public static function getModelLabel(): string
    {
        return __('users.model_label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('users.model_plural');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make(__('users.section_account'))
                ->columns(2)
                ->schema([
                    TextInput::make('name')
                        ->label(__('users.name_label'))
                        ->required()
                        ->maxLength(255),

                    TextInput::make('email')
                        ->label(__('users.email_label'))
                        ->email()
                        ->required()
                        ->unique(ignoreRecord: true)
                        ->maxLength(255),

                    TextInput::make('code')
                        ->label(__('users.code_label'))
                        ->unique(ignoreRecord: true)
                        ->maxLength(64)
                        ->live(onBlur: true)
                        ->placeholder(__('users.code_placeholder')),

                    Select::make('role')
                        ->label(__('users.role_label'))
                        ->required()
                        ->default('users')
                        ->live()
                        ->options([
                            'superadmin' => __('users.role_superadmin'),
                            'admin' => __('users.role_admin'),
                            'staff' => __('users.role_staff'),
                            'assistant' => __('users.role_assistant'),
                            'users' => __('users.role_users'),
                        ]),

                    Toggle::make('is_active')
                        ->label(__('users.account_active_label'))
                        ->default(true)
                        ->helperText(__('users.account_active_helper')),

                    Toggle::make('must_change_password')
                        ->label(__('users.must_change_password_label'))
                        ->default(false)
                        ->helperText(__('users.must_change_password_helper')),

                    TextInput::make('password')
                        ->label(__('users.new_password_label'))
                        ->password()
                        ->revealable()
                        ->dehydrated(fn ($state): bool => filled($state))
                        ->dehydrateStateUsing(fn ($state): ?string => filled($state) ? Hash::make($state) : null)
                        ->required(fn (string $context, Get $get): bool => $context === 'create'
                            && ($get('role') !== 'users' || (blank($get('member_birth_date')) && blank($get('code')))))
                        ->helperText(fn (string $context, Get $get): string => $context === 'edit'
                            ? __('users.password_helper_edit')
                            : ($get('role') === 'users' ? __('users.password_helper_borrower_create') : ''))
                        ->maxLength(255)
                        ->columnSpanFull(),
                ]),

            Section::make(__('users.section_borrower_profile'))
                ->description(__('users.section_borrower_profile_desc'))
                ->columns(2)
                ->visible(fn (Get $get): bool => $get('role') === 'users')
                ->schema([
                    Select::make('member_id')
                        ->label(__('users.link_member_label'))
                        ->placeholder(__('users.link_member_placeholder'))
                        ->searchable()
                        ->preload()
                        ->live()
                        ->options(fn () => Member::query()
                            ->orderBy('name')
                            ->get()
                            ->mapWithKeys(fn (Member $m): array => [$m->id => $m->label()])
                            ->all())
                        ->afterStateUpdated(function (Set $set, $state): void {
                            if (blank($state)) {
                                return;
                            }

                            $member = Member::find($state);
                            if (! $member) {
                                return;
                            }

                            $set('name', $member->name);
                            if (filled($member->code)) {
                                $set('code', $member->code);
                            }
                            $set('member_type', $member->member_type ?? Member::TYPE_STUDENT);
                            $set('member_group', $member->group);
                            $set('member_birth_date', $member->birth_date?->format('Y-m-d'));
                        })
                        ->helperText(__('users.link_member_helper'))
                        ->columnSpanFull(),

                    Select::make('member_type')
                        ->label(__('members.member_type_label'))
                        ->options(Member::memberTypeOptions())
                        ->default(Member::TYPE_STUDENT)
                        ->live()
                        ->required(fn (Get $get): bool => $get('role') === 'users')
                        ->afterStateUpdated(function (Set $set, Get $get, ?string $state): void {
                            $currentGroup = (string) ($get('member_group') ?? '');
                            if ($state === Member::TYPE_TEACHER && ($currentGroup === '' || $currentGroup === 'KARYAWAN')) {
                                $set('member_group', MemberAccountSync::defaultGroupForType(Member::TYPE_TEACHER));
                            } elseif ($state === Member::TYPE_EMPLOYEE && ($currentGroup === '' || $currentGroup === 'GURU')) {
                                $set('member_group', MemberAccountSync::defaultGroupForType(Member::TYPE_EMPLOYEE));
                            }
                        }),

                    Select::make('member_group')
                        ->label(__('members.group_label'))
                        ->searchable()
                        ->preload()
                        ->required(fn (Get $get): bool => $get('role') === 'users')
                        ->options(function (Get $get): array {
                            $options = Group::query()->orderBy('label')->pluck('label', 'label')->all();
                            $current = trim((string) ($get('member_group') ?? ''));
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

                    DatePicker::make('member_birth_date')
                        ->label(__('members.birth_date_label'))
                        ->displayFormat('d/m/Y')
                        ->maxDate(now())
                        ->live()
                        ->helperText(__('members.birth_date_helper'))
                        ->columnSpanFull(),
                ]),

            Section::make(__('users.section_acl'))
                ->collapsed()
                ->visible(fn (): bool => (bool) auth()->user()?->isSuperadmin())
                ->schema([
                    CheckboxList::make('permissions.allow')
                        ->label(__('users.perm_allow_label'))
                        ->options(\App\Support\Acl::labels())
                        ->columns(2),

                    CheckboxList::make('permissions.deny')
                        ->label(__('users.perm_deny_label'))
                        ->options(\App\Support\Acl::labels())
                        ->columns(2),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with('member'))
            ->defaultSort(fn (Builder $query, string $direction): Builder => self::applyDefaultSort($query, $direction))
            ->columns([
                TextColumn::make('name')
                    ->label(__('users.name_label'))
                    ->searchable()
                    ->sortable(),

                TextColumn::make('email')
                    ->label(__('users.email_label'))
                    ->searchable()
                    ->sortable()
                    ->toggleable(),

                TextColumn::make('code')
                    ->label(__('users.code_label'))
                    ->badge()
                    ->color('gray')
                    ->copyable()
                    ->searchable()
                    ->sortable(query: fn (Builder $query, string $direction): Builder => self::applyCodeSort($query, $direction))
                    ->placeholder('-'),

                TextColumn::make('role')
                    ->label(__('users.role_label'))
                    ->badge()
                    ->sortable(query: fn (Builder $query, string $direction): Builder => self::applyRoleSort($query, $direction))
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'superadmin' => __('users.role_superadmin'),
                        'admin' => __('users.role_admin'),
                        'staff' => __('users.role_staff'),
                        'assistant' => __('users.role_assistant'),
                        default => __('users.role_users'),
                    })
                    ->color(fn (string $state): string => match ($state) {
                        'superadmin' => 'danger',
                        'admin' => 'warning',
                        'staff' => 'info',
                        'assistant' => 'success',
                        default => 'gray',
                    }),

                TextColumn::make('member.member_type')
                    ->label(__('members.member_type_label'))
                    ->badge()
                    ->state(fn (User $record): ?string => $record->isBorrower() ? ($record->resolvedMember()?->member_type ?? Member::TYPE_STUDENT) : null)
                    ->formatStateUsing(fn (?string $state): string => $state ? (Member::memberTypeOptions()[$state] ?? __('members.type_student')) : '-')
                    ->color(fn (?string $state): string => match ($state) {
                        Member::TYPE_TEACHER => 'success',
                        Member::TYPE_EMPLOYEE => 'warning',
                        Member::TYPE_STUDENT => 'info',
                        default => 'gray',
                    })
                    ->placeholder('-'),

                TextColumn::make('member.group')
                    ->label(__('members.group_label'))
                    ->badge()
                    ->color('gray')
                    ->state(fn (User $record): ?string => $record->resolvedMember()?->group)
                    ->searchable(query: fn (Builder $query, string $search): Builder => $query->whereHas('member', fn (Builder $m) => $m->where('group', 'like', "%{$search}%")))
                    ->placeholder('-'),

                IconColumn::make('is_active')
                    ->label(__('common.active'))
                    ->boolean(),

                TextColumn::make('created_at')
                    ->label(__('users.created_label'))
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('role')
                    ->label(__('users.role_label'))
                    ->options([
                        'superadmin' => __('users.role_superadmin'),
                        'admin' => __('users.role_admin'),
                        'staff' => __('users.role_staff'),
                        'assistant' => __('users.role_assistant'),
                        'users' => __('users.role_users'),
                    ])
                    ->placeholder(__('common.all')),

                SelectFilter::make('member_type')
                    ->label(__('members.member_type_label'))
                    ->options(Member::memberTypeOptions())
                    ->placeholder(__('common.all'))
                    ->query(function (Builder $query, array $data): Builder {
                        $value = $data['value'] ?? null;
                        if (blank($value)) {
                            return $query;
                        }

                        return $query->where('role', 'users')
                            ->whereHas('member', fn (Builder $m): Builder => $m->where('member_type', $value));
                    }),
            ])
            ->recordActions([
                Action::make('toggleAktif')
                    ->label(fn ($record): string => $record->is_active ? __('users.deactivate') : __('users.activate'))
                    ->icon(fn ($record): string => $record->is_active ? 'heroicon-o-no-symbol' : 'heroicon-o-check-circle')
                    ->color(fn ($record): string => $record->is_active ? 'danger' : 'success')
                    ->requiresConfirmation()
                    ->visible(fn (): bool => (bool) auth()->user()?->isSuperadmin())
                    ->action(function ($record): void {
                        abort_unless((bool) auth()->user()?->isSuperadmin(), 403);

                        $newState = ! $record->is_active;
                        $record->update(['is_active' => $newState]);

                        if ($newState) {
                            ActivityLogger::log(
                                'user.activated',
                                "Mengaktifkan kembali akun {$record->name} ({$record->email})",
                                $record,
                                ['target_role' => $record->role],
                            );
                        }

                        Notification::make()
                            ->title($record->is_active ? __('users.activated_title') : __('users.deactivated_title'))
                            ->success()
                            ->send();
                    }),
                EditAction::make()
                    ->label(__('common.edit')),
                DeleteAction::make()
                    ->label(__('common.delete')),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()
                        ->label(__('common.delete')),
                ]),
            ]);
    }

    public static function applyDefaultSort(Builder $query, string $direction = 'asc'): Builder
    {
        $dir = strtolower($direction) === 'desc' ? 'desc' : 'asc';

        self::applyRoleSort($query, $dir);
        self::applyCodeSort($query, 'asc');

        return $query->orderBy('users.name', 'asc')->orderBy('users.id', 'asc');
    }

    public static function applyRoleSort(Builder $query, string $direction = 'asc'): Builder
    {
        $dir = strtolower($direction) === 'desc' ? 'desc' : 'asc';

        return $query->orderByRaw(
            "CASE users.role
                WHEN 'superadmin' THEN 1
                WHEN 'admin' THEN 2
                WHEN 'staff' THEN 3
                WHEN 'assistant' THEN 4
                WHEN 'users' THEN 5
                ELSE 99
            END {$dir}"
        );
    }

    public static function applyCodeSort(Builder $query, string $direction = 'asc'): Builder
    {
        $dir = strtolower($direction) === 'desc' ? 'desc' : 'asc';

        return $query
            ->orderByRaw("CASE WHEN users.code IS NULL OR TRIM(users.code) = '' THEN 1 ELSE 0 END ASC")
            ->orderByRaw("CASE WHEN users.code REGEXP '^[0-9]+$' THEN LPAD(users.code, 32, '0') ELSE CONCAT('z', LOWER(users.code)) END {$dir}");
    }

    public static function getPages(): array
    {
        return [
            'index' => ListUsers::route('/'),
            'create' => CreateUser::route('/create'),
            'edit' => EditUser::route('/{record}/edit'),
        ];
    }
}
