<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SampahTransaction extends Model
{
    use HasFactory;

    protected $table = 'sampah_transactions';

    protected $fillable = [
        'admin_id',
        'warga_id',
        'jumlah',
        'periode'
    ];
}
