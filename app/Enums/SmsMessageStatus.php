<?php

namespace App\Enums;

enum SmsMessageStatus: string
{
    case Queued = 'queued';

    case Processing = 'processing';

    /** A transient operator failure; the message will be republished at next_retry_at. */
    case RetryScheduled = 'retry_scheduled';

    case Accepted = 'accepted';

    case Failed = 'failed';

    case Delivered = 'delivered';
}
