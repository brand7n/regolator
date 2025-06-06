<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WebAuthnCredential extends Model
{
    protected $table = 'webauthn_credentials';

    protected $fillable = [
        'credential_id',
        'public_key',
        'counter',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
} 