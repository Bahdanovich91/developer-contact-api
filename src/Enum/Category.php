<?php

declare(strict_types=1);

namespace App\Enum;

enum Category: string
{
    case Support = 'support';
    case Sales = 'sales';
    case Feedback = 'feedback';
    case Other = 'other';
}
