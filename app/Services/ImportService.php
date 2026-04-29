<?php

namespace App\Services;

use App\Jobs\ProcessImportJob;
use App\Models\ImportJob;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Facades\Excel;

class ImportService
{
    protected MappingService $mappingService;
    protected ValidationService $validationService;

    public function __construct(
        MappingService $mappingService,
        ValidationService $validationService
    ) {
        $this->mappingService = $mappingService;
        $this->validationService = $validationService;
    }

    /**
     * Handle file upload and create import job
     */
    public function uploadFile(UploadedFile $file, ?string $module = 'generic'): ImportJob
    {
        $path = $file->store('imports', 'public');
        
        // Count rows in file
        $totalRows = $this->countRows(storage_path('app/public/' . $path));
        
        $importJob = ImportJob::create([
            'file_name' => $file->getClientOriginalName(),
            'file_path' => $path,
            'status' => ImportJob::STATUS_PENDING,
            'total_rows' => $totalRows,
            'module' => $module,
            'import_table_name' => $this->makeImportTableName($file),
        ]);
        
        return $importJob;
    }

    /**
     * Get headers from uploaded file
     */
    public function getHeaders(ImportJob $job): array
    {
        $filePath = storage_path('app/public/' . $job->file_path);
        
        $collection = Excel::toCollection(null, $filePath);
        $firstRow = $collection->first()->first();
        
        if ($firstRow) {
            return array_values(array_filter(
                $firstRow->toArray(),
                fn ($header) => $header !== null && trim((string) $header) !== ''
            ));
        }
        
        return [];
    }

    /**
     * Auto-map columns for the job
     */
    public function autoMapColumns(ImportJob $job): array
    {
        $headers = $this->getHeaders($job);
        return $this->mappingService->autoMap($headers);
    }

    /**
     * Save column mapping and start import
     */
    public function saveMappingAndStart(ImportJob $job, array $mapping, ?array $validationRules = null): void
    {
        $job->update([
            'column_mapping' => $mapping,
            'validation_rules' => $validationRules,
            'status' => ImportJob::STATUS_PENDING,
        ]);
        
        // Dispatch the job to queue
        ProcessImportJob::dispatch($job);
    }

    /**
     * Get import status
     */
    public function getStatus(ImportJob $job): array
    {
        return [
            'id' => $job->id,
            'file_name' => $job->file_name,
            'status' => $job->status,
            'total_rows' => $job->total_rows,
            'processed_rows' => $job->processed_rows,
            'failed_rows' => $job->failed_rows,
            'progress_percentage' => $job->getProgressPercentage(),
            'import_table_name' => $job->import_table_name,
            'error_summary' => $job->error_summary,
            'created_at' => $job->created_at,
            'updated_at' => $job->updated_at,
        ];
    }

    /**
     * Get errors for an import job
     */
    public function getErrors(ImportJob $job): array
    {
        return $job->errors()->get()->toArray();
    }

    /**
     * Retry failed rows
     */
    public function retryFailedRows(ImportJob $job): void
    {
        $errors = $job->errors()->where('is_retried', false)->get();
        
        foreach ($errors as $error) {
            $error->markAsRetried();
        }
        
        // Reset counters and restart processing
        $job->update([
            'processed_rows' => 0,
            'failed_rows' => 0,
            'status' => ImportJob::STATUS_PENDING,
        ]);
        
        ProcessImportJob::dispatch($job);
    }

    /**
     * Count rows in file (excluding header)
     */
    protected function countRows(string $filePath): int
    {
        try {
            $collection = Excel::toCollection(null, $filePath);
            return max(0, $collection->first()->count() - 1); // Subtract header row
        } catch (\Exception $e) {
            return 0;
        }
    }

    protected function makeImportTableName(UploadedFile $file): string
    {
        $name = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);
        $table = Str::snake(trim(preg_replace('/[^a-zA-Z0-9]+/', ' ', $name)));

        if ($table === '' || preg_match('/^[0-9]/', $table)) {
            $table = 'import_'.$table;
        }

        return substr($table, 0, 60);
    }

    /**
     * Generate error report as CSV
     */
    public function generateErrorReport(ImportJob $job): string
    {
        $errors = $this->getErrors($job);
        
        $csvData = [];
        $csvData[] = ['Row Number', 'Error Message', 'Row Data'];
        
        foreach ($errors as $error) {
            $csvData[] = [
                $error['row_number'],
                $error['error_message'],
                json_encode($error['row_data']),
            ];
        }
        
        $filename = 'error_report_' . $job->id . '_' . time() . '.csv';
        $path = storage_path('app/public/exports/' . $filename);
        
        $handle = fopen($path, 'w');
        foreach ($csvData as $row) {
            fputcsv($handle, $row);
        }
        fclose($handle);
        
        return 'exports/' . $filename;
    }
}
