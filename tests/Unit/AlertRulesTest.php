<?php

declare(strict_types=1);

namespace Lubie\Tests\Unit;

use Lubie\Alerts\AlertRule;
use Lubie\Alerts\Condition;
use Lubie\Alerts\Rules;
use Lubie\Alerts\Thresholds;
use Lubie\Domain\ProjectSnapshot;
use Lubie\Services\KpiService;
use Lubie\Tests\Support\SnapshotBuilder as B;
use PHPUnit\Framework\TestCase;

/** UT-ALR-01 to UT-ALR-16: each rule fires on its condition and stays quiet otherwise. */
final class AlertRulesTest extends TestCase
{
    private const T = '2026-03-20';

    /** @return list<Condition> */
    private function fire(AlertRule $rule, ProjectSnapshot $s, ?Thresholds $t = null): array
    {
        return $rule->evaluate($s, (new KpiService())->compute($s, self::T), $t ?? Thresholds::defaults(), self::T);
    }

    /** @return list<string> */
    private function keys(array $conds): array
    {
        return array_map(static fn (Condition $c) => $c->dedupKey, $conds);
    }

    public function testA1DelayedAndSmsLevel(): void
    {
        $short = B::project()->activity(1, '2026-03-01', '2026-03-15', 1000, 50, '2026-03-10')->build();   // 5 days late
        $long = B::project()->activity(2, '2026-02-01', '2026-03-01', 1000, 50, '2026-02-20')->build();    // 19 days late
        $this->assertSame(['A1:activity:1'], $this->keys($this->fire(new Rules\A01ActivityDelayed(), $short)));
        $c = $this->fire(new Rules\A01ActivityDelayed(), $long);
        $this->assertSame(['A1:activity:2', 'A1S:activity:2'], $this->keys($c));
        $this->assertSame(['director', 'pm'], $c[1]->smsTo);
        $done = B::project()->activity(3, '2026-03-01', '2026-03-15', 1000, 100, '2026-03-14')->build();
        $this->assertSame([], $this->fire(new Rules\A01ActivityDelayed(), $done));
    }

    public function testA2LateStart(): void
    {
        $s = B::project()->activity(1, '2026-03-10', '2026-04-10', 1000)->activity(2, '2026-03-25', '2026-04-10', 1000)->build();
        $this->assertSame(['A2:activity:1'], $this->keys($this->fire(new Rules\A02LateStart(), $s)));
    }

    public function testA3DueSoonAndA4Overdue(): void
    {
        $s = B::project()->milestone(1, '2026-03-25')->milestone(2, '2026-04-30')->milestone(3, '2026-03-10')->milestone(4, '2026-03-10', '2026-03-09')->build();
        $this->assertSame(['A3:milestone:1'], $this->keys($this->fire(new Rules\A03MilestoneDueSoon(), $s)));
        $a4 = $this->fire(new Rules\A04MilestoneOverdue(), $s);
        $this->assertSame(['A4:milestone:3'], $this->keys($a4));
        $this->assertTrue($a4[0]->clientVisible, 'Clients get an in-app notice for overdue milestones');
        $this->assertNotContains('client', $a4[0]->smsTo, 'Clients never get SMS');
        $this->assertStringContainsString('10/03/2026', $a4[0]->description, 'Dates shown DD/MM/YYYY');
    }

    public function testBudgetRulesA5A6A6b(): void
    {
        $at = static fn (int $spent) => B::project()->activity(1, '2026-03-01', '2026-03-31', 1000)->spend('2026-03-05', $spent)->budget(1000)->build();
        $codes = function (ProjectSnapshot $s): array {
            $out = [];
            foreach ([new Rules\A05BudgetWarning(), new Rules\A06BudgetExceeded(), new Rules\A06bCriticalOverrun()] as $r) {
                foreach ($this->fire($r, $s) as $c) {
                    $out[] = $c->rule;
                }
            }
            return $out;
        };
        $this->assertSame([], $codes($at(799)));
        $this->assertSame(['A5'], $codes($at(800)));
        $this->assertSame(['A5'], $codes($at(1000)), 'Exactly 100 % is a warning, not exceeded');
        $this->assertSame(['A6'], $codes($at(1050)), 'A5 stops once A6 is true');
        $this->assertSame(['A6'], $codes($at(1100)), 'Overrun of exactly 10 % is not critical');
        $this->assertSame(['A6', 'A6b'], $codes($at(1101)));
        $no = B::project()->activity(1, '2026-03-01', '2026-03-31', 1000)->spend('2026-03-05', 5000)->build();
        $this->assertSame([], $codes($no), 'No approved budget: no budget alerts');
    }

    public function testA7LowCpiNeedsEarnedValue(): void
    {
        $low = B::project()->activity(1, '2026-03-01', '2026-03-31', 1000, 40, '2026-03-15')->spend('2026-03-05', 800)->build();
        $this->assertSame(['A7:project:1'], $this->keys($this->fire(new Rules\A07LowCpi(), $low)));
        $noEv = B::project()->activity(1, '2026-03-01', '2026-03-31', 1000)->spend('2026-03-05', 800)->build();
        $this->assertSame([], $this->fire(new Rules\A07LowCpi(), $noEv));
    }

    public function testA8LowSpiUsesProjectThreshold(): void
    {
        $s = B::project()->activity(1, '2026-03-01', '2026-03-31', 1000, 50, '2026-03-15')->build();   // PV 63.3 %, SPI 0.79
        $this->assertSame(['A8:project:1'], $this->keys($this->fire(new Rules\A08LowSpi(), $s)));
        $this->assertSame([], $this->fire(new Rules\A08LowSpi(), $s, new Thresholds(['spi_min' => 0.75])));
    }

