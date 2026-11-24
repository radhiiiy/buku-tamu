<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hosts', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('team')->nullable();
            $table->string('email')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('visits', function (Blueprint $table) {
            $table->id();
            $table->string('guest_name');
            $table->string('guest_phone', 30);
            $table->string('guest_company')->nullable();
            $table->string('guest_email')->nullable();
            $table->string('purpose');
            $table->foreignId('host_id')->constrained('hosts');
            $table->text('notes')->nullable();
            $table->string('status')->default('menunggu')->index(); // menunggu|berkunjung|selesai|ditolak
            $table->string('source')->default('qr'); // qr | manual
            $table->timestamp('arrived_at')->index();
            $table->timestamp('confirmed_at')->nullable();
            $table->timestamp('checked_out_at')->nullable();
            $table->timestamp('consented_at')->nullable();
            $table->foreignId('confirmed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('visit_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('visit_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->string('description')->nullable();
            $table->timestamp('created_at')->nullable();
        });

        Schema::create('app_notifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('type'); // visit_new | visit_confirmed | visit_done | visit_rejected
            $table->string('title');
            $table->string('body');
            $table->string('meta')->nullable();
            $table->foreignId('visit_id')->nullable()->constrained()->cascadeOnDelete();
            $table->timestamp('read_at')->nullable();
            $table->timestamps();
        });

        Schema::create('settings', function (Blueprint $table) {
            $table->string('key')->primary();
            $table->text('value')->nullable();
        });

        Schema::create('report_exports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('kind'); // xlsx | csv | sheets
            $table->string('filename');
            $table->string('path')->nullable();
            $table->unsignedInteger('rows_count')->default(0);
            $table->unsignedInteger('columns_count')->default(0);
            $table->date('period_from');
            $table->date('period_to');
            $table->string('url')->nullable();
            $table->json('filters')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        foreach (['report_exports', 'settings', 'app_notifications', 'visit_events', 'visits', 'hosts'] as $t) {
            Schema::dropIfExists($t);
        }
    }
};
