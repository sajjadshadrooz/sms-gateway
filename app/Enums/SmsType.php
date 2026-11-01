<?php

namespace App\Enums;

enum SmsType: string
{
    case Normal = 'normal';
    case Express = 'express';

    public function price(): int
    {
        return (int) config("sms.prices.{$this->value}");
    }

    public function topic(): string
    {
        return (string) config("kafka.topics.{$this->value}");
    }

    public function deadLetterTopic(): string
    {
        return (string) config("kafka.topics.{$this->value}_dlq");
    }
}
