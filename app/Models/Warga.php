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
        'rt'
    ];

    public function admin(){
        return $this->belongsTo(Admin::class);
    }

    public function giliran_arisan(){
        return $this->hasMany(GiliranArisan::class);
    }
}
