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
        Schema::create('user_reviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreignId('reviewer_id')->references('id')->on('users')->onDelete('cascade');
            $table->text('review')->nullable();
            $table->integer('ratings');
            $table->enum('report_status', ['reported', 'rejected', 'approved'])->nullable();
            $table->string('report_reason')->nullable();
            $table->string('report_rejected_reason')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['user_id', 'reviewer_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_reviews');
    }
};
