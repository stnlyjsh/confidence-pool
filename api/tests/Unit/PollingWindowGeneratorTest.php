<?php

namespace Tests\Unit;

use App\Services\PollingWindowGenerator;
use Illuminate\Support\Carbon;
use PHPUnit\Framework\TestCase;

class PollingWindowGeneratorTest extends TestCase
{
    private PollingWindowGenerator $generator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->generator = new PollingWindowGenerator;
    }

    public function test_no_kickoffs_produces_no_expressions(): void
    {
        $this->assertSame([], $this->generator->generate(collect()));
    }

    public function test_a_single_kickoff_within_one_day_produces_one_expression(): void
    {
        $kickoff = Carbon::parse('2026-10-29 20:15:00');

        $expressions = $this->generator->generate(collect([$kickoff]), gameDurationHours: 3);

        // 20:15 + 3h = 23:15, so the window is hours 20-23 on Oct 29.
        $this->assertSame(['* 20-23 29 10 *'], $expressions);
    }

    public function test_a_kickoff_that_crosses_midnight_produces_two_expressions(): void
    {
        $kickoff = Carbon::parse('2026-10-29 22:00:00');

        $expressions = $this->generator->generate(collect([$kickoff]), gameDurationHours: 4);

        // 22:00 + 4h = Oct 30 02:00, split at the day boundary.
        $this->assertSame(['* 22-23 29 10 *', '* 0-2 30 10 *'], $expressions);
    }

    public function test_overlapping_kickoffs_merge_into_one_window(): void
    {
        $kickoffs = collect([
            Carbon::parse('2026-11-15 13:00:00'),
            Carbon::parse('2026-11-15 16:25:00'),
        ]);

        $expressions = $this->generator->generate($kickoffs, gameDurationHours: 5);

        // 13:00+5h=18:00 and 16:25+5h=21:25 overlap, merging into one 13-21 window.
        $this->assertSame(['* 13-21 15 11 *'], $expressions);
    }

    public function test_far_apart_kickoffs_stay_as_separate_windows(): void
    {
        $kickoffs = collect([
            Carbon::parse('2026-11-12 20:15:00'), // Thursday night
            Carbon::parse('2026-11-15 13:00:00'), // Sunday early
        ]);

        $expressions = $this->generator->generate($kickoffs, gameDurationHours: 3);

        $this->assertSame(['* 20-23 12 11 *', '* 13-16 15 11 *'], $expressions);
    }
}
