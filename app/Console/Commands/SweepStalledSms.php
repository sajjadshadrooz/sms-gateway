<?php

namespace App\Console\Commands;

use App\Contracts\MessageEventLog;
use App\Contracts\SmsQueue;
use App\Enums\MessageEvent;
use App\Enums\SmsMessageStatus;
use App\Exceptions\SmsQueueUnavailableException;
use App\Models\SmsMessage;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class SweepStalledSms extends Command
{
    protected $signature = 'sms:sweep-stalled {--limit= : Maximum number of messages to handle per step in this run}';

    protected $description = 'Fail SMS messages whose wallet hold expired and requeue messages stuck in queued';

    public function handle(SmsQueue $queue, MessageEventLog $events): int
    {
        $limit = (int) ($this->option('limit') ?: config('sms.sweep.batch'));

        // Expire first, so a message past its hold is never requeued.
        $expired = $this->expireOverdue($events, $limit);
        $requeued = $this->requeueStale($queue, $events, $limit);

        $this->info("Expired {$expired} and requeued {$requeued} SMS message(s).");

        return self::SUCCESS;
    }

    private function expireOverdue(MessageEventLog $events, int $limit): int
    {
        /** @var Collection<int, SmsMessage> $expired */
        $expired = DB::transaction(function () use ($limit) {
            $overdue = SmsMessage::query()
                ->whereIn('status', [SmsMessageStatus::Queued, SmsMessageStatus::RetryScheduled])
                ->whereHas('reservation', fn ($query) => $query->overdue())
                ->limit($limit)
                // The row lock also makes a worker's claim wait for, then see, the failure.
                ->lock('for update skip locked')
                ->get();

            foreach ($overdue as $sms) {
                $sms->update([
                    'status' => SmsMessageStatus::Failed,
                    'next_retry_at' => null,
                    'last_error' => 'reservation_expired',
                ]);
                $sms->reservation->expire();
            }

            return $overdue;
        });

        foreach ($expired as $sms) {
            $events->record($sms, MessageEvent::Failed, ['reason' => 'reservation_expired']);
        }

        return $expired->count();
    }

    private function requeueStale(SmsQueue $queue, MessageEventLog $events, int $limit): int
    {
        /** @var Collection<int, SmsMessage> $stale */
        $stale = DB::transaction(function () use ($limit) {
            $stale = SmsMessage::query()
                ->staleQueued((int) config('sms.sweep.requeue_after_seconds'))
                ->orderBy('updated_at')
                ->limit($limit)
                ->lock('for update skip locked')
                ->get();

            foreach ($stale as $sms) {
                $sms->touch();
            }

            return $stale;
        });

        $requeued = 0;

        foreach ($stale as $sms) {
            try {
                $queue->publish($sms);
            } catch (SmsQueueUnavailableException $exception) {
                // Still queued, so a later run tries again.
                $this->error("Could not requeue SMS {$sms->id}: {$exception->getMessage()}");

                continue;
            }

            $events->record($sms, MessageEvent::Republished, [
                'topic' => $sms->type->topic(),
                'reason' => 'stale_queued',
            ]);
            $requeued++;
        }

        return $requeued;
    }
}
