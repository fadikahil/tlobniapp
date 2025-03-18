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
        Schema::table('items', function (Blueprint $table) {
            $table->string('price_type')->nullable()->after('price');
            $table->json('special_tags')->nullable()->after('price_type');
            $table->string('location_type')->nullable()->after('country');
            $table->timestamp('expiration_date')->nullable()->after('expiry_date');
            $table->string('expiration_time')->nullable()->after('expiration_date');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('items', function (Blueprint $table) {
            $table->dropColumn('price_type');
            $table->dropColumn('special_tags');
            $table->dropColumn('location_type');
            $table->dropColumn('expiration_date');
            $table->dropColumn('expiration_time');
        });
    }
};
