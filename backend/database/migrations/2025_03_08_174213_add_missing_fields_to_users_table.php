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
        Schema::table('users', function (Blueprint $table) {
            if (!Schema::hasColumn('users', 'platform_type')) {
                $table->string('platform_type')->nullable()->comment('android/ios');
            }
            
            if (!Schema::hasColumn('users', 'provider_type')) {
                $table->string('provider_type')->nullable()->comment('Expert/Business');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'platform_type')) {
                $table->dropColumn('platform_type');
            }
            
            if (Schema::hasColumn('users', 'provider_type')) {
                $table->dropColumn('provider_type');
            }
        });
    }
};
