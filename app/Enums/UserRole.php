<?php

namespace App\Enums;

enum UserRole: string
{
    case Admin = 'admin';
    case Manager = 'manager';
    case LoanOfficer = 'loan_officer';
    case Accountant = 'accountant';

    public function label(): string
    {
        return match ($this) {
            self::Admin => __('Administrator'),
            self::Manager => __('Manager'),
            self::LoanOfficer => __('Loan Officer'),
            self::Accountant => __('Accountant'),
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Admin => 'danger',
            self::Manager => 'primary',
            self::LoanOfficer => 'info',
            self::Accountant => 'success',
        };
    }
}
