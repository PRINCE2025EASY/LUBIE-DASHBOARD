<?php

declare(strict_types=1);

namespace Lubie\Domain;


enum ScheduleStatus: string
{
    case NotStarted = 'not_started';
    case LateStart = 'late_start';
    case InProgress = 'in_progress';
    case Delayed = 'delayed';
    case Completed = 'completed';
    case CompletedLate = 'completed_late';

    public function label(): string
    {
        return match ($this) {
            self::NotStarted => 'Not started',
            self::LateStart => 'Late start',
            self::InProgress => 'In progress',
            self::Delayed => 'Delayed',
            self::Completed => 'Completed',
            self::CompletedLate => 'Completed late',
        };
    }

    public function tone(): string
    {
        return match ($this) {
            self::Completed, self::InProgress, self::NotStarted => 'ok',
            self::LateStart, self::CompletedLate => 'warning',
            self::Delayed => 'critical',
        };
    }
}
