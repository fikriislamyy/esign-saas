<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Template extends Model
{
    use HasUuids;

    protected $fillable = [
        'organization_id',
        'name',
        'file_path',
        'file_size',
        'mime_type',
    ];

    public $incrementing = false;

    protected $keyType = 'string';

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function signatureFields(): HasMany
    {
        return $this->hasMany(TemplateSignatureField::class);
    }
}
