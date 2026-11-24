<?php

use App\Mail\VisitMail;
use App\Models\AppNotification;
use App\Models\Host;
use App\Models\Setting;
use App\Models\User;
use App\Models\Visit;
use App\Services\ReportService;
use App\Services\WhatsAppService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;

beforeEach(function () {
    Mail::fake();
    $this->host = Host::create(['name' => 'Sari Putri', 'team' => 'Ops', 'email' => 'sari@example.com']);
    $this->admin = User::create(['name' => 'Levi', 'email' => 'levi@x.test', 'password' => 'password', 'role' => 'admin']);
});

function newVisit(Host $host, array $over = []): Visit
{
    return Visit::create(array_merge([
        'guest_name' => 'Tamu', 'guest_phone' => '081234567', 'purpose' => 'Wawancara', 'host_id' => $host->id,
        'status' => 'menunggu', 'arrived_at' => now(),
    ], $over));
}

it('seeder hanya mengisi akun kantor, penerima notifikasi, dan pengaturan (tanpa data kunjungan)', function () {
    $this->admin->delete();
    $this->host->delete();

    $this->seed(DatabaseSeeder::class);

    expect(Visit::count())->toBe(0)
        ->and(AppNotification::count())->toBe(0)
        ->and(User::pluck('email')->all())->toEqualCanonicalizing(['levi@ruangkarya.co.id', 'resepsionis@ruangkarya.co.id'])
        ->and(Host::count())->toBe(5)
        ->and(Host::whereNull('email')->count())->toBe(0)
        ->and(Setting::get('office_name'))->not->toBeEmpty();
});

it('seeder aman dijalankan ulang dan tidak menimpa perubahan', function () {
    $this->seed(DatabaseSeeder::class);
    $u = User::where('email', 'levi@ruangkarya.co.id')->first();
    $u->update(['password' => 'rahasia-baru']);
    Setting::put('office_name', 'Kantor Baru');

    $this->seed(DatabaseSeeder::class);

    expect(Hash::check('rahasia-baru', $u->fresh()->password))->toBeTrue()
        ->and(Setting::get('office_name'))->toBe('Kantor Baru')
        ->and(User::count())->toBe(3) // admin test + 2 akun kantor
        ->and(Host::count())->toBe(5); // 'Sari Putri' sudah ada, tidak diduplikasi
});

it('tabel sesi tersedia setelah migrasi', function () {
    expect(Schema::hasTable('sessions'))->toBeTrue();
});

it('konfirmasi ganda tidak memproses kunjungan dua kali', function () {
    $v = newVisit($this->host);
    $this->actingAs($this->admin)->post("/admin/kunjungan/{$v->id}/konfirmasi")->assertSessionHas('success');
    $this->actingAs($this->admin)->post("/admin/kunjungan/{$v->id}/konfirmasi")->assertSessionHas('error');

    expect($v->events()->where('title', 'Kedatangan dikonfirmasi')->count())->toBe(1);
});

it('pengingat gagal bila penanggung jawab tidak punya email', function () {
    $h = Host::create(['name' => 'Tanpa Email', 'team' => 'X']);
    $v = newVisit($h);
    $this->actingAs($this->admin)->post("/admin/kunjungan/{$v->id}/pengingat")->assertSessionHas('error');
    Mail::assertNothingSent();
});

it('email tamu baru dikirim ke penanggung jawab dan petugas (tanpa duplikat)', function () {
    User::create(['name' => 'Rani', 'email' => 'sari@example.com', 'password' => 'password']); // email sama dengan host
    $this->post('/kunjungan', ['guest_name' => 'Budi', 'guest_phone' => '081234567', 'purpose' => 'Wawancara', 'host_id' => $this->host->id, 'consent' => '1']);

    Mail::assertSent(VisitMail::class, 2); // host (sari@) + levi@, bukan 3
    Mail::assertSent(VisitMail::class, fn ($m) => $m->hasTo('levi@x.test'));
});

it('parameter query yang salah bentuk tidak menyebabkan error 500', function () {
    $this->actingAs($this->admin);
    foreach ([
        '/admin?q[]=x&status[]=y&range[]=1',
        '/admin/kunjungan?q[]=x&date[]=1&host[]=2&status[]=a&sort[]=b',
        '/admin/kunjungan?date=bukan-tanggal',
        '/admin/laporan?from[]=x&to[]=y&status[]=z&host[]=1',
        '/admin/laporan/excel?cols=abc',
        '/admin/laporan/excel/pratinjau?cols=abc',
        '/admin/notifikasi?tab[]=1',
    ] as $url) {
        $this->get($url)->assertOk();
    }
});

it('tanggal tidak valid pada daftar kunjungan kembali ke hari ini', function () {
    newVisit($this->host, ['guest_name' => 'Hari Ini']);
    $this->actingAs($this->admin)->get('/admin/kunjungan?date=2026-02-31')->assertOk()->assertSee('Hari Ini');
});

