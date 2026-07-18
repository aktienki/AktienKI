<?php

/*
|--------------------------------------------------------------------------
| In App\Models\User ergänzen
|--------------------------------------------------------------------------
*/

protected function casts(): array
{
    return [
        'email_verified_at' => 'datetime',
        'password' => 'hashed',
        'ui_preferences' => 'array',
    ];
}

protected $fillable = [
    'name',
    'email',
    'password',
    'ui_theme',
    'ui_preferences',
];
