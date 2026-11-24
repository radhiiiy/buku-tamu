<?php

namespace Database\Seeders;

use App\Models\Host;
use App\Services\WhatsAppService;
use Illuminate\Database\Seeder;

/**
 * Seeder penerima notifikasi (penanggung jawab).
 * Aman dijalankan berulang: dicocokkan berdasarkan email, data yang ada diperbarui.
 *
 * Jalankan: php artisan db:seed --class=HostSeeder
 */
class HostSeeder extends Seeder
{
    public function run(): void
    {
        $wa = app(WhatsAppService::class);

        // [nama, jabatan (disimpan di kolom team), email, nomor WhatsApp]
        $hosts = [
            ['M Levi Sutansyah', 'Man SDM dan Umum', 'levisutansyah@gmail.com', '+62 812-6168-5327'],
            ['Dinda Esmerelga', 'Supervisi Keuangan', 'esmerelgadinda@gmail.com', '082339033034'],
            ['Corry Lidya Vega', 'Adm Bagian Pajak', 'corrylidyavega@gmail.com', '087755647901'],
            ['Rizky Robert Candra Junior', 'Staff Logistik Pusat', 'rizkyrobert46@gmail.com', '+62 851-5663-1513'],
            ['Arif Muhazalin', 'DPA', 'arifdpa@yahoo.co.id', '+62 852-5818-1063'],
            ['Muhammad Farid', 'Staff SDM', 'm.faridd73@gmail.com', '+62 822-8517-0751'],
            ['Fajar Fathoni', 'Logistik dan Sertifikasi Sabang', 'fathoniarifinfajar@gmail.com', '+62 822-2841-4292'],
            ['Aswandi Utomo', 'Kepala Cabang', 'aswandiutomo82@gmail.com', '+62 821-1116-3608'],
            ['Agus Subiantoro', 'Staff DPA', 'agussubian@gmail.com', '+62 811-8474-9501'],
        ];

        foreach ($hosts as [$name, $team, $email, $phone]) {
            Host::updateOrCreate(['email' => $email], [
                'name' => $name,
                'team' => $team,
                'phone' => $wa->normalize($phone) ?: null,
                'is_active' => true,
            ]);
        }
    }
}
