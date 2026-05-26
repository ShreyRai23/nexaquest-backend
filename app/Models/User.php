<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Tymon\JWTAuth\Contracts\JWTSubject;
use Illuminate\Database\Eloquent\SoftDeletes;

class User extends Authenticatable implements JWTSubject
{
    use Notifiable, SoftDeletes;

    protected $fillable = ['name', 'email', 'password', 'role', 'avatar_emoji', 'profile_image_path'];

    protected $appends = ['profile_image_url'];

    protected $hidden = ['password', 'remember_token'];

    protected $casts = ['email_verified_at' => 'datetime', 'password' => 'hashed'];

    public function getJWTIdentifier() { return $this->getKey(); }
    public function getJWTCustomClaims() { return ['role' => $this->role]; }

    public function childProfile() { return $this->hasOne(ChildProfile::class); }
    public function parentProfile() { return $this->hasOne(ParentProfile::class); }
    public function children() { return $this->hasMany(ChildProfile::class, 'parent_id'); }

    public function getProfileImageUrlAttribute()
    {
        if ($this->profile_image_path) {
            return asset('storage/' . $this->profile_image_path);
        }
        return null;
    }
}
