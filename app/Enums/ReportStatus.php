<?php

namespace App\Enums;

/**
 * Lifecycle of a queued report export (Section 10). A large export is created Pending,
 * built by GenerateReportExport, then flipped to Ready (the "your report is ready"
 * notification fires) or Failed.
 */
enum ReportStatus: string
{
    case Pending = 'pending';
    case Ready = 'ready';
    case Failed = 'failed';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Preparing',
            self::Ready => 'Ready',
            self::Failed => 'Failed',
        };
    }

    public function isReady(): bool
    {
        return $this === self::Ready;
    }
}
