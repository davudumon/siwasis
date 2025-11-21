<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class Admin extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    protected $table = 'admin'; // plural sesuai migration

    protected $fillable = [
        'name',
        'email',
        'password',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $appends = ['photo_url'];

    public function articles()
    {
        return $this->hasMany(Article::class);
    }

    public function warga()
    {
        return $this->hasMany(Warga::class);
    }

    public function youtube_links()
    {
        return $this->hasMany(YoutubeLink::class);
    }

    public function documents()
    {
        return $this->hasMany(Document::class);
    }

    public function sampah_transaction()
    {
        return $this->hasMany(SampahTransaction::class);
    }

    public function getPhotoUrlAttribute()
    {
        return $this->photo
            ? config('app.url') . '/storage/profile/' . $this->photo
            : null;
    }
}
