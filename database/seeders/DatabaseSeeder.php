<?php

namespace Database\Seeders;

use App\Models\Host;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Seeder awal: hanya akun kantor dan daftar penerima notifikasi (penanggung jawab).
 * Tidak ada data kunjungan/notifikasi. Aman dijalankan berulang: data yang sudah ada tidak ditimpa.
 */
class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        foreach (Setting::DEFAULTS as $key => $value) {
            Setting::query()->firstOrCreate(['key' => $key], ['value' => json_encode($value)]);
        }

        // Akun kantor (kata sandi awal: "password", segera ganti).
        User::firstOrCreate(['email' => 'levi@ruangkarya.co.id'], [
            'name' => 'Levi', 'password' => 'password', 'role' => 'admin', 'can_export_phone' => true,
        ]);
        User::firstOrCreate(['email' => 'resepsionis@ruangkarya.co.id'], [
            'name' => 'Resepsionis', 'password' => 'password', 'role' => 'resepsionis',
        ]);

        // Penerima notifikasi: nama, tim, dan email.
        
    }
}
