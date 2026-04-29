<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('imported_structured_records');

        Schema::create('imported_structured_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('job_id')->constrained('import_jobs')->onDelete('cascade');
            $table->unsignedInteger('row_number');
            $table->string('name')->nullable();
            $table->string('email', 191)->nullable();
            $table->string('phone', 191)->nullable();
            $table->string('dob')->nullable();
            $table->text('address')->nullable();
            $table->string('city')->nullable();
            $table->string('state')->nullable();
            $table->string('country')->nullable();
            $table->string('zipcode')->nullable();
            $table->string('company')->nullable();
            $table->string('designation')->nullable();
            $table->string('status')->nullable();
            $table->string('source_created_at')->nullable();
            $table->timestamps();

            $table->index(['job_id', 'row_number']);
            $table->index('email');
            $table->index('phone');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('imported_structured_records');
    }
};
