<?php

namespace App\Services;

use Illuminate\Support\Facades\Validator;

class ValidationService
{
    /**
     * Default validation rules per field type
     */
    protected array $defaultRules = [
        'name' => 'required|string|max:255',
        'email' => 'required|email',
        'phone' => 'nullable|string|max:20',
        'dob' => 'nullable|date',
        'address' => 'nullable|string|max:500',
        'city' => 'nullable|string|max:100',
        'state' => 'nullable|string|max:100',
        'country' => 'nullable|string|max:100',
        'zipcode' => 'nullable|string|max:20',
        'company' => 'nullable|string|max:255',
        'designation' => 'nullable|string|max:255',
        'status' => 'nullable|string|max:50',
    ];

    /**
     * Validate a single row of data
     */
    public function validateRow(array $row, array $mapping, ?array $customRules = null): array
    {
        $rules = $this->buildRules($mapping, $customRules);
        $data = $this->transformData($row, $mapping);
        
        $validator = Validator::make($data, $rules);
        
        if ($validator->fails()) {
            return [
                'valid' => false,
                'errors' => $validator->errors()->toArray(),
                'data' => $data,
            ];
        }
        
        return [
            'valid' => true,
            'errors' => [],
            'data' => $data,
        ];
    }

    /**
     * Build validation rules based on mapping
     */
    protected function buildRules(array $mapping, ?array $customRules = null): array
    {
        $rules = [];
        
        foreach ($mapping as $header => $field) {
            $rule = $this->defaultRules[$field] ?? 'nullable';
            
            // Allow custom rules to override defaults
            if (isset($customRules[$field])) {
                $rule = $customRules[$field];
            }
            
            $rules[$field] = $rule;
        }
        
        return $rules;
    }

    /**
     * Transform row data using mapping
     */
    protected function transformData(array $row, array $mapping): array
    {
        $data = [];
        
        foreach ($mapping as $header => $field) {
            if (isset($row[$header])) {
                $data[$field] = is_scalar($row[$header]) ? trim((string) $row[$header]) : $row[$header];
            }
        }
        
        return $data;
    }

    /**
     * Validate multiple rows in batch
     */
    public function validateBatch(array $rows, array $mapping, ?array $customRules = null): array
    {
        $results = [
            'valid' => [],
            'invalid' => [],
        ];
        
        foreach ($rows as $index => $row) {
            $result = $this->validateRow($row, $mapping, $customRules);
            $result['row_number'] = $index + 1;
            $result['original_data'] = $row;
            
            if ($result['valid']) {
                $results['valid'][] = $result;
            } else {
                $results['invalid'][] = $result;
            }
        }
        
        return $results;
    }

    /**
     * Get default rules for a field
     */
    public function getDefaultRule(string $field): string
    {
        return $this->defaultRules[$field] ?? 'nullable';
    }

    /**
     * Set custom validation rules
     */
    public function setCustomRules(array $rules): self
    {
        $this->defaultRules = array_merge($this->defaultRules, $rules);
        return $this;
    }
}
