<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TemplateSignatureField extends Model
{
    use HasUuids;

    protected $fillable = [
        'template_id',
        'page',
        'x',
        'y',
        'width',
        'height',
    ];

    public $incrementing = false;

    protected $keyType = 'string';

    public function template(): BelongsTo
    {
        return $this->belongsTo(Template::class);
    }
}
