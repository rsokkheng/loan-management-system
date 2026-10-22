<?php

namespace App\Enums;

enum Relationship: string
{
    case Spouse = 'spouse';
    case Parent = 'parent';
    case Child = 'child';
    case Sibling = 'sibling';
    case Relative = 'relative';
    case Friend = 'friend';
    case Colleague = 'colleague';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Spouse => __('Spouse'),
            self::Parent => __('Parent'),
            self::Child => __('Child'),
            self::Sibling => __('Sibling'),
            self::Relative => __('Relative'),
            self::Friend => __('Friend'),
            self::Colleague => __('Colleague'),
            self::Other => __('Other'),
        };
    }
}
