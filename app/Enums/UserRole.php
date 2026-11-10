<?php

namespace App\Enums;

enum UserRole: string
{
    case Main = 'main';
    case Local = 'local';
    case Member = 'member';

    public function label(): string
    {
        return match ($this) {
            self::Main => 'Main Admin',
            self::Local => 'Branch Admin',
            self::Member => 'Member',
        };
    }
}
