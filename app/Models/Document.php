<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Document extends Model
{
    use HasUuids;

    protected $fillable = [
        'organization_id',
        'owner_id',
        'name',
        'file_path',
        'signed_path',
        'file_size',
        'mime_type',
        'status',
    ];

    public $incrementing = false;

    protected $keyType = 'string';

    public function organization()
    {
        return $this->belongsTo(Organization::class);
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'owner_id'
        );
    }

    public function signers()
    {
        return $this->hasMany(
            DocumentSigner::class
        );
    }

    public function signatureFields()
    {
        return $this->hasMany(DocumentSignatureField::class);
    }
}