it('dasbor menghitung statistik dan tren dari database', function () {
    newVisit($this->host, ['status' => 'menunggu']);
    newVisit($this->host, ['status' => 'berkunjung']);
    newVisit($this->host, ['status' => 'selesai', 'checked_out_at' => now()->addMinutes(30)]);
    newVisit($this->host, ['status' => 'selesai', 'arrived_at' => now()->subDay(), 'checked_out_at' => now()->subDay()->addMinutes(10)]);

    $r = $this->actingAs($this->admin)->get('/admin')->assertOk();
    $d = $r->viewData('total');

    expect($d)->toBe(3)
        ->and($r->viewData('waiting'))->toBe(1)
        ->and($r->viewData('visiting'))->toBe(1)
        ->and($r->viewData('done'))->toBe(1)
        ->and($r->viewData('diff'))->toBe(2)
        ->and($r->viewData('trendTotal'))->toBe(4)
        ->and($r->viewData('pendingCount'))->toBe(1);
});

it('ringkasan & rekap harian laporan akurat', function () {
    $a = now()->setTime(9, 0);
    newVisit($this->host, ['status' => 'selesai', 'arrived_at' => $a, 'checked_out_at' => $a->copy()->addMinutes(20)]);
    newVisit($this->host, ['status' => 'selesai', 'arrived_at' => $a->copy()->subDay(), 'checked_out_at' => $a->copy()->subDay()->addMinutes(40)]);
    newVisit($this->host, ['status' => 'menunggu', 'arrived_at' => $a]);
    newVisit($this->host, ['status' => 'ditolak', 'arrived_at' => $a]);

    $svc = app(ReportService::class);
    $f = $svc->filters(['from' => today()->subDay()->toDateString(), 'to' => today()->toDateString()]);
    $s = $svc->summary($f);
    $daily = $svc->daily($f);

    expect($s)->toMatchArray(['total' => 4, 'done' => 2, 'waiting' => 1, 'rejected' => 1, 'unfinished' => 1, 'percent_done' => 50, 'avg_duration' => 30])
        ->and($daily[0])->toMatchArray(['total' => 3, 'waiting' => 1, 'done' => 1])
        ->and($daily[1])->toMatchArray(['total' => 1, 'done' => 1]);
});

it('rentang tanggal laporan yang tidak valid memakai default', function () {
    $f = app(ReportService::class)->filters(['from' => '2026-02-31', 'to' => 'bukan-tanggal', 'status' => ['x'], 'host' => ['1']]);

    expect($f['to']->isToday())->toBeTrue()
        ->and($f['status'])->toBe('all')
        ->and($f['host'])->toBe('all');
});

it('admin mengelola penerima notifikasi: tambah, ubah email, nonaktifkan, hapus', function () {
    $this->actingAs($this->admin)->get('/admin/pengaturan/penerima')->assertOk()->assertSee('sari@example.com');

    $this->post('/admin/pengaturan/penerima', ['name' => 'Budi', 'team' => 'IT', 'email' => 'budi@kantor.test'])->assertSessionHas('success');
    $budi = Host::where('name', 'Budi')->firstOrFail();
    expect($budi->is_active)->toBeTrue();

    $this->put("/admin/pengaturan/penerima/{$budi->id}", ['name' => 'Budi S', 'team' => 'IT', 'email' => 'budi.s@kantor.test'])->assertSessionHas('success');
    expect($budi->fresh()->email)->toBe('budi.s@kantor.test');

    $this->post("/admin/pengaturan/penerima/{$budi->id}/aktif");
    expect($budi->fresh()->is_active)->toBeFalse();
    $this->get('/kunjungan')->assertDontSee('Budi S');

    $this->delete("/admin/pengaturan/penerima/{$budi->id}")->assertSessionHas('success');
    expect(Host::find($budi->id))->toBeNull();
});

it('email penerima harus valid dan unik; penerima beriwayat tidak bisa dihapus', function () {
    $this->actingAs($this->admin);
    $this->post('/admin/pengaturan/penerima', ['name' => 'X', 'email' => 'bukan-email'])->assertSessionHasErrorsIn('hostNew', 'email');
    $this->post('/admin/pengaturan/penerima', ['name' => 'Dobel', 'email' => 'sari@example.com'])->assertSessionHasErrorsIn('hostNew', 'email');

    newVisit($this->host);
    $this->delete("/admin/pengaturan/penerima/{$this->host->id}")->assertSessionHas('error');
    expect(Host::find($this->host->id))->not->toBeNull();
});

it('resepsionis hanya bisa melihat daftar penerima', function () {
    $rani = User::create(['name' => 'Rani', 'email' => 'rani@x.test', 'password' => 'password', 'role' => 'resepsionis']);
    $this->actingAs($rani)->get('/admin/pengaturan/penerima')->assertOk();
    $this->post('/admin/pengaturan/penerima', ['name' => 'Z'])->assertForbidden();
    $this->put("/admin/pengaturan/penerima/{$this->host->id}", ['name' => 'Z'])->assertForbidden();
});

