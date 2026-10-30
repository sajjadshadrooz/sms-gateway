<?php

namespace App\Enums;

enum MessageEvent: string
{
    /** Accepted by the API, funds reserved and published to Kafka. */
    case Queued = 'queued';

    /** A worker picked the message up from Kafka. */
    case Processing = 'processing';

    /** The operator accepted the message; the reservation was confirmed. */
    case Accepted = 'accepted';

    /** A transient operator failure; a retry is scheduled. */
    case RetryScheduled = 'retry_scheduled';

    /** The scheduler put the message back on its Kafka topic for a retry. */
    case Republished = 'republished';

    /** Rejected by the operator or out of attempts; the reservation was released. */
    case Failed = 'failed';

    /** The message was parked on the dead letter topic. */
    case DeadLettered = 'dead_lettered';

    /** A Kafka delivery was ignored: the message is already settled or waiting for a retry. */
    case Skipped = 'skipped';

    /** A Kafka message that does not match any stored SMS message. */
    case UnknownMessage = 'unknown_message';
}
