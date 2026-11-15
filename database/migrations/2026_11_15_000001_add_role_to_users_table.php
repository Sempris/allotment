<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Existing users were all board members.
            $table->string('role')->default('board');

            // Tenants see exactly one plot.
            $table->foreignId('plot_id')->nullable()->constrained()->nullOnDelete();

            // Users who appear in the reading history are locked instead of deleted.
            $table->boolean('active')->default(true);
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('plot_id');
            $table->dropColumn(['role', 'active']);
        });
    }
};
