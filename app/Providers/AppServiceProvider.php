<?php

namespace App\Providers;

use App\Contracts\MessageEventLog;
use App\Contracts\SmsQueue;
use App\Services\ClickHouse\ClickHouseMessageEventLog;
use App\Services\Kafka\KafkaEventProducer;
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
        $this->app->singleton(KafkaProducer::class);
        $this->app->bind(SmsQueue::class, KafkaSmsQueue::class);

        $this->app->singleton(KafkaEventProducer::class);
        $this->app->bind(MessageEventLog::class, ClickHouseMessageEventLog::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->app->terminating(function () {
            if ($this->app->resolved(KafkaEventProducer::class)) {
                $this->app->make(KafkaEventProducer::class)->flush();
            }
        });
    }
}
