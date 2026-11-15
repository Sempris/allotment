<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Log of bills that were issued to a tenant, by e-mail or as a printout.
        Schema::create('invoice_dispatches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('plot_id')->constrained()->restrictOnDelete();
            $table->unsignedSmallInteger('year');

            // Null: handed over as a printout.
            $table->string('recipient_email')->nullable();

            // Amount at the time of issuing, to notice later changes.
            $table->unsignedInteger('total_cents');

            $table->foreignId('sent_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['plot_id', 'year']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoice_dispatches');
    }
};
