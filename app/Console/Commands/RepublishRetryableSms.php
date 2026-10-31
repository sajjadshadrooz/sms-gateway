<?php

namespace App\Console\Commands;

use App\Contracts\MessageEventLog;
use App\Contracts\SmsQueue;
use App\Enums\MessageEvent;
use App\Enums\SmsMessageStatus;
use App\Exceptions\SmsQueueUnavailableException;
use App\Models\SmsMessage;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class RepublishRetryableSms extends Command
{
    protected $signature = 'sms:republish-retries {--limit= : Maximum number of messages to republish in this run}';

    protected $description = 'Put SMS messages whose retry time has arrived back on their Kafka topic';

    public function handle(SmsQueue $queue, MessageEventLog $events): int
    {
        $limit = (int) ($this->option('limit') ?: config('sms.retry.republish_batch'));

        $due = DB::transaction(function () use ($limit) {
            $due = SmsMessage::query()
                ->retryDue()
                ->orderBy('next_retry_at')
                ->limit($limit)
                // Concurrent runs skip each other's rows instead of waiting on them.
                ->lock('for update skip locked')
                ->get();

            foreach ($due as $sms) {
                $sms->update([
                    'status' => SmsMessageStatus::Queued,
                    'next_retry_at' => null,
                ]);
            }

            return $due;
        });

        // Publish only once the queued status is committed: a worker that still saw
        // retry_scheduled would skip the delivery and commit its offset.
        $republished = 0;

        foreach ($due as $sms) {
            try {
                $queue->publish($sms);
            } catch (SmsQueueUnavailableException $exception) {
                // Due again right away, so the next run picks it up.
                $sms->update([
                    'status' => SmsMessageStatus::RetryScheduled,
                    'next_retry_at' => now(),
                ]);

                $this->error("Could not republish SMS {$sms->id}: {$exception->getMessage()}");

                continue;
            }

            $events->record($sms, MessageEvent::Republished, ['topic' => $sms->type->topic()]);
            $republished++;
        }

        $this->info("Republished {$republished} SMS message(s).");

        return self::SUCCESS;
    }
}
