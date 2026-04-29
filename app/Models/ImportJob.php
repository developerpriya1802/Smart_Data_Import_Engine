<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ImportJob extends Model
{
    use HasFactory;

    protected $fillable = [
        'file_name',
        'file_path',
        'status',
        'total_rows',
        'processed_rows',
        'failed_rows',
        'column_mapping',
        'validation_rules',
        'module',
        'import_table_name',
        'error_summary',
    ];

    protected $casts = [
        'column_mapping' => 'array',
        'validation_rules' => 'array',
        'total_rows' => 'integer',
        'processed_rows' => 'integer',
        'failed_rows' => 'integer',
    ];

    public const STATUS_PENDING = 'pending';
    public const STATUS_PROCESSING = 'processing';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_FAILED = 'failed';

    public function errors(): HasMany
    {
        return $this->hasMany(ImportError::class, 'job_id');
    }

    public function records(): HasMany
    {
        return $this->hasMany(ImportedRecord::class, 'job_id');
    }

    public function structuredRecords(): HasMany
    {
        return $this->hasMany(ImportedStructuredRecord::class, 'job_id');
    }

    public function getProgressPercentage(): int
    {
        if ($this->total_rows === 0) {
            return 0;
        }
        return (int) round(($this->processed_rows / $this->total_rows) * 100);
    }

    public function isCompleted(): bool
    {
        return $this->status === self::STATUS_COMPLETED;
    }

    public function isProcessing(): bool
    {
        return $this->status === self::STATUS_PROCESSING;
    }

    public function markAsProcessing(): void
    {
        $this->update(['status' => self::STATUS_PROCESSING]);
    }

    public function markAsCompleted(): void
    {
        $this->update(['status' => self::STATUS_COMPLETED]);
    }

    public function markAsFailed(string $summary = null): void
    {
        $this->update([
            'status' => self::STATUS_FAILED,
            'error_summary' => $summary,
        ]);
    }

    public function incrementProcessed(int $count = 1): void
    {
        $this->increment('processed_rows', $count);
    }

    public function incrementFailed(int $count = 1): void
    {
        $this->increment('failed_rows', $count);
    }
}
