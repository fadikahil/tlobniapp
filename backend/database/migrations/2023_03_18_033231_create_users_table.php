<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up() {
        Schema::create('users', static function (Blueprint $table) {
            $table->id();
            $table->string('name')->nullable();
            $table->string('email');
            $table->string('mobile')->nullable();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('profile')->nullable();
            $table->string('type')->comment('email/google/mobile');
            $table->string('password');
            $table->string('fcm_id');
            $table->boolean('notification')->default(0);
            $table->string('firebase_id')->nullable();
            $table->text('address')->nullable();

            $table->string('business_name')->nullable(); // Only for Business Providers
            $table->text('categories')->nullable(); // For Business and Experts
            $table->string('phone')->nullable();
            $table->string('gender')->nullable();
            $table->string('location')->nullable();

            $table->rememberToken();
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['firebase_id', 'type']);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down() {
        Schema::dropIfExists('users');
    }
};
