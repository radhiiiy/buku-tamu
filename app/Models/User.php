<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;

class User extends Authenticatable
{
    protected $guarded = [];

    protected $hidden = ['password', 'remember_token', 'google_access_token', 'google_refresh_token'];

    public const DEFAULT_PREFS = ['notify_dashboard' => true, 'notify_email' => true, 'notify_sound' => false];

    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'prefs' => 'array',
            'can_export_phone' => 'boolean',
            'google_access_token' => 'encrypted',
            'google_refresh_token' => 'encrypted',
            'google_expires_at' => 'datetime',
        ];
    }

    public function pref(string $key): bool
    {
        return (bool) (($this->prefs ?? [])[$key] ?? self::DEFAULT_PREFS[$key] ?? false);
    }

    public function isAdmin(): bool
    {
        return $this->role === 'admin';
    }

    public function roleLabel(): string
    {
        return $this->isAdmin() ? 'Admin' : 'Resepsionis';
    }

    public function notificationsFeed(): HasMany
    {
        return $this->hasMany(AppNotification::class);
    }

    public function unreadCount(): int
    {
        return $this->notificationsFeed()->whereNull('read_at')->count();
    }

    public function googleConnected(): bool
    {
        return filled($this->google_refresh_token) || filled($this->google_access_token);
    }
}
