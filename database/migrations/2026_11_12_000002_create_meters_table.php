<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('meters', function (Blueprint $table) {
            $table->id();
            $table->foreignId('plot_id')->constrained()->restrictOnDelete();
            $table->string('type');
            $table->string('serial_number')->unique();
            $table->date('installed_on');
            $table->date('removed_on')->nullable();
            $table->unsignedBigInteger('initial_value_milli')->default(0);
            $table->timestamps();

            $table->index(['plot_id', 'type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('meters');
    }
};
