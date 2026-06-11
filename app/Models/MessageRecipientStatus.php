<?php

namespace App\Models;

enum MessageRecipientStatus: string
{
    case Pending = 'PENDING';
    case Queued = 'QUEUED';
    case Sent = 'SENT';
    case Failed = 'FAILED';
}
