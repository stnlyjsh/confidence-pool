<?php

namespace App\Enums;

enum GameStatus: string
{
    case Scheduled = 'scheduled';
    case InProgress = 'in_progress';
    case Final = 'final';
    case Postponed = 'postponed';
    case Canceled = 'canceled';
    case Voided = 'voided';
}
