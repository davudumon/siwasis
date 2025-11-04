<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ArisanTransaction extends Model
{
    use HasFactory;

    protected $table = 'arisan_transactions';

    protected $fillable = [
        'admin_id',
        'warga_id',
        'jumlah',
        'periode_id',
        'tanggal',
        'status'
    ];

    public function admin()
    {
        return $this->belongsTo(Admin::class);
    }

    public function warga()
    {
        return $this->belongsTo(Warga::class);
    }

    public function periode(){
        return $this->belongsTo(Periode::class);
    }
}
