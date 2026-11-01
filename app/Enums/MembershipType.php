<?php

namespace App\Enums;

enum MembershipType: string
{
    case HighTableCouncil = 'High Table Council';
    case ShotCaller = 'Shot Caller';
    case Alas = 'Alas';
    case Pointman = 'Pointman';
    case Carnal = 'Carnal';
    case Carnala = 'Carnala';

    public function label(): string
    {
        return match ($this) {
            self::HighTableCouncil => 'High Table Council',
            self::ShotCaller => 'Shot Caller',
            self::Alas => 'Alas',
            self::Pointman => 'Pointman',
            self::Carnal => 'Carnal (Boy)',
            self::Carnala => 'Carnala (Girl)',
        };
    }

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_map(fn (self $type): string => $type->value, self::cases());
    }
}
