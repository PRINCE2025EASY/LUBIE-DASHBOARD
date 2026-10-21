<?php

declare(strict_types=1);

namespace Lubie\Services;

use Lubie\Db\Database;
use Lubie\Support\Clock;

/** Updates projects.last_change_at so polling clients know data changed (FR-24). */
final class ChangeTracker
{
    public function __construct(private Database $db, private Clock $clock)
    {
    }

    public function touch(int $projectId): void
    {
        $this->db->run('UPDATE projects SET last_change_at = ?, change_seq = change_seq + 1 WHERE id = ?', [$this->clock->nowSql(), $projectId]);
    }
}
