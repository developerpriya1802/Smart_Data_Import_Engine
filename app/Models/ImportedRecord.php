<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ImportedRecord extends Model
{
    use HasFactory;

    protected $fillable = [
        'job_id',
        'row_number',
        'original_data',
        'mapped_data',
    ];

    protected $casts = [
        'original_data' => 'array',
        'mapped_data' => 'array',
        'row_number' => 'integer',
    ];

    public function job(): BelongsTo
    {
        return $this->belongsTo(ImportJob::class, 'job_id');
    }
}
