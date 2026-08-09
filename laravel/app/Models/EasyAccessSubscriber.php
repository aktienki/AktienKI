<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Notifications\Notifiable;

class EasyAccessSubscriber extends Model
{
    use Notifiable;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'accepted_terms' => 'boolean',
            'accepted_at' => 'datetime',
            'is_active' => 'boolean',
            'unsubscribed_at' => 'datetime',
            'preferences' => 'array',
        ];
    }

    public function routeNotificationForMail(): string
    {
        return $this->email;
    }

    public function getNameAttribute(): string
    {
        return __('Easy-Access-Abonnent');
    }

    public function getPreferencesAttribute(): array
    {
        return ['locale' => app()->getLocale(), 'theme' => 'dark'];
    }
}
