<?php

namespace App\Models;

enum MessageStatus: string
{
    case Draft = 'DRAFT';
    case Sending = 'SENDING';
    case Sent = 'SENT';
    case Failed = 'FAILED';
}
