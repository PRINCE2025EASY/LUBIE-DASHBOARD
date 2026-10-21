<?php

declare(strict_types=1);

namespace Lubie\Repositories;

use Lubie\Db\Database;

/** Budgets (versions), expenditure, payment certificates and payments. */
final class CostRepository
{
    public function __construct(private Database $db)
    {
    }

    /** @return list<array<string, mixed>> */
    public function budgets(int $projectId): array
    {
        return $this->db->all('SELECT b.*, rq.full_name AS requested_by_name, dc.full_name AS decided_by_name FROM budgets b
                                 JOIN users rq ON rq.id = b.requested_by LEFT JOIN users dc ON dc.id = b.decided_by
                                WHERE b.project_id = ? ORDER BY b.version_no DESC', [$projectId]);
    }

    /** @return array<string, mixed>|null */
    public function budget(int $id): ?array
    {
        return $this->db->one('SELECT * FROM budgets WHERE id = ?', [$id]);
    }

    /** @return list<array<string, mixed>> */
    public function pendingBudgetsAll(): array
    {
        return $this->db->all("SELECT b.*, p.code AS project_code, p.name AS project_name, rq.full_name AS requested_by_name,
                                      (SELECT c.amount FROM budgets c WHERE c.project_id = b.project_id AND c.is_current = 1) AS current_amount
                                 FROM budgets b JOIN projects p ON p.id = b.project_id JOIN users rq ON rq.id = b.requested_by
                                WHERE b.status = 'pending' ORDER BY b.requested_at");
    }

    public function nextVersion(int $projectId): int
    {
        return (int) $this->db->value('SELECT COALESCE(MAX(version_no), 0) + 1 FROM budgets WHERE project_id = ?', [$projectId]);
    }

    public function insertBudget(int $projectId, int $version, string $amount, ?string $reason, string $status, int $by, string $at): int
    {
        return $this->db->insert('INSERT INTO budgets (project_id, version_no, amount, reason, status, requested_by, requested_at) VALUES (?, ?, ?, ?, ?, ?, ?)',
            [$projectId, $version, $amount, $reason, $status, $by, $at]);
    }

    public function setBudgetStatus(int $id, string $from, string $to): int
    {
        return $this->db->run('UPDATE budgets SET status = ? WHERE id = ? AND status = ?', [$to, $id, $from])->rowCount();
    }

    public function approveBudget(int $id, int $projectId, int $by, string $at, ?string $note): void
    {
        $current = $this->db->value('SELECT id FROM budgets WHERE project_id = ? AND is_current = 1', [$projectId]);
        if ($current !== null) {
            $this->db->run("UPDATE budgets SET is_current = NULL, status = 'superseded' WHERE id = ?", [$current]);
        }
        $n = $this->db->run("UPDATE budgets SET status = 'approved', is_current = 1, decided_by = ?, decided_at = ?, decision_note = ?, supersedes_id = ? WHERE id = ? AND status = 'pending'",
            [$by, $at, $note, $current, $id])->rowCount();
        if ($n !== 1) {
            throw new \RuntimeException('Budget changed by another user');
        }
    }

    public function rejectBudget(int $id, int $by, string $at, ?string $note): int
    {
        return $this->db->run("UPDATE budgets SET status = 'rejected', decided_by = ?, decided_at = ?, decision_note = ? WHERE id = ? AND status = 'pending'",
            [$by, $at, $note, $id])->rowCount();
    }

    /** @return list<array<string, mixed>> */
    public function expenditures(int $projectId, int $limit, int $offset): array
    {
        return $this->db->all('SELECT e.*, c.code AS category_code, c.label AS category_label, a.code AS activity_code, u.full_name AS recorded_by_name
                                 FROM expenditures e JOIN expenditure_categories c ON c.id = e.category_id LEFT JOIN activities a ON a.id = e.activity_id
                                 JOIN users u ON u.id = e.recorded_by WHERE e.project_id = ? ORDER BY e.expense_date DESC, e.id DESC LIMIT ? OFFSET ?',
            [$projectId, $limit, $offset]);
    }

    public function countExpenditures(int $projectId): int
    {
        return (int) $this->db->value('SELECT COUNT(*) FROM expenditures WHERE project_id = ?', [$projectId]);
    }

    /** @return array<string, mixed>|null */
    public function expenditure(int $id): ?array
    {
        return $this->db->one('SELECT * FROM expenditures WHERE id = ?', [$id]);
    }

    /** @param array<string, mixed> $e */
    public function insertExpenditure(int $projectId, array $e, int $by): int
    {
        return $this->db->insert('INSERT INTO expenditures (project_id, activity_id, category_id, expense_date, amount, description, reference_no, supplier_name, recorded_by)
                                  VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [$projectId, $e['activity_id'], $e['category_id'], $e['expense_date'], $e['amount'], $e['description'], $e['reference_no'], $e['supplier_name'], $by]);
    }

    public function voidExpenditure(int $id, string $reason, int $by): int
    {
        return $this->db->run("UPDATE expenditures SET status = 'voided', void_reason = ?, voided_by = ? WHERE id = ? AND status = 'active'", [$reason, $by, $id])->rowCount();
    }

    public function referenceExists(int $projectId, string $ref): bool
    {
        return (bool) $this->db->value('SELECT 1 FROM expenditures WHERE project_id = ? AND reference_no = ?', [$projectId, $ref]);
    }

    /** @return list<array<string, mixed>> */
    public function categories(): array
    {
        return $this->db->all('SELECT id, code, label FROM expenditure_categories WHERE is_active = 1 ORDER BY id');
    }

    /** @return list<array<string, mixed>> */
    public function certificates(int $projectId): array
    {
        return $this->db->all('SELECT c.*, COALESCE((SELECT SUM(t.amount) FROM payment_transactions t WHERE t.certificate_id = c.id), 0) AS paid
                                 FROM payment_certificates c WHERE c.project_id = ? ORDER BY c.submitted_date, c.id', [$projectId]);
    }

    /** @return array<string, mixed>|null */
    public function certificate(int $id): ?array
    {
        return $this->db->one('SELECT c.*, COALESCE((SELECT SUM(t.amount) FROM payment_transactions t WHERE t.certificate_id = c.id), 0) AS paid
                                 FROM payment_certificates c WHERE c.id = ?', [$id]);
    }

    /** @return list<array<string, mixed>> */
    public function payments(int $certificateId): array
    {
        return $this->db->all('SELECT id, paid_date, amount, reference_no FROM payment_transactions WHERE certificate_id = ? ORDER BY paid_date, id', [$certificateId]);
    }

    public function insertCertificate(int $projectId, string $no, string $valuation, string $submitted, ?string $remarks, int $by): int
    {
        return $this->db->insert('INSERT INTO payment_certificates (project_id, cert_no, valuation_amount, submitted_date, remarks, recorded_by) VALUES (?, ?, ?, ?, ?, ?)',
            [$projectId, $no, $valuation, $submitted, $remarks, $by]);
    }

    public function certify(int $id, string $amount, string $date): int
    {
        return $this->db->run("UPDATE payment_certificates SET amount_certified = ?, certified_date = ?, status = 'certified' WHERE id = ? AND status = 'submitted'",
            [$amount, $date, $id])->rowCount();
    }

    public function insertPayment(int $certId, string $date, string $amount, string $ref, int $by): int
    {
        return $this->db->insert('INSERT INTO payment_transactions (certificate_id, paid_date, amount, reference_no, recorded_by) VALUES (?, ?, ?, ?, ?)',
            [$certId, $date, $amount, $ref, $by]);
    }

    public function setCertificateStatus(int $id, string $status): void
    {
        $this->db->run('UPDATE payment_certificates SET status = ? WHERE id = ?', [$status, $id]);
    }

    public function certNoExists(int $projectId, string $no): bool
    {
        return (bool) $this->db->value('SELECT 1 FROM payment_certificates WHERE project_id = ? AND cert_no = ?', [$projectId, $no]);
    }

    public function paymentRefExists(int $certId, string $ref): bool
    {
        return (bool) $this->db->value('SELECT 1 FROM payment_transactions WHERE certificate_id = ? AND reference_no = ?', [$certId, $ref]);
    }
}
