<?php

declare(strict_types=1);

namespace Lubie\Alerts\Rules;

use Lubie\Alerts\AlertRule;
use Lubie\Alerts\Condition;
use Lubie\Alerts\Thresholds;
use Lubie\Domain\KpiResult;
use Lubie\Domain\ProjectSnapshot;
use Lubie\Services\KpiService;
use Lubie\Support\Money;

/** A9: certificate not fully paid after submitted date + project payment period. */
final class A09CertificateOverdue implements AlertRule
{
    public function evaluate(ProjectSnapshot $s, KpiResult $k, Thresholds $t, string $today): array
    {
        $out = [];
        foreach ($s->certificates as $c) {
            $fullyPaid = $c['certified'] !== null && $c['paid'] >= $c['certified'];
            $age = KpiService::days($c['submitted_date'], $today);
            if (!$fullyPaid && $age > $s->paymentPeriodDays()) {
                $due = ($c['certified'] ?? $c['valuation']) - $c['paid'];
                $out[] = new Condition('A9', "A9:certificate:{$c['id']}", 'high', 'certificate', $c['id'],
                    "Certificate {$c['cert_no']} overdue",
                    "{$s->code()} {$c['cert_no']} submitted {$age} days ago; outstanding " . Money::format($due) . '.', ['qs', 'pm'], ['qs', 'pm']);
            }
        }
        return $out;
    }
}
