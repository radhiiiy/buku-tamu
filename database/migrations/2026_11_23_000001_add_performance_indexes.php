<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Indeks untuk query yang paling sering dipakai (dasbor, daftar kunjungan, laporan, notifikasi). */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('visits', function (Blueprint $table) {
            if (! Schema::hasIndex('visits', ['status', 'arrived_at'])) {
                $table->index(['status', 'arrived_at']);
            }
            if (! Schema::hasIndex('visits', ['host_id', 'arrived_at'])) {
                $table->index(['host_id', 'arrived_at']);
            }
        });

        Schema::table('app_notifications', function (Blueprint $table) {
            if (! Schema::hasIndex('app_notifications', ['user_id', 'read_at'])) {
                $table->index(['user_id', 'read_at']);
            }
            if (! Schema::hasIndex('app_notifications', ['user_id', 'type', 'id'])) {
                $table->index(['user_id', 'type', 'id']);
            }
        });
    }

    public function down(): void
    {
        Schema::table('visits', function (Blueprint $table) {
            $table->dropIndex(['status', 'arrived_at']);
            $table->dropIndex(['host_id', 'arrived_at']);
        });
        Schema::table('app_notifications', function (Blueprint $table) {
            $table->dropIndex(['user_id', 'read_at']);
            $table->dropIndex(['user_id', 'type', 'id']);
        });
    }
};
