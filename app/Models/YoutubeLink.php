<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class YoutubeLink extends Model
{
    use HasFactory;

    protected $table = 'youtube_links'; // tabel plural

    protected $fillable = [
        'title',
        'url',
        'image',
        'admin_id', // kalau nanti ada relasi ke admin
    ];

    // contoh relasi kalau 1 link dibuat oleh 1 admin
    public function admin()
    {
        return $this->belongsTo(Admin::class);
    }
}
