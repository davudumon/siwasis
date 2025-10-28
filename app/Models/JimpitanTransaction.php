<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class JimpitanTransaction extends Model
{
    use HasFactory;

    protected $fillable = [
        'admin_id',
        'jumlah',
        'tipe',
        'keterangan',
        'tanggal',
    ];

    public function admin()
    {
        return $this->belongsTo(Admin::class);
    }
}
