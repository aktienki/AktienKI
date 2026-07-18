<?php

namespace App\Enums;

enum UiTheme: string
{
    case Purple = 'purple';
    case Teal = 'teal';
    case Light = 'light';

    public function label(): string
    {
        return match ($this) {
            self::Purple => 'Purple',
            self::Teal => 'Teal',
            self::Light => 'Light',
        };
    }

    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        $options = [];

        foreach (self::cases() as $theme) {
            $options[$theme->value] = $theme->label();
        }

        return $options;
    }
}
