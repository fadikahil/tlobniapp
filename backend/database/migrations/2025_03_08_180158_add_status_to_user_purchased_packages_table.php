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
        Schema::table('user_purchased_packages', function (Blueprint $table) {
            $table->tinyInteger('status')->default(0)->comment('0: Blocked, 1: Approved')->after('used_limit');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('user_purchased_packages', function (Blueprint $table) {
            $table->dropColumn('status');
        });
    }
};
