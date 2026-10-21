<?php

declare(strict_types=1);

namespace Lubie\Tests\Unit;

use Lubie\Domain\ScheduleStatus;
use Lubie\Services\KpiService;
use Lubie\Tests\Support\SnapshotBuilder;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/** UT-KPI-01 to UT-KPI-12 (Design Specification s9.1, thesis Table 2.1). */
final class KpiServiceTest extends TestCase
{
    private KpiService $kpi;

    protected function setUp(): void
    {
        $this->kpi = new KpiService();
    }

    /** Worked example: BAC 200,000; PV 98,000; EV 70,000; AC 80,000. */
    public function testThesisWorkedExample(): void
    {
        $s = SnapshotBuilder::project()
            ->activity(1, '2026-03-01', '2026-03-11', 100000, 50, '2026-03-09')   // 9/10 elapsed -> PV 90,000; EV 50,000
            ->activity(2, '2026-03-02', '2026-06-10', 100000, 20, '2026-03-09')   // 8/100 elapsed -> PV 8,000; EV 20,000
            ->spend('2026-03-05', 50000)->spend('2026-03-10', 30000)->spend('2026-03-11', 99999)  // last one is after t
            ->budget(200000)->build();
        $k = $this->kpi->compute($s, '2026-03-10');
        $this->assertSame('cost', $k->weighting);
        $this->assertSame(20000000, $k->bac);
        $this->assertSame(9800000, $k->pv);
        $this->assertSame(7000000, $k->ev);
        $this->assertSame(8000000, $k->ac);
        $this->assertSame(35.0, $k->completionPct);
        $this->assertSame(-2800000, $k->sv);
        $this->assertSame(0.71, $k->spi);
        $this->assertSame(-1000000, $k->cv);
        $this->assertSame(0.88, $k->cpi);
        $this->assertSame(40.0, $k->budgetConsumedPct);
        $this->assertSame(12000000, $k->budgetVariance);
        $this->assertSame(1, $k->dataCurrencyDays);
    }

    public function testNoActivitiesGivesNotAvailable(): void
    {
        $k = $this->kpi->compute(SnapshotBuilder::project()->build(), '2026-03-10');
        $this->assertSame('none', $k->weighting);
        $this->assertNull($k->completionPct);
        $this->assertNull($k->spi);
        $this->assertNull($k->cpi);
        $this->assertNull($k->budgetConsumedPct);
        $this->assertNull($k->dataCurrencyDays);
    }

    public function testDurationFallbackWhenNoActivityIsPriced(): void
    {
        $s = SnapshotBuilder::project()
            ->activity(1, '2026-03-01', '2026-03-10', null, 100, '2026-03-10')   // 10 days weight
            ->activity(2, '2026-03-11', '2026-03-30', null, 0, '2026-03-10')     // 20 days weight
            ->build();
        $k = $this->kpi->compute($s, '2026-03-10');
        $this->assertSame('duration', $k->weighting);
        $this->assertSame(33.33, $k->completionPct);
        $this->assertNull($k->bac);
        $this->assertNull($k->ev);
        $this->assertNull($k->cpi, 'CPI needs money values');
    }

    public function testZeroPlannedValueHidesSpiAndZeroActualHidesCpi(): void
    {
        $s = SnapshotBuilder::project()->activity(1, '2026-05-01', '2026-05-31', 1000)->build();
        $k = $this->kpi->compute($s, '2026-04-01');
        $this->assertSame(0, $k->pv);
        $this->assertNull($k->spi);
        $this->assertSame(0, $k->ac);
        $this->assertNull($k->cpi);
    }

    public function testOnlyApprovedProgressOnOrBeforeDateCounts(): void
    {
        $s = SnapshotBuilder::project()->activity(1, '2026-03-01', '2026-03-31', 1000)
            ->history(1, '2026-03-05', 20)->history(1, '2026-03-20', 60)->build();
        $this->assertSame(20.0, $this->kpi->compute($s, '2026-03-10')->completionPct);
        $this->assertSame(60.0, $this->kpi->compute($s, '2026-03-25')->completionPct);
        $this->assertSame(0.0, $this->kpi->compute($s, '2026-03-01')->completionPct);
    }

    public function testBudgetConsumedNotAvailableWithoutBudget(): void
    {
        $s = SnapshotBuilder::project()->activity(1, '2026-03-01', '2026-03-31', 1000)->spend('2026-03-02', 10)->build();
        $k = $this->kpi->compute($s, '2026-03-10');
        $this->assertNull($k->budgetConsumedPct);
        $this->assertNull($k->budgetVariance);
    }

