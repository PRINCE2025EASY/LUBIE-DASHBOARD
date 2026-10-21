<?php

declare(strict_types=1);

namespace Lubie\Repositories;

use Lubie\Db\Database;

/** Progress records with approval workflow, delay reasons and offline idempotency keys. */
final class ProgressRepository
{
    public function __construct(private Database $db)
    {
    }

    /** @return array<string, mixed>|null */
    public function find(int $id): ?array
    {
        return $this->db->one('SELECT pr.*, a.project_id, a.code AS activity_code, a.name AS activity_name FROM progress_records pr JOIN activities a ON a.id = pr.activity_id WHERE pr.id = ?', [$id]);
    }

    public function findByClientUuid(string $uuid): ?int
    {
        $v = $this->db->value('SELECT id FROM progress_records WHERE client_uuid = ?', [$uuid]);
        return $v === null ? null : (int) $v;
    }

    /** @return list<array<string, mixed>> */
    public function forActivity(int $activityId): array
    {
        return $this->db->all(
            'SELECT pr.id, pr.record_date, pr.percent_complete, pr.remarks, pr.status, pr.submitted_at, pr.reviewed_at, pr.return_reason,
                    d.label AS delay_reason, su.full_name AS submitted_by_name, rv.full_name AS reviewed_by_name
               FROM progress_records pr LEFT JOIN delay_reasons d ON d.id = pr.delay_reason_id
               JOIN users su ON su.id = pr.submitted_by LEFT JOIN users rv ON rv.id = pr.reviewed_by
              WHERE pr.activity_id = ? ORDER BY pr.record_date DESC, pr.id DESC', [$activityId]);
    }

    /** @return list<array<string, mixed>> */
    public function queue(int $projectId): array
    {
        return $this->db->all(
            "SELECT pr.id, pr.activity_id, a.code AS activity_code, a.name AS activity_name, pr.record_date, pr.percent_complete, pr.remarks,
                    d.label AS delay_reason, d.raises_critical, su.full_name AS submitted_by_name, pr.submitted_at,
                    (SELECT p2.percent_complete FROM progress_records p2 WHERE p2.activity_id = pr.activity_id AND p2.status = 'approved'
                      ORDER BY p2.record_date DESC, p2.id DESC LIMIT 1) AS last_approved_pct
               FROM progress_records pr JOIN activities a ON a.id = pr.activity_id
          LEFT JOIN delay_reasons d ON d.id = pr.delay_reason_id JOIN users su ON su.id = pr.submitted_by
              WHERE a.project_id = ? AND pr.status = 'submitted' ORDER BY pr.submitted_at", [$projectId]);
    }

    /** @return list<array<string, mixed>> latest entry per activity submitted by this user */
    public function mySubmissions(int $userId, int $projectId): array
    {
        return $this->db->all(
            'SELECT pr.id, pr.activity_id, pr.status, pr.percent_complete, pr.record_date, pr.return_reason, pr.reviewed_at
               FROM progress_records pr JOIN activities a ON a.id = pr.activity_id
              WHERE pr.submitted_by = ? AND a.project_id = ? ORDER BY pr.submitted_at DESC, pr.id DESC LIMIT 50', [$userId, $projectId]);
    }

    /** @param array<string, mixed> $r */
    public function insert(array $r): int
    {
        return $this->db->insert(
            'INSERT INTO progress_records (activity_id, record_date, percent_complete, remarks, delay_reason_id, status, submitted_by, submitted_at, resubmission_of, client_uuid)
             VALUES (?, ?, ?, ?, ?, \'submitted\', ?, ?, ?, ?)',
            [$r['activity_id'], $r['record_date'], $r['percent_complete'], $r['remarks'], $r['delay_reason_id'], $r['submitted_by'], $r['submitted_at'], $r['resubmission_of'], $r['client_uuid']]);
    }

    public function decide(int $id, string $status, int $reviewer, string $at, ?string $reason): int
    {
        return $this->db->run("UPDATE progress_records SET status = ?, reviewed_by = ?, reviewed_at = ?, return_reason = ? WHERE id = ? AND status = 'submitted'",
            [$status, $reviewer, $at, $reason, $id])->rowCount();
    }

    /** @return array<string, mixed>|null */
    public function delayReason(int $id): ?array
    {
        return $this->db->one('SELECT * FROM delay_reasons WHERE id = ? AND is_active = 1', [$id]);
    }

    /** @return list<array<string, mixed>> */
    public function delayReasons(): array
    {
        return $this->db->all('SELECT id, code, label, raises_critical FROM delay_reasons WHERE is_active = 1 ORDER BY sort_order');
    }
}
