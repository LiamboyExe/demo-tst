<?php

namespace App\Enums;

enum UserRole: string
{
    case Main = 'main';
    case Local = 'local';

    public function label(): string
    {
        return match ($this) {
            self::Main => 'Main Admin',
            self::Local => 'Branch Admin',
        };
    }
}
