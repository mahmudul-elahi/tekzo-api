<?php

namespace App\Enums;

enum StatusEnum: int
{
    case Inactive = 0;
    case Active = 1;
    case Pending = 2;
    case Rejected = 3;
    case Approved = 4;
    case Processing = 5;
    case OnWay = 6;
    case Complete = 7;
    case Replied = 8;
    case Return = 9;
    case Outlet = 10;
    case Bannar = 11;

    case PAID = 13;

    case Failed = 12;


    public function color(): string
    {
        return match ($this) {
            self::Inactive => 'secondary',   // gray
            self::Active => 'primary',       // blue
            self::Pending => 'warning',      // yellow
            self::Rejected => 'danger',      // red
            self::Approved => 'success',     // green
            self::Processing => 'info',      // cyan/blue
            self::OnWay => 'info',           // cyan/blue
            self::Complete => 'success',     // green
            self::Replied => 'primary',      // blue
            self::Return => 'danger',        // red
            self::Outlet => 'secondary',     // gray
            self::Bannar => 'primary',       // blue
            self::Failed => 'danger',       // red
        };
    }
}