    public function testA9CertificateOverdue(): void
    {
        $s = B::project(['payment_period_days' => 30])
            ->certificate(1, '2026-02-01', 1000, 900, 400)     // 47 days, part paid -> overdue
            ->certificate(2, '2026-02-01', 1000, 900, 900)     // fully paid
            ->certificate(3, '2026-03-01', 1000, null, 0)      // 19 days, inside period
            ->build();
        $c = $this->fire(new Rules\A09CertificateOverdue(), $s);
        $this->assertSame(['A9:certificate:1'], $this->keys($c));
        $this->assertStringContainsString('GH₵ 500.00', $c[0]->description);
    }

    public function testA10MaterialAndA14Equipment(): void
    {
        $s = B::project()->resource(1, 'material', 5, 10)->resource(2, 'material', 50, 10)->resource(3, 'equipment', 1, null, 'faulty')->resource(4, 'equipment', 1, null, 'working')->build();
        $this->assertSame(['A10:resource:1'], $this->keys($this->fire(new Rules\A10MaterialLow(), $s)));
        $this->assertSame(['A14:resource:3'], $this->keys($this->fire(new Rules\A14EquipmentFaulty(), $s)));
    }

    public function testA11StaleData(): void
    {
        $stale = B::project()->activity(1, '2026-03-01', '2026-03-31', 1000, 10, '2026-03-10')->build();
        $fresh = B::project()->activity(1, '2026-03-01', '2026-03-31', 1000, 10, '2026-03-18')->build();
        $future = B::project(['planned_start' => '2026-04-01'])->activity(1, '2026-04-01', '2026-04-30', 1000)->build();
        $this->assertSame(['A11:project:1'], $this->keys($this->fire(new Rules\A11StaleData(), $stale)));
        $this->assertSame([], $this->fire(new Rules\A11StaleData(), $fresh));
        $this->assertSame([], $this->fire(new Rules\A11StaleData(), $future), 'No alert before the project starts');
    }

    public function testA12A13(): void
    {
        $s = B::project()->pendingBudget(7, 2, 5000)->submitted(1, 1, '2026-03-19')->submitted(2, 1, '2026-03-20')->build();
        $a12 = $this->fire(new Rules\A12BudgetRevisionPending(), $s);
        $this->assertSame(['A12:budget:7'], $this->keys($a12));
        $this->assertSame(['director'], $a12[0]->smsTo);
        $this->assertSame(['A13:project:1'], $this->keys($this->fire(new Rules\A13ProgressAwaitingApproval(), $s)), 'One summary alert, not one per entry');
    }

    public function testA15ClearsWhenWorkResumes(): void
    {
        $open = B::project()->activity(1, '2026-03-01', '2026-03-31', 1000)->critical(9, 1, '2026-03-12')->build();
        $c = $this->fire(new Rules\A15StoppageReported(), $open);
        $this->assertSame(['A15:progress:9'], $this->keys($c));
        $this->assertFalse($c[0]->clientVisible, 'Stoppages are internal');
        $resumed = B::project()->activity(1, '2026-03-01', '2026-03-31', 1000)->critical(9, 1, '2026-03-12')->history(1, '2026-03-15', 30)->build();
        $this->assertSame([], $this->fire(new Rules\A15StoppageReported(), $resumed));
    }

    public function testNoRuleEverSendsSmsToClients(): void
    {
        $s = B::project()->activity(1, '2026-02-01', '2026-03-01', 1000, 50, '2026-02-20')->activity(2, '2026-03-10', '2026-04-10', 1000)
            ->spend('2026-03-05', 2000)->budget(1000)->milestone(1, '2026-03-10')->milestone(2, '2026-03-22')
            ->certificate(1, '2026-01-01', 100, 100, 0)->resource(1, 'material', 1, 5)->resource(2, 'equipment', 1, null, 'faulty')
            ->pendingBudget(1, 2, 3000)->submitted(1, 1, '2026-03-19')->critical(1, 1, '2026-03-12')->build();
        $rules = [new Rules\A01ActivityDelayed(), new Rules\A02LateStart(), new Rules\A03MilestoneDueSoon(), new Rules\A04MilestoneOverdue(),
            new Rules\A05BudgetWarning(), new Rules\A06BudgetExceeded(), new Rules\A06bCriticalOverrun(), new Rules\A07LowCpi(), new Rules\A08LowSpi(),
            new Rules\A09CertificateOverdue(), new Rules\A10MaterialLow(), new Rules\A11StaleData(), new Rules\A12BudgetRevisionPending(),
            new Rules\A13ProgressAwaitingApproval(), new Rules\A14EquipmentFaulty(), new Rules\A15StoppageReported()];
        $seen = [];
        foreach ($rules as $r) {
            foreach ($this->fire($r, $s) as $c) {
                $seen[$c->rule] = true;
                $this->assertNotContains('client', $c->smsTo, $c->rule);
                if (in_array('client', $c->recipients, true)) {
                    $this->assertTrue($c->clientVisible, $c->rule . ' reaches clients so must be client-safe');
                }
            }
        }
        $this->assertCount(15, $seen, 'Rules seen: ' . implode(',', array_keys($seen)));   // A7 needs EV; A6/A5 exclusive
    }
}
