<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ImportError extends Model
{
    use HasFactory;

    protected $fillable = [
        'job_id',
        'row_number',
        'error_message',
        'row_data',
        'is_retried',
    ];

    protected $casts = [
        'row_data' => 'array',
        'is_retried' => 'boolean',
    ];

    public function job(): BelongsTo
    {
        return $this->belongsTo(ImportJob::class, 'job_id');
    }

    public function markAsRetried(): void
    {
        $this->update(['is_retried' => true]);
    }
}