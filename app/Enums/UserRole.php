<?php

namespace App\Enums;

enum UserRole: string
{
    case Admin = 'admin';
    case Kasir = 'kasir';
    case Owner = 'owner';
    case Pelanggan = 'pelanggan';

    public function label(): string
    {
        return match ($this) {
            self::Admin => 'Admin',
            self::Kasir => 'Kasir',
            self::Owner => 'Owner / Manajer',
            self::Pelanggan => 'Member / Pelanggan',
        };
    }

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
