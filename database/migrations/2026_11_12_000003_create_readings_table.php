<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('readings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('meter_id')->constrained()->restrictOnDelete();
            $table->date('read_on');
            $table->unsignedBigInteger('value_milli');
            $table->string('source');
            $table->string('note')->nullable();
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();

            // Readings are never edited in place. A correction creates a new row
            // and points the original at it, so the history stays complete.
            $table->foreignId('superseded_by_id')->nullable()->constrained('readings')->nullOnDelete();

            // Soft plausibility warnings: the reading is stored, but the board should look at it.
            $table->string('flag_reason')->nullable();
            $table->timestamp('flag_reviewed_at')->nullable();
            $table->foreignId('flag_reviewed_by')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamps();

            $table->index(['meter_id', 'read_on']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('readings');
    }
};
