<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('hosts', 'phone')) {
            Schema::table('hosts', function (Blueprint $table) {
                $table->string('phone', 20)->nullable()->after('email'); // nomor WhatsApp, format 628xxx
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('hosts', 'phone')) {
            Schema::table('hosts', fn (Blueprint $table) => $table->dropColumn('phone'));
        }
    }
};
