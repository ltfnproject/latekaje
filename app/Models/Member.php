<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Member extends Model
{
    public const TYPE_STUDENT = 'student';
    public const TYPE_TEACHER = 'teacher';
    public const TYPE_EMPLOYEE = 'employee';

    protected $fillable = ['code', 'name', 'group', 'member_type', 'birth_date', 'aktif'];

    protected function casts(): array
    {
        return [
            'aktif' => 'boolean',
            'birth_date' => 'date',
        ];
    }

    public static function memberTypeOptions(): array
    {
        return [
            self::TYPE_STUDENT => __('members.type_student'),
            self::TYPE_TEACHER => __('members.type_teacher'),
            self::TYPE_EMPLOYEE => __('members.type_employee'),
        ];
    }

    public function memberTypeLabel(): string
    {
        return self::memberTypeOptions()[$this->member_type ?? self::TYPE_STUDENT] ?? __('members.type_student');
    }

    /**
     * Password awal akun peminjam: tanggal lahir format DDMMYYYY (fallback ke ID/NIS bila kosong).
     */
    public function defaultInitialPassword(): string
    {
        if ($this->birth_date) {
            return $this->birth_date->format('dmY');
        }

        return (string) ($this->code ?? '');
    }

    public function loans(): HasMany
    {
        return $this->hasMany(Loan::class, 'member_id');
    }

    public function activeLoans(): HasMany
    {
        return $this->hasMany(Loan::class, 'member_id')->where('status', 'aktif');
    }

    public function label(): string
    {
        return ($this->code ? $this->code.' — ' : '').$this->name.' ('.$this->group.')';
    }
}
