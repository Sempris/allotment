<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tariffs', function (Blueprint $table) {
            $table->id();
            $table->unsignedSmallInteger('year');
            $table->string('meter_type');
            $table->unsignedInteger('price_per_unit_cents');
            $table->unsignedInteger('base_fee_cents')->default(0);
            $table->timestamps();

            $table->unique(['year', 'meter_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tariffs');
    }
};
