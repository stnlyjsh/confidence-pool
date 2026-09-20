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
        Schema::create('ledger_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pool_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('season');
            $table->string('type');
            $table->unsignedTinyInteger('week')->nullable();
            // Positive = the user owes the pool (a buy-in); negative = the
            // pool owes the user (a payout).
            $table->integer('amount_cents');
            $table->boolean('is_paid')->default(false);
            $table->timestamp('paid_at')->nullable();
            $table->foreignId('marked_paid_by_user_id')->nullable()->constrained('users');
            $table->string('notes')->nullable();
            $table->timestamps();

            $table->unique(['pool_id', 'user_id', 'season', 'type', 'week']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ledger_entries');
    }
};
