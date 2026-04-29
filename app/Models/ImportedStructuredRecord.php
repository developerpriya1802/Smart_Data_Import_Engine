<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ImportedStructuredRecord extends Model
{
    use HasFactory;

    protected $fillable = [
        'job_id',
        'row_number',
        'name',
        'email',
        'phone',
        'dob',
        'address',
        'city',
        'state',
        'country',
        'zipcode',
        'company',
        'designation',
        'status',
        'source_created_at',
        'extra_fields',
    ];

    protected $casts = [
        'row_number' => 'integer',
        'extra_fields' => 'array',
    ];

    public function job(): BelongsTo
    {
        return $this->belongsTo(ImportJob::class, 'job_id');
    }
}
