<?php

namespace App\Jobs;

use App\Models\ImportError;
use App\Models\ImportJob;
use App\Models\ImportedRecord;
use App\Models\ImportedStructuredRecord;
use App\Services\ValidationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Maatwebsite\Excel\Facades\Excel;

class ProcessImportJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $backoff = 60;

    public function __construct(
        public ImportJob $importJob
    ) {}

    public function handle(ValidationService $validationService): void
    {
        $this->importJob->markAsProcessing();
        
        $filePath = storage_path('app/public/' . $this->importJob->file_path);
        $mapping = $this->importJob->column_mapping;
        $validationRules = $this->importJob->validation_rules;
        
        try {
            $collection = Excel::toCollection(null, $filePath);
            $sheet = $collection->first();
            $headers = $sheet->first()?->toArray() ?? [];
            $rows = $sheet->skip(1)->values(); // Skip header row
            
            $processedCount = 0;
            $failedCount = 0;
            
            foreach ($rows as $index => $row) {
                $rowArray = $this->combineHeadersWithRow($headers, $row->toArray());
                $rowNumber = $index + 2; // +2 because we skip header and are 0-indexed
                
                // Validate the row
                $result = $validationService->validateRow($rowArray, $mapping, $validationRules);
                
                if ($result['valid']) {
                    $this->processValidRow($rowNumber, $rowArray, $result['data']);
                    $processedCount++;
                } else {
                    // Log error
                    $this->logError($rowNumber, $result['errors'], $rowArray);
                    $failedCount++;
                }
                
                // Update progress every 10 rows
                if (($index + 1) % 10 === 0) {
                    $this->importJob->update([
                        'processed_rows' => $processedCount,
                        'failed_rows' => $failedCount,
                    ]);
                }
            }
            
            // Final update
            $this->importJob->update([
                'processed_rows' => $processedCount,
                'failed_rows' => $failedCount,
            ]);
            
            if ($failedCount > 0) {
                $this->importJob->markAsFailed("{$failedCount} rows failed validation");
            } else {
                $this->importJob->markAsCompleted();
            }
            
        } catch (\Exception $e) {
            $this->importJob->markAsFailed($e->getMessage());
            throw $e;
        }
    }

    /**
     * Process a valid row - customize this for your needs
     */
    protected function processValidRow(int $rowNumber, array $originalData, array $mappedData): void
    {
        ImportedRecord::create([
            'job_id' => $this->importJob->id,
            'row_number' => $rowNumber,
            'original_data' => $originalData,
            'mapped_data' => $mappedData,
        ]);

        ImportedStructuredRecord::create($this->buildStructuredRecord($rowNumber, $mappedData));
        $this->saveToFileTable($rowNumber, $mappedData);
    }

    protected function buildStructuredRecord(int $rowNumber, array $mappedData): array
    {
        $directColumns = [
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
        ];

        $record = [
            'job_id' => $this->importJob->id,
            'row_number' => $rowNumber,
            'source_created_at' => $mappedData['created_at'] ?? null,
        ];

        foreach ($directColumns as $column) {
            $record[$column] = $mappedData[$column] ?? null;
        }

        $record['extra_fields'] = array_diff_key($mappedData, array_flip([...$directColumns, 'created_at']));

        return $record;
    }

    protected function saveToFileTable(int $rowNumber, array $mappedData): void
    {
        $table = $this->importJob->import_table_name;

        if (! $table) {
            return;
        }

        $this->ensureFileTable($table, array_keys($mappedData));

        $tableData = array_diff_key($mappedData, array_flip([
            'id',
            'import_job_id',
            'row_number',
            'created_at',
            'updated_at',
        ]));

        DB::table($table)->insert(array_merge([
            'import_job_id' => $this->importJob->id,
            'row_number' => $rowNumber,
            'created_at' => now(),
            'updated_at' => now(),
        ], $tableData));
    }

    protected function ensureFileTable(string $table, array $fields): void
    {
        if (! Schema::hasTable($table)) {
            Schema::create($table, function (Blueprint $blueprint) use ($fields) {
                $blueprint->id();
                $blueprint->foreignId('import_job_id')->nullable()->constrained('import_jobs')->nullOnDelete();
                $blueprint->unsignedInteger('row_number');

                foreach ($fields as $field) {
                    if (! $this->isReservedFileTableColumn($field)) {
                        $blueprint->text($field)->nullable();
                    }
                }

                $blueprint->timestamps();
                $blueprint->index(['import_job_id', 'row_number']);
            });

            return;
        }

        $missingFields = array_filter(
            $fields,
            fn ($field) => ! $this->isReservedFileTableColumn($field) && ! Schema::hasColumn($table, $field)
        );

        if (! empty($missingFields)) {
            Schema::table($table, function (Blueprint $blueprint) use ($missingFields) {
                foreach ($missingFields as $field) {
                    $blueprint->text($field)->nullable();
                }
            });
        }
    }

    protected function isReservedFileTableColumn(string $field): bool
    {
        return in_array($field, ['id', 'import_job_id', 'row_number', 'created_at', 'updated_at'], true);
    }

    protected function combineHeadersWithRow(array $headers, array $row): array
    {
        $usableHeaders = array_values(array_filter(
            $headers,
            fn ($header) => $header !== null && trim((string) $header) !== ''
        ));
        $values = array_pad($row, count($headers), null);
        $rowData = [];

        foreach ($headers as $index => $header) {
            if ($header === null || trim((string) $header) === '') {
                continue;
            }

            $rowData[trim((string) $header)] = $values[$index] ?? null;
        }

        return array_intersect_key($rowData, array_flip($usableHeaders));
    }

    /**
     * Log error to database
     */
    protected function logError(int $rowNumber, array $errors, array $rowData): void
    {
        ImportError::create([
            'job_id' => $this->importJob->id,
            'row_number' => $rowNumber,
            'error_message' => implode('; ', array_map(fn($e) => is_array($e) ? implode(', ', $e) : $e, $errors)),
            'row_data' => $rowData,
        ]);
    }

    public function failed(\Throwable $exception): void
    {
        $this->importJob->markAsFailed($exception->getMessage());
    }
}
