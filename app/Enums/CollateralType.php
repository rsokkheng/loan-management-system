<?php

namespace App\Enums;

enum CollateralType: string
{
    case LandHardTitle = 'land_hard_title';
    case LandSoftTitle = 'land_soft_title';
    case House = 'house';
    case Vehicle = 'vehicle';
    case Motorbike = 'motorbike';
    case Gold = 'gold';
    case Deposit = 'deposit';
    case Equipment = 'equipment';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::LandHardTitle => __('Land (hard title)'),
            self::LandSoftTitle => __('Land (soft title)'),
            self::House => __('House'),
            self::Vehicle => __('Car / Vehicle'),
            self::Motorbike => __('Motorbike'),
            self::Gold => __('Gold / Jewellery'),
            self::Deposit => __('Savings deposit'),
            self::Equipment => __('Equipment / Machinery'),
            self::Other => __('Other'),
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::LandHardTitle, self::LandSoftTitle => 'map',
            self::House => 'house',
            self::Vehicle => 'car-front',
            self::Motorbike => 'bicycle',
            self::Gold => 'gem',
            self::Deposit => 'piggy-bank',
            self::Equipment => 'tools',
            self::Other => 'box-seam',
        };
    }
}
