<?php

namespace App\Imports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

class GenericImport implements ToCollection, WithHeadingRow
{
    public function collection(Collection $rows)
    {
        // This is a generic import handler
        // You can customize this to handle different import types
        foreach ($rows as $row) {
            // Process each row
            // Example: YourModel::create($row->toArray());
        }
    }
}