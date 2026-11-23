<?php

declare(strict_types=1);

namespace App\Enums;

enum OnlinePaymentStatusEnum: int
{
    case Pending = 0;
    case Initiated = 1;
    case Processing = 2;
    case Successful = 3;
    case Failed = 4;
    case Canceled = 5;
    case Unsuccessful = 6;
    case PartiallyPaid = 7;
    case Refunded = 8;
}
