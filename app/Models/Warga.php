<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Warga extends Model
{
    use HasFactory;

    protected $table = 'warga';

    protected $fillable = [
        'admin_id',
        'nama',
        'alamat',
        'role',
        'tanggal_lahir',
        'rt',
        'tipe_warga'
    ];

    public function admin(){
        return $this->belongsTo(Admin::class);
    }

    public function periode()
    {
        return $this->belongsToMany(Periode::class, 'periode_warga', 'warga_id', 'periode_id')
                    ->using(PeriodeWarga::class) // Menggunakan Model PeriodeWarga
                    ->withPivot('status_arisan') // Mengambil kolom status_arisan dari pivot
                    ->withTimestamps();
    }

    public function kasTransaction(){
        return $this->hasMany(KasWarga::class);
    }
    
    public function arisanTransaction(){
        return $this->hasMany(ArisanTransaction::class);
    }
}
