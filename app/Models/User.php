<?php

namespace App\Models;

use App\Enums\UserRole;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    protected $table = 'tbl_users';

    protected $primaryKey = 'id_user';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'nama_lengkap',
        'username',
        'rfid_uid',
        'email',
        'password_hash',
        'role',
        'is_active',
    ];

    /**
     * @var list<string>
     */
    protected $hidden = [
        'password_hash',
        'remember_token',
    ];

    /**
     * @var list<string>
     */
    protected $appends = [
        'name',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'password_hash' => 'hashed',
            'role' => UserRole::class,
            'is_active' => 'boolean',
        ];
    }

    public function getAuthPassword(): string
    {
        return $this->password_hash;
    }

    public function getAuthPasswordName(): string
    {
        return 'password_hash';
    }

    protected function name(): Attribute
    {
        return Attribute::make(
            get: fn (): string => (string) ($this->attributes['nama_lengkap'] ?? ''),
            set: fn (string $value): array => ['nama_lengkap' => $value],
        );
    }

    public function pelanggan(): HasOne
    {
        return $this->hasOne(Pelanggan::class, 'id_user', 'id_user');
    }

    public function transaksi(): HasMany
    {
        return $this->hasMany(Transaksi::class, 'id_user', 'id_user');
    }

    public function logs(): HasMany
    {
        return $this->hasMany(LogAktivitas::class, 'id_user', 'id_user');
    }
}
