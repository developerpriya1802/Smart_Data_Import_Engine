<?php

namespace App\Http\Controllers;

use App\Models\ImportJob;
use App\Services\ImportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class ImportController extends Controller
{
    protected ImportService $importService;

    public function __construct(ImportService $importService)
    {
        $this->importService = $importService;
    }

    /**
     * Upload file and create import job
     * POST /api/import/upload
     */
    public function upload(Request $request): JsonResponse
    {
        $request->validate([
            'file' => 'required|file|mimes:csv,xlsx,xls|max:10240', // Max 10MB
            'module' => 'nullable|string|max:100',
        ]);

        try {
            $file = $request->file('file');
            $module = $request->input('module', 'generic');
            
            $job = $this->importService->uploadFile($file, $module);
            $headers = $this->importService->getHeaders($job);
            $autoMapping = $this->importService->autoMapColumns($job);
            $mappingService = app(\App\Services\MappingService::class);
            
            return response()->json([
                'success' => true,
                'message' => 'File uploaded successfully',
                'data' => [
                    'job_id' => $job->id,
                    'file_name' => $job->file_name,
                    'import_table_name' => $job->import_table_name,
                    'total_rows' => $job->total_rows,
                    'headers' => $headers,
                    'auto_mapping' => $autoMapping,
                    'field_suggestions' => $mappingService->getFieldSuggestionsForHeaders($headers),
                    'available_fields' => $mappingService->getFieldsFromHeaders($headers),
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to upload file: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Save column mapping
     * POST /api/import/mapping
     */
    public function saveMapping(Request $request): JsonResponse
    {
        $request->validate([
            'job_id' => 'required|exists:import_jobs,id',
            'mapping' => 'required|array',
        ]);

        try {
            $job = ImportJob::findOrFail($request->job_id);
            
            $mapping = array_filter($request->mapping, fn ($field) => filled($field));

            if (empty($mapping)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Mapping validation failed',
                    'errors' => ['Please map at least one Excel column'],
                ], 422);
            }

            $job->update(['column_mapping' => $mapping]);

            return response()->json([
                'success' => true,
                'message' => 'Mapping saved successfully',
                'data' => [
                    'job_id' => $job->id,
                    'mapping' => $mapping,
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to save mapping: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Start import processing
     * POST /api/import/start
     */
    public function start(Request $request): JsonResponse
    {
        $request->validate([
            'job_id' => 'required|exists:import_jobs,id',
            'validation_rules' => 'nullable|array',
        ]);

        try {
            $job = ImportJob::findOrFail($request->job_id);
            
            if ($job->status !== ImportJob::STATUS_PENDING) {
                return response()->json([
                    'success' => false,
                    'message' => 'Job is not in pending status',
                ], 400);
            }

            $mapping = $job->column_mapping;
            
            if (empty($mapping)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Please save column mapping first',
                ], 400);
            }

            $this->importService->saveMappingAndStart(
                $job, 
                $mapping, 
                $request->validation_rules
            );

            return response()->json([
                'success' => true,
                'message' => 'Import started successfully',
                'data' => [
                    'job_id' => $job->id,
                    'status' => $job->status,
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to start import: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Check import status
     * GET /api/import/status/{id}
     */
    public function status(int $id): JsonResponse
    {
        try {
            $job = ImportJob::findOrFail($id);
            $status = $this->importService->getStatus($job);

            return response()->json([
                'success' => true,
                'data' => $status,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Job not found',
            ], 404);
        }
    }

    /**
     * Get import errors
     * GET /api/import/errors/{id}
     */
    public function errors(int $id): JsonResponse
    {
        try {
            $job = ImportJob::findOrFail($id);
            $errors = $this->importService->getErrors($job);

            return response()->json([
                'success' => true,
                'data' => $errors,
                'total' => count($errors),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Job not found',
            ], 404);
        }
    }

    /**
     * Download error report
     * GET /api/import/errors/{id}/download
     */
    public function downloadErrorReport(int $id): JsonResponse
    {
        try {
            $job = ImportJob::findOrFail($id);
            $filePath = $this->importService->generateErrorReport($job);

            return response()->json([
                'success' => true,
                'message' => 'Error report generated',
                'data' => [
                    'download_url' => Storage::url($filePath),
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to generate report: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Retry failed rows
     * POST /api/import/retry/{id}
     */
    public function retry(int $id): JsonResponse
    {
        try {
            $job = ImportJob::findOrFail($id);
            
            if ($job->failed_rows === 0) {
                return response()->json([
                    'success' => false,
                    'message' => 'No failed rows to retry',
                ], 400);
            }

            $this->importService->retryFailedRows($job);

            return response()->json([
                'success' => true,
                'message' => 'Retry started successfully',
                'data' => [
                    'job_id' => $job->id,
                    'status' => $job->status,
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retry: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * List all import jobs
     * GET /api/import/jobs
     */
    public function list(): JsonResponse
    {
        $jobs = ImportJob::orderBy('created_at', 'desc')->get();
        
        return response()->json([
            'success' => true,
            'data' => $jobs,
        ]);
    }
}
