<?php

namespace App\Enums;

enum LoanStatus: string
{
    case Pending = 'pending';
    case Approved = 'approved';
    case Rejected = 'rejected';
    case Active = 'active';
    case Closed = 'closed';
    case WrittenOff = 'written_off';

    public function label(): string
    {
        return match ($this) {
            self::Pending => __('Pending Approval'),
            self::Approved => __('Approved'),
            self::Rejected => __('Rejected'),
            self::Active => __('Active'),
            self::Closed => __('Closed'),
            self::WrittenOff => __('Written Off'),
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Pending => 'warning',
            self::Approved => 'info',
            self::Rejected => 'secondary',
            self::Active => 'primary',
            self::Closed => 'success',
            self::WrittenOff => 'danger',
        };
    }
}
