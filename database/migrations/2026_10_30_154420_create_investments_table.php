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
        Schema::create('investments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('plan_id')->constrained()->restrictOnDelete();
            $table->unsignedBigInteger('amount_sats');
            $table->unsignedInteger('target_return_bps');
            $table->unsignedSmallInteger('duration_days');
            $table->unsignedBigInteger('earned_sats')->default(0);
            $table->unsignedBigInteger('gold_mg')->nullable();
            $table->string('status')->default('active')->index();
            $table->timestamp('starts_at');
            $table->timestamp('ends_at')->index();
            $table->timestamp('settled_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('investments');
    }
};
