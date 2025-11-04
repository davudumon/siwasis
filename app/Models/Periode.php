<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Periode extends Model
{
    use HasFactory;

    protected $table = 'periode'; // Asumsi nama tabel adalah 'periode'

    protected $fillable = [
        'nama',
        'tanggal_mulai',
        'tanggal_selesai',
        'nominal'
    ];

    /**
     * Relasi Many-to-Many ke Model Warga (Melalui tabel pivot periode_warga)
     * Ini digunakan untuk mendapatkan daftar peserta dan status kemenangan arisan mereka.
     */
    public function warga()
    {
        return $this->belongsToMany(Warga::class, 'periode_warga')
            ->withPivot('status_arisan')
            ->withTimestamps();
    }
}
