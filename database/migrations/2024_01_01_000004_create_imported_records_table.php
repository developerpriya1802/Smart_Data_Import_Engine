<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('imported_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('job_id')->constrained('import_jobs')->onDelete('cascade');
            $table->unsignedInteger('row_number');
            $table->json('original_data');
            $table->json('mapped_data');
            $table->timestamps();

            $table->index(['job_id', 'row_number']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('imported_records');
    }
};
