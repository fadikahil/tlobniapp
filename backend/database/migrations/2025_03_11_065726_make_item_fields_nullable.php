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
            $table->text('description')->nullable()->change();
            $table->text('address')->nullable()->change();
            $table->string('image')->nullable()->change();
            $table->string('country')->nullable()->change();
            $table->string('city')->nullable()->change();
            $table->boolean('show_only_to_premium')->default(0)->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('items', function (Blueprint $table) {
            $table->text('description')->nullable(false)->change();
            $table->text('address')->nullable(false)->change();
            $table->string('image')->nullable(false)->change();
            $table->string('country')->nullable(false)->change();
            $table->string('city')->nullable(false)->change();
            $table->boolean('show_only_to_premium')->nullable(false)->change();
        });
    }
};
