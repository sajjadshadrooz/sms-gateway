<?php

namespace App\Providers;

use App\Contracts\MessageEventLog;
use App\Contracts\SmsQueue;
use App\Services\ClickHouse\ClickHouseMessageEventLog;
use App\Services\Kafka\KafkaProducer;
use App\Services\Kafka\KafkaSmsQueue;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(KafkaProducer::class); // we should hase just one instance of KafkaProducer of performance.
        $this->app->bind(SmsQueue::class, KafkaSmsQueue::class);

        $this->app->bind(MessageEventLog::class, ClickHouseMessageEventLog::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
