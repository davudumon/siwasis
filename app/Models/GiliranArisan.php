<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class GiliranArisan extends Model
{
    use HasFactory;

    protected $table = 'giliran_arisan';
    protected $fillable = [
        'admin_id',
        'warga_id',
        'periode',
        'status',
        'tanggal_dapat'
    ];

    public function admin()
    {
        return $this->belongsTo(Admin::class);
    }

    public function warga()
    {
        return $this->belongsTo(Warga::class);
    }
}

