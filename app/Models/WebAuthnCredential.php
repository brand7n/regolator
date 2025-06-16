<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WebAuthnCredential extends Model
{
    protected $table = 'webauthn_credentials';

    protected $fillable = [
        'passkey',
    ];

    protected $casts = [
        'passkey' => 'array',  
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
} 