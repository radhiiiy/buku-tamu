<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Aman dijalankan di atas skeleton Laravel: jika tabel `users` bawaan sudah ada,
 * hanya kolom yang belum ada yang ditambahkan.
 */
return new class extends Migration
{
    private array $columns = ['role', 'can_export_phone', 'prefs', 'google_email', 'google_access_token', 'google_refresh_token', 'google_expires_at'];

    public function up(): void
    {
        if (! Schema::hasTable('users')) {
            Schema::create('users', function (Blueprint $table) {
                $table->id();
                $table->string('name');
                $table->string('email')->unique();
                $table->string('password');
                $table->rememberToken();
                $table->timestamps();
            });
        }

        Schema::table('users', function (Blueprint $table) {
            if (! Schema::hasColumn('users', 'role')) {
                $table->string('role')->default('resepsionis'); // resepsionis | admin
            }
            if (! Schema::hasColumn('users', 'can_export_phone')) {
                $table->boolean('can_export_phone')->default(false);
            }
            if (! Schema::hasColumn('users', 'prefs')) {
                $table->json('prefs')->nullable();
            }
            if (! Schema::hasColumn('users', 'google_email')) {
                $table->string('google_email')->nullable();
            }
            if (! Schema::hasColumn('users', 'google_access_token')) {
                $table->text('google_access_token')->nullable();
            }
            if (! Schema::hasColumn('users', 'google_refresh_token')) {
                $table->text('google_refresh_token')->nullable();
            }
            if (! Schema::hasColumn('users', 'google_expires_at')) {
                $table->timestamp('google_expires_at')->nullable();
            }
        });
    }

    public function down(): void
    {
        $existing = array_filter($this->columns, fn ($c) => Schema::hasColumn('users', $c));
        if ($existing) {
            Schema::table('users', fn (Blueprint $table) => $table->dropColumn(array_values($existing)));
        }
    }
};
