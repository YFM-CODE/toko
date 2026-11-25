<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Produk extends Model
{
    use HasFactory;

    protected $table = 'tbl_produk';

    protected $primaryKey = 'id_produk';

    protected $fillable = [
        'id_kategori',
        'kode_produk',
        'nama_produk',
        'harga',
        'stok',
        'gambar',
    ];

    /**
     * @var list<string>
     */
    protected $appends = [
        'gambar_url',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'id_kategori' => 'integer',
            'harga' => 'float',
            'stok' => 'integer',
        ];
    }

    protected function gambarUrl(): Attribute
    {
        return Attribute::make(
            get: fn (): ?string => ! empty($this->attributes['gambar'])
                ? '/uploads/produk/'.$this->attributes['gambar']
                : null,
        );
    }

    public function kategori(): BelongsTo
    {
        return $this->belongsTo(Kategori::class, 'id_kategori', 'id_kategori');
    }

    public function detailTransaksi(): HasMany
    {
        return $this->hasMany(DetailTransaksi::class, 'id_produk', 'id_produk');
    }
}
