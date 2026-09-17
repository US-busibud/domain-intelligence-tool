<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('scans', function (Blueprint $table) {
            $table->id();
            $table->string('original_input'); // User ka raw input (e.g., HTTPS://Acme.com/)
            $table->string('normalized_domain'); // Cleaned domain (e.g., acme.com)
            $table->string('brand_name'); // Extracted brand (e.g., acme)
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('scans');
    }
};
