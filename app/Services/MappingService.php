<?php

namespace App\Services;

use Illuminate\Support\Str;

class MappingService
{
    /**
     * Common field variations for auto-mapping
     */
    protected array $fieldMappings = [
        'name' => ['full name', 'fullname', 'customer name', 'user name', 'username', 'first name', 'firstname', 'full_name'],
        'email' => ['email', 'email address', 'email_address', 'mail', 'e-mail', 'emailaddress'],
        'phone' => ['phone', 'phone no', 'phoneno', 'phone number', 'phone_number', 'mobile', 'mobile number', 'contact', 'contact no'],
        'dob' => ['dob', 'date of birth', 'date_of_birth', 'birth date', 'birth_date', 'birthday'],
        'address' => ['address', 'address line', 'address_line', 'full address', 'full_address'],
        'city' => ['city', 'town', 'location'],
        'state' => ['state', 'province', 'region'],
        'country' => ['country', 'nation', 'nationality'],
        'zipcode' => ['zipcode', 'zip code', 'zip_code', 'postal code', 'postal_code', 'pincode', 'pin code'],
        'company' => ['company', 'company name', 'company_name', 'organization', 'org'],
        'designation' => ['designation', 'job title', 'job_title', 'role', 'position'],
        'status' => ['status', 'is active', 'is_active', 'active'],
        'created_at' => ['created at', 'created_at', 'created date', 'registration date'],
    ];

    /**
     * Auto-map CSV headers to system fields
     */
    public function autoMap(array $headers): array
    {
        $mapping = [];
        
        foreach ($headers as $header) {
            if (! $this->isUsableHeader($header)) {
                continue;
            }

            $header = trim((string) $header);
            $mapping[$header] = $this->findMatchingField($header) ?? $this->fieldNameFromHeader($header);
        }
        
        return $mapping;
    }

    /**
     * Find the best matching system field for a header
     */
    protected function findMatchingField(string $header): ?string
    {
        return $this->getFieldSuggestions($header)[0] ?? null;
    }

    public function getFieldSuggestions(string $header): array
    {
        $headerField = $this->fieldNameFromHeader($header);
        $normalizedHeader = $this->normalize($header);
        $scores = [];

        foreach ($this->fieldMappings as $systemField => $variations) {
            $searchTerms = array_merge([$systemField], $variations);

            foreach ($searchTerms as $term) {
                $normalizedTerm = $this->normalize($term);

                if ($normalizedHeader === $normalizedTerm) {
                    $scores[$systemField] = max($scores[$systemField] ?? 0, 100);
                    continue;
                }

                if (str_contains($normalizedHeader, $normalizedTerm) || str_contains($normalizedTerm, $normalizedHeader)) {
                    $scores[$systemField] = max($scores[$systemField] ?? 0, 85);
                    continue;
                }

                similar_text($normalizedHeader, $normalizedTerm, $percentage);

                if ($percentage >= 80) {
                    $scores[$systemField] = max($scores[$systemField] ?? 0, (int) $percentage);
                }
            }
        }

        arsort($scores);

        if (empty($scores)) {
            return [$headerField];
        }

        $topScore = max($scores);

        if ($topScore >= 85) {
            return $this->uniqueFields(array_merge(
                [$headerField],
                array_keys(array_filter($scores, fn (int $score) => $score === $topScore))
            ));
        }

        return $this->uniqueFields(array_merge(
            [$headerField],
            array_keys(array_filter($scores, fn (int $score) => $score >= 80))
        ));
    }

    public function getFieldSuggestionsForHeaders(array $headers): array
    {
        $suggestions = [];

        foreach ($headers as $header) {
            if (! $this->isUsableHeader($header)) {
                continue;
            }

            $header = trim((string) $header);
            $suggestions[$header] = $this->getFieldSuggestions($header);
        }

        return $suggestions;
    }

    protected function normalize(string $value): string
    {
        return trim(preg_replace('/[^a-z0-9]+/', ' ', strtolower($value)));
    }

    public function fieldNameFromHeader(string $header): string
    {
        return Str::snake($this->normalize($header));
    }

    public function getFieldsFromHeaders(array $headers): array
    {
        return array_values(array_unique(array_map(
            fn ($header) => $this->fieldNameFromHeader(trim((string) $header)),
            array_filter($headers, fn ($header) => $this->isUsableHeader($header))
        )));
    }

    protected function uniqueFields(array $fields): array
    {
        return array_values(array_unique(array_filter($fields)));
    }

    protected function isUsableHeader(mixed $header): bool
    {
        return $header !== null && trim((string) $header) !== '';
    }

    /**
     * Get available system fields
     */
    public function getAvailableFields(): array
    {
        return array_keys($this->fieldMappings);
    }

    /**
     * Validate mapping has required fields
     */
    public function validateMapping(array $mapping, array $requiredFields): array
    {
        $errors = [];
        $mappedFields = array_values($mapping);
        
        foreach ($requiredFields as $field) {
            if (!in_array($field, $mappedFields)) {
                $errors[] = "Required field '{$field}' is not mapped";
            }
        }
        
        return $errors;
    }

    /**
     * Get confidence score for mapping
     */
    public function getMappingConfidence(array $mapping, array $headers): float
    {
        if (empty($headers)) {
            return 0;
        }
        
        $mappedCount = count($mapping);
        $totalHeaders = count($headers);
        
        return round(($mappedCount / $totalHeaders) * 100, 2);
    }
}
