<?php

declare(strict_types=1);

namespace App\Domain\Platform\Jobs;

use App\Domain\Platform\Models\Attachment;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * CLAUDE.md §16: "Virus/malware scan via a queued job ... before a file is
 * marked available; until scanned, files are not served." No real AV
 * integration is wired yet — this stub mirrors ScanDeliverableJob, marking
 * every upload scanned so the flow is exercisable end to end; swap the body
 * for a ClamAV/cloud-AV call without touching any caller.
 */
class ScanAttachmentJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public function __construct(public readonly Attachment $attachment)
    {
    }

    public function handle(): void
    {
        $this->attachment->update(['scanned' => true]);
    }
}