    public function testRoundingUsesPesewasNotFloats(): void
    {
        // 33.33 % of GH₵ 0.10 = 3.333 pesewas -> 3; 66.67 % -> 6.667 -> 7
        $s = SnapshotBuilder::project()->activity(1, '2026-03-01', '2026-03-31', null)->build();
        $this->assertSame(3, \Lubie\Support\Money::mulDiv(10, 3333, 10000));
        $this->assertSame(7, \Lubie\Support\Money::mulDiv(10, 6667, 10000));
        $this->assertSame(-7, \Lubie\Support\Money::mulDiv(-10, 6667, 10000));
        $this->assertSame('duration', $this->kpi->compute($s, '2026-03-02')->weighting);
    }

    /** @return iterable<string, array{string, string, string, float}> */
    public static function fractions(): iterable
    {
        yield 'before start' => ['2026-03-01', '2026-03-11', '2026-02-28', 0.0];
        yield 'on start' => ['2026-03-01', '2026-03-11', '2026-03-01', 0.0];
        yield 'half way' => ['2026-03-01', '2026-03-11', '2026-03-06', 0.5];
        yield 'on finish' => ['2026-03-01', '2026-03-11', '2026-03-11', 1.0];
        yield 'after finish' => ['2026-03-01', '2026-03-11', '2026-04-01', 1.0];
        yield 'zero length before' => ['2026-03-05', '2026-03-05', '2026-03-04', 0.0];
        yield 'zero length on day' => ['2026-03-05', '2026-03-05', '2026-03-05', 1.0];
    }

    #[DataProvider('fractions')]
    public function testPlannedFraction(string $start, string $finish, string $t, float $expected): void
    {
        $this->assertSame($expected, KpiService::plannedFraction($start, $finish, $t));
    }

    /** @return iterable<string, array{array<string, mixed>, int, string, ScheduleStatus}> */
    public static function statuses(): iterable
    {
        $a = ['planned_start' => '2026-03-01', 'planned_finish' => '2026-03-31', 'actual_start' => null, 'actual_finish' => null, 'pct_date' => null, 'has_progress' => false];
        yield 'not started' => [$a, 0, '2026-03-01', ScheduleStatus::NotStarted];
        yield 'late start' => [$a, 0, '2026-03-02', ScheduleStatus::LateStart];
        yield 'in progress' => [['actual_start' => '2026-03-02'] + $a, 4000, '2026-03-15', ScheduleStatus::InProgress];
        yield 'delayed' => [['actual_start' => '2026-03-02'] + $a, 9000, '2026-04-01', ScheduleStatus::Delayed];
        yield 'delayed never started' => [$a, 0, '2026-04-01', ScheduleStatus::Delayed];
        yield 'completed' => [['actual_finish' => '2026-03-30'] + $a, 10000, '2026-04-05', ScheduleStatus::Completed];
        yield 'completed late' => [['actual_finish' => '2026-04-03'] + $a, 10000, '2026-04-05', ScheduleStatus::CompletedLate];
    }

    /** @param array<string, mixed> $a */
    #[DataProvider('statuses')]
    public function testScheduleStatus(array $a, int $pct, string $t, ScheduleStatus $expected): void
    {
        $this->assertSame($expected, KpiService::status($a, $pct, $t));
    }

    public function testDelayedActivityReportsDaysLate(): void
    {
        $s = SnapshotBuilder::project()->activity(1, '2026-03-01', '2026-03-10', 1000, 50, '2026-03-05')->build();
        $k = $this->kpi->compute($s, '2026-03-25');
        $this->assertCount(1, $k->delayedActivities());
        $this->assertSame(15, $k->delayedActivities()[0]['days_late']);
    }

    public function testSeriesHasNoEarnedValueInTheFuture(): void
    {
        $s = SnapshotBuilder::project(['planned_start' => '2026-03-01', 'planned_finish' => '2026-04-30'])
            ->activity(1, '2026-03-01', '2026-04-30', 1000)->history(1, '2026-03-10', 25)->build();
        $series = $this->kpi->series($s, '2026-03-20');
        $this->assertNotEmpty($series);
        foreach ($series as $p) {
            $p['date'] > '2026-03-20' ? $this->assertNull($p['ev']) : $this->assertNotNull($p['ev']);
        }
        $this->assertSame(100.0, end($series)['pv_pct']);
    }
}
