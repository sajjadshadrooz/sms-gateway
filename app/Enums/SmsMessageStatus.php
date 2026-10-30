<?php

namespace App\Enums;

enum SmsMessageStatus: string
{
    case Queued = 'queued';

    case Processing = 'processing';

    case Accepted = 'accepted';

    case Failed = 'failed';

    case Delivered = 'delivered';
}
