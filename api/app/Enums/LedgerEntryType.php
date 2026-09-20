<?php

namespace App\Enums;

enum LedgerEntryType: string
{
    case BuyIn = 'buy_in';
    case WeeklyPayout = 'weekly_payout';
    case SeasonPayout = 'season_payout';
    case Adjustment = 'adjustment';
}
