<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class KasWarga extends Model
{
    use HasFactory;

    protected $table = 'kas_warga';

    protected $fillable = [
        'admin_id',
        'warga_id',
        'periode_id',
        'jumlah',
        'tanggal',
        'status',
    ];

    /**
     * Relasi ke Admin (pembuat data)
     */
    public function admin()
    {
        return $this->belongsTo(Admin::class);
    }

    /**
     * Relasi ke Warga
     */
    public function warga()
    {
        return $this->belongsTo(Warga::class);
    }

    public function periode(){
        return $this->belongsTo(Periode::class);
    }

    /**
     * Scope: ambil data berdasarkan periode
     */
    public function scopePeriode($query, $periode)
    {
        return $query->where('periode', $periode);
    }

    /**
     * Scope: ambil data yang sudah bayar
     */
    public function scopeSudahBayar($query)
    {
        return $query->where('status', 'sudah_bayar');
    }

    /**
     * Scope: ambil data yang belum bayar
     */
    public function scopeBelumBayar($query)
    {
        return $query->where('status', 'belum_bayar');
    }
}
