<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class Document extends Model
{
    use HasUuids;

    protected $fillable = [
        'id',
        'organization_id',
        'uploaded_by',
        'name',
        'original_name',
        'file_path',
        'file_size',
        'status',
    ];

    public function organization()
    {
        return $this->belongsTo(
            Organization::class
        );
    }

    public function uploader()
    {
        return $this->belongsTo(
            User::class,
            'uploaded_by'
        );
    }
}