it('email tamu baru hanya ke penerima yang dipilih tamu', function () {
    $lain = Host::create(['name' => 'Andi', 'team' => 'HRD', 'email' => 'andi@example.com']);
    $this->post('/kunjungan', ['guest_name' => 'Budi', 'guest_phone' => '081234567', 'purpose' => 'Wawancara', 'host_id' => $lain->id, 'consent' => '1']);

    Mail::assertSent(VisitMail::class, fn ($m) => $m->hasTo('andi@example.com'));
    Mail::assertNotSent(VisitMail::class, fn ($m) => $m->hasTo('sari@example.com'));
});

it('nomor WhatsApp dinormalkan ke format 62', function () {
    $wa = app(WhatsAppService::class);
    expect($wa->normalize('0812-3456-7890'))->toBe('6281234567890')
        ->and($wa->normalize('+62 812 3456 7890'))->toBe('6281234567890')
        ->and($wa->normalize('81234567890'))->toBe('6281234567890')
        ->and($wa->normalize('abc'))->toBe('')
        ->and($wa->normalize(null))->toBe('');
});

it('penerima menyimpan nomor WhatsApp yang sudah dinormalkan dan menolak nomor ngawur', function () {
    $this->actingAs($this->admin);
    $this->post('/admin/pengaturan/penerima', ['name' => 'Wa', 'phone' => '0812 3456 7890'])->assertSessionHas('success');
    expect(Host::where('name', 'Wa')->value('phone'))->toBe('6281234567890');
    $this->post('/admin/pengaturan/penerima', ['name' => 'Bad', 'phone' => 'halo'])->assertSessionHasErrorsIn('hostNew', 'phone');
});

it('tamu baru memicu WhatsApp ke penerima yang dipilih lewat Fonnte', function () {
    config(['whatsapp.driver' => 'fonnte', 'whatsapp.fonnte.token' => 'tok-123']);
    Http::fake(['api.fonnte.com/*' => Http::response(['status' => true])]);
    $this->host->update(['phone' => '6281234567890']);

    $this->post('/kunjungan', ['guest_name' => 'Budi', 'guest_phone' => '081200000000', 'guest_company' => 'PT Maju', 'purpose' => 'Wawancara', 'host_id' => $this->host->id, 'consent' => '1']);

    Http::assertSent(fn ($r) => $r->url() === 'https://api.fonnte.com/send'
        && $r->hasHeader('Authorization', 'tok-123')
        && $r['target'] === '6281234567890'
        && str_contains($r['message'], 'Budi') && str_contains($r['message'], 'TAMU-0001')
        && ! str_contains($r['message'], '081200000000')); // nomor tamu tidak dibocorkan
    expect(Visit::first()->events()->where('title', 'Penanggung jawab diberi tahu')->value('description'))->toContain('email dan WhatsApp');
});

it('driver log tidak memanggil API dan kegagalan WhatsApp tidak membatalkan kunjungan', function () {
    Http::fake();
    $this->host->update(['phone' => '6281234567890']);
    $this->post('/kunjungan', ['guest_name' => 'Budi', 'guest_phone' => '081200000000', 'purpose' => 'Wawancara', 'host_id' => $this->host->id, 'consent' => '1'])->assertRedirect();
    Http::assertNothingSent();

    config(['whatsapp.driver' => 'fonnte', 'whatsapp.fonnte.token' => 'x']);
    Http::fake(['api.fonnte.com/*' => Http::response(['status' => false, 'reason' => 'device disconnected'])]);
    $this->post('/kunjungan', ['guest_name' => 'Rina', 'guest_phone' => '081200000000', 'purpose' => 'Wawancara', 'host_id' => $this->host->id, 'consent' => '1'])->assertRedirect();
    expect(Visit::count())->toBe(2);
});

it('pengingat bisa dikirim lewat WhatsApp walau penerima tanpa email', function () {
    config(['whatsapp.driver' => 'fonnte', 'whatsapp.fonnte.token' => 'x']);
    Http::fake(['api.fonnte.com/*' => Http::response(['status' => true])]);
    $h = Host::create(['name' => 'Hanya WA', 'phone' => '6281234567891']);
    $v = newVisit($h);

    $this->actingAs($this->admin)->post("/admin/kunjungan/{$v->id}/pengingat")->assertSessionHas('success');
    Http::assertSentCount(1);
});

it('halaman detail kunjungan menampilkan saluran notifikasi', function () {
    $this->post('/kunjungan', ['guest_name' => 'Budi', 'guest_phone' => '081200000000', 'purpose' => 'Wawancara', 'host_id' => $this->host->id, 'consent' => '1']);
    $this->actingAs($this->admin)->get('/admin/kunjungan/'.Visit::first()->id)->assertOk()->assertSee('melalui email');
});
