<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use HasFactory, Notifiable;

    protected $table = 'users';

    public $timestamps = false;

    const CREATED_AT = 'created_at';

    protected $fillable = [
        'first_name',
        'last_name',
        'email',
        'password',
        'role_id',
        'lang_id',
    ];

    protected $hidden = [
        'password',
    ];

    // Role constants
    const ROLE_ADMIN = 1;
    const ROLE_MANAGER = 2;
    const ROLE_SUBSCRIBER = 3;
    const ROLE_ADVERTISER = 4;

    public function role()
    {
        return $this->belongsTo(Role::class);
    }

    public function language()
    {
        return $this->belongsTo(Language::class, 'lang_id');
    }

    public function info()
    {
        return $this->hasOne(UserInfo::class);
    }

    public function jobs()
    {
        return $this->hasMany(Job::class);
    }

    public function jobsApplied()
    {
        return $this->hasMany(JobApplied::class);
    }

    public function categoryPreferences()
    {
        return $this->hasMany(JobCategoryPreference::class);
    }

    public function locationPreferences()
    {
        return $this->hasMany(JobLocationPreference::class);
    }

    public function subscriberPreference()
    {
        return $this->hasOne(SubscriberPreference::class);
    }

    public function getFullNameAttribute(): string
    {
        return "{$this->first_name} {$this->last_name}";
    }

    public function isAdmin(): bool
    {
        return $this->role_id === self::ROLE_ADMIN;
    }

    public function isManager(): bool
    {
        return $this->role_id === self::ROLE_MANAGER;
    }

    public function isSubscriber(): bool
    {
        return $this->role_id === self::ROLE_SUBSCRIBER;
    }

    public function isAdvertiser(): bool
    {
        return $this->role_id === self::ROLE_ADVERTISER;
    }

    public function isBackendUser(): bool
    {
        return in_array($this->role_id, [self::ROLE_ADMIN, self::ROLE_MANAGER, self::ROLE_ADVERTISER]);
    }

    public function scopeAdmins($query)
    {
        return $query->whereIn('role_id', [self::ROLE_ADMIN, self::ROLE_MANAGER]);
    }

    public function scopeSubscribers($query)
    {
        return $query->where('role_id', self::ROLE_SUBSCRIBER);
    }

    public function scopeAdvertisers($query)
    {
        return $query->where('role_id', self::ROLE_ADVERTISER);
    }
}
