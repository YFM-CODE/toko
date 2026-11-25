<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Transaksi extends Model
{
    use HasFactory;

    protected $table = 'tbl_transaksi';

    protected $primaryKey = 'id_transaksi';

    protected $fillable = [
        'kode_transaksi',
        'tanggal',
        'id_pelanggan',
        'id_user',
        'subtotal',
        'diskon',
        'total_belanja',
        'metode_pembayaran',
        'payment_channel',
        'payment_token',
        'payment_ref',
        'bukti_pembayaran',
        'nominal_bayar',
        'kembalian',
        'poin_didapat',
        'poin_ditukar',
        'status',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'id_pelanggan' => 'integer',
            'id_user' => 'integer',
            'subtotal' => 'float',
            'diskon' => 'float',
            'total_belanja' => 'float',
            'nominal_bayar' => 'float',
            'kembalian' => 'float',
            'poin_didapat' => 'integer',
            'poin_ditukar' => 'integer',
        ];
    }

    /**
     * @var list<string>
     */
    protected $appends = ['bukti_pembayaran_url'];

    public function getBuktiPembayaranUrlAttribute(): ?string
    {
        if (empty($this->bukti_pembayaran)) {
            return null;
        }

        return '/uploads/bukti_pembayaran/'.$this->bukti_pembayaran;
    }

    public function pelanggan(): BelongsTo
    {
        return $this->belongsTo(Pelanggan::class, 'id_pelanggan', 'id_pelanggan');
    }

    public function kasir(): BelongsTo
    {
        return $this->belongsTo(User::class, 'id_user', 'id_user');
    }

    public function detail(): HasMany
    {
        return $this->hasMany(DetailTransaksi::class, 'id_transaksi', 'id_transaksi');
    }
}
