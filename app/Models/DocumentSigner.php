<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DocumentSigner extends Model
{
    use HasUuids;

    protected $fillable = [
        'document_id',
        'email',
        'name',
        'role',
        'token',
        'status',
        'signing_order',
        'signed_at',

        'otp_hash',
        'otp_expires_at',
        'otp_attempts',
        'otp_verified_at',
        'otp_last_sent_at',
    ];

    public $incrementing = false;

    protected $keyType = 'string';

    protected $casts = [
        'signed_at' => 'datetime',
        'otp_expires_at' => 'datetime',
        'otp_verified_at' => 'datetime',
        'otp_last_sent_at' => 'datetime',
    ];

    public function document()
    {
        return $this->belongsTo(
            Document::class
        );
    }

    public function fields()
    {
        return $this->hasMany(
            DocumentSignatureField::class,
            'signer_id'
        );
    }
}