<?php

namespace App\Console\Commands;

use App\Contracts\SmsQueue;
use App\Enums\SmsMessageStatus;
use App\Models\SmsMessage;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class RepublishRetryableSms extends Command
{
    protected $signature = 'sms:republish-retries {--limit= : Maximum number of messages to republish in this run}';

    protected $description = 'Put SMS messages whose retry time has arrived back on their Kafka topic';

    public function handle(SmsQueue $queue): int
    {
        $limit = (int) ($this->option('limit') ?: config('sms.retry.republish_batch'));

        $count = DB::transaction(function () use ($queue, $limit) {
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

                // Inside the transaction, like SendSms: a publish failure rolls the
                // status back so the next run picks the message up again.
                $queue->publish($sms);
            }

            return $due->count();
        });

        $this->info("Republished {$count} SMS message(s).");

        return self::SUCCESS;
    }
}
