<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LogAktivitas extends Model
{
    protected $table = 'tbl_log';

    protected $primaryKey = 'id_log';

    public $timestamps = false;

    protected $fillable = [
        'id_user',
        'nama_tabel',
        'aktivitas',
        'id_referensi',
        'data_lama',
        'data_baru',
        'waktu',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'id_user' => 'integer',
            'id_referensi' => 'integer',
            'waktu' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'id_user', 'id_user');
    }
}
