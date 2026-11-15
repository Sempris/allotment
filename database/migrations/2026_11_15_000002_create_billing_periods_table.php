<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // A row means: this billing year ends on a key date, and meter values are
        // interpolated to it. No row: the year ends with each meter's last reading.
        Schema::create('billing_periods', function (Blueprint $table) {
            $table->id();
            $table->unsignedSmallInteger('year')->unique();
            $table->date('key_date');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('billing_periods');
    }
};
