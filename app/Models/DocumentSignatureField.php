<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class DocumentSignatureField extends Model
{
    use HasUuids;

    protected $fillable = [
        'document_id',
        'signer_id',
        'page',
        'x',
        'y',
        'width',
        'height',
        'signature_image',
        'signed_at'
    ];

    protected $keyType = 'string';

    public $incrementing = false;

    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class);
    }

    public function signer(): BelongsTo
    {
        return $this->belongsTo(
            DocumentSigner::class,
            'signer_id'
        );
    }
}
