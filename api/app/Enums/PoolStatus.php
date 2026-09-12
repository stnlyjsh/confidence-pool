<?php

namespace App\Enums;

enum PoolStatus: string
{
    case Draft = 'draft';
    case Active = 'active';
    case Completed = 'completed';
}
