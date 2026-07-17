<?php

declare(strict_types=1);

namespace App\Enum;

enum Sentiment: string
{
    case Positive = 'positive';
    case Negative = 'negative';
    case Neutral = 'neutral';
    case Unknown = 'unknown';
}
