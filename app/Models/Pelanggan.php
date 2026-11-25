<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Pelanggan extends Model
{
    use HasFactory;

    protected $table = 'tbl_pelanggan';

    protected $primaryKey = 'id_pelanggan';

    protected $fillable = [
        'id_user',
        'rfid_uid',
        'nama_pelanggan',
        'kota',
        'no_telepon',
        'tier',
        'poin',
        'saldo',
    ];

    protected $appends = [
        'diskon_tier_persen',
        'is_walk_in',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'id_user' => 'integer',
            'poin' => 'integer',
            'saldo' => 'float',
        ];
    }

    public function getIsWalkInAttribute(): bool
    {
        return str_contains(strtolower((string) $this->nama_pelanggan), 'umum');
    }

    public function getDiskonTierPersenAttribute(): int
    {
        if ($this->is_walk_in) {
            return 0;
        }

        return match (strtolower((string) $this->tier)) {
            'gold', 'vip' => 10,
            'silver' => 5,
            default => 2,
        };
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'id_user', 'id_user');
    }

    public function transaksi(): HasMany
    {
        return $this->hasMany(Transaksi::class, 'id_pelanggan', 'id_pelanggan');
    }
}
