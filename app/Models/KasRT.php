<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class KasRt extends Model
{
    use HasFactory;

    protected $table = 'kas_rt';

    protected $fillable = [
        'admin_id',
        'tanggal',
        'tipe',
        'jumlah',
        'keterangan',
    ];

    /**
     * Relasi ke Admin
     */
    public function admin()
    {
        return $this->belongsTo(Admin::class);
    }

    /**
     * Scope: filter berdasarkan tipe (pemasukan/pengeluaran)
     */
    public function scopeTipe($query, $tipe)
    {
        return $query->where('tipe', $tipe);
    }

    /**
     * Scope: filter berdasarkan rentang tanggal
     */
    public function scopeTanggalAntara($query, $mulai, $akhir)
    {
        return $query->whereBetween('tanggal', [$mulai, $akhir]);
    }
}
