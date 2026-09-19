<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('candidates', function (Blueprint $table) {
            $table->unsignedTinyInteger('ownership_score')
                ->nullable()
                ->after('variation_domain');

            $table->string('ownership_classification')
                ->nullable()
                ->after('ownership_score');

            $table->json('ownership_reasons')
                ->nullable()
                ->after('ownership_classification');
        });
    }

    public function down(): void
{
    Schema::table('candidates', function (Blueprint $table) {
        if (Schema::hasColumn('candidates', 'ownership_score')) {
            $table->dropColumn('ownership_score');
        }

        if (Schema::hasColumn('candidates', 'ownership_classification')) {
            $table->dropColumn('ownership_classification');
        }

        if (Schema::hasColumn('candidates', 'ownership_reasons')) {
            $table->dropColumn('ownership_reasons');
        }
    });
}
};
