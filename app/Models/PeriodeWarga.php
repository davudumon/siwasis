<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Model untuk tabel pivot 'periode_warga'
 * Menyimpan partisipasi Warga dalam suatu Periode, termasuk status kemenangan Arisan.
 * Kolom: periode_id, warga_id, status_arisan
 */
class PeriodeWarga extends Model
{
    use HasFactory;

    // Nama tabel secara eksplisit (karena ini tabel pivot)
    protected $table = 'periode_warga';

    // Kolom yang dapat diisi massal
    protected $fillable = [
        'periode_id',
        'warga_id',
        'status_arisan',
    ];

    /**
     * Relasi ke Model Warga (Banyak-ke-Satu)
     */
    public function warga()
    {
        return $this->belongsTo(Warga::class, 'warga_id');
    }

    /**
     * Relasi ke Model Periode (Banyak-ke-Satu)
     */
    public function periode()
    {
        return $this->belongsTo(Periode::class, 'periode_id');
    }
}
