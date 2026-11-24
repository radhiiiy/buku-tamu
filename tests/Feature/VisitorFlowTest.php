<?php

use App\Mail\VisitMail;
use App\Models\Host;
use App\Models\ReportExport;
use App\Models\Setting;
use App\Models\User;
use App\Models\Visit;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;

function payload(Host $host, array $over = []): array
{
    return array_merge([
        'guest_name' => 'Budi Santoso', 'guest_phone' => '0812 3456 7890', 'guest_company' => 'PT Maju Bersama',
        'purpose' => 'Rapat / pertemuan', 'host_id' => $host->id, 'consent' => '1',
    ], $over);
}

beforeEach(function () {
    Mail::fake();
    $this->host = Host::create(['name' => 'Sari Putri', 'team' => 'Tim Operasional', 'email' => 'sari@example.com']);
    $this->user = User::create(['name' => 'Levi', 'email' => 'levi@x.test', 'password' => 'password', 'role' => 'admin', 'can_export_phone' => true]);
});

it('menampilkan halaman buku tamu dalam dua bahasa', function () {
    $this->get('/')->assertOk()->assertSee('Kunjungan baik bermula dari sapa.');
    $this->get('/bahasa/en');
    $this->get('/')->assertSee('Good visits start with a hello.');
});

it('tamu mendaftar, status menunggu, dan penanggung jawab diberi tahu', function () {
    $res = $this->post('/kunjungan', payload($this->host));
    $res->assertRedirect();
    $visit = Visit::firstOrFail();
    expect($visit->status)->toBe('menunggu')->and($visit->code)->toBe('TAMU-0001');
    Mail::assertSent(VisitMail::class, fn ($m) => $m->hasTo('sari@example.com'));
    $this->get($res->headers->get('Location'))->assertOk()->assertSee('#TAMU-0001');
    expect($this->user->notificationsFeed()->count())->toBe(1);
});

it('validasi formulir menolak data tidak lengkap', function () {
    $this->post('/kunjungan', payload($this->host, ['guest_name' => '', 'guest_phone' => 'abc', 'consent' => null]))
        ->assertSessionHasErrors(['guest_name', 'guest_phone', 'consent']);
});

it('halaman bukti harus bertanda tangan', function () {
    $v = Visit::create(['guest_name' => 'A', 'guest_phone' => '081234567', 'purpose' => 'Wawancara', 'host_id' => $this->host->id, 'arrived_at' => now()]);
    $this->get('/kunjungan/berhasil/'.$v->id)->assertForbidden();
});

it('dasbor wajib login', function () {
    $this->get('/admin')->assertRedirect('/masuk');
    $this->actingAs($this->user)->get('/admin')->assertOk()->assertSee('Total tamu hari ini');
});

it('alur konfirmasi lalu check-out menghitung durasi', function () {
    $this->post('/kunjungan', payload($this->host));
    $v = Visit::first();
    $this->actingAs($this->user)->post("/admin/kunjungan/{$v->id}/konfirmasi")->assertSessionHas('success');
    expect($v->fresh()->status)->toBe('berkunjung');
    $this->travel(48)->minutes();
    $this->actingAs($this->user)->post("/admin/kunjungan/{$v->id}/checkout");
    expect($v->fresh()->status)->toBe('selesai')->and($v->fresh()->duration_minutes)->toBe(48);
});

it('check-out ditolak bila belum dikonfirmasi', function () {
    $this->post('/kunjungan', payload($this->host));
    $this->actingAs($this->user)->post('/admin/kunjungan/'.Visit::first()->id.'/checkout')->assertSessionHas('error');
});

it('konfirmasi otomatis saat pengaturan dimatikan', function () {
    Setting::put('require_confirmation', false);
    $this->post('/kunjungan', payload($this->host));
    expect(Visit::first()->status)->toBe('berkunjung');
});

it('resepsionis menambah tamu manual dan filter pencarian bekerja', function () {
    $this->actingAs($this->user)->post('/admin/kunjungan', payload($this->host, ['guest_email' => 'b@x.test']))->assertRedirect();
    $this->actingAs($this->user)->post('/admin/kunjungan', payload($this->host, ['guest_name' => 'Rina Wulandari']));
    $this->actingAs($this->user)->get('/admin/kunjungan?q=Rina')->assertSee('Rina Wulandari')->assertDontSee('Budi Santoso');
});

it('ekspor xlsx tanpa nomor WhatsApp secara default', function () {
    Storage::fake('local');
    $this->post('/kunjungan', payload($this->host));
    $this->actingAs($this->user)->post('/admin/laporan/excel', [
        'format' => 'xlsx', 'filename' => 'Tes', 'cols' => ['id', 'guest', 'phone'], 'from' => today()->toDateString(), 'to' => today()->toDateString(),
    ])->assertRedirect();
    $e = ReportExport::firstOrFail();
    expect($e->filename)->toBe('Tes.xlsx')->and($e->filters['cols'])->toBe(['id', 'guest']);
    Storage::disk('local')->assertExists($e->path);
    $this->actingAs($this->user)->get("/admin/laporan/ekspor/{$e->id}/unduh")->assertOk();
});

it('nomor WhatsApp hanya bisa diekspor bila diizinkan', function () {
    Setting::put('phone_export_enabled', true);
    $this->post('/kunjungan', payload($this->host));
    $this->actingAs($this->user)->post('/admin/laporan/excel', ['format' => 'csv', 'cols' => ['guest', 'phone']]);
    expect(ReportExport::first()->filters['cols'])->toBe(['guest', 'phone']);
});

it('kirim ke Google Sheets membuat spreadsheet baru', function () {
    $this->user->update(['google_access_token' => 'tok', 'google_refresh_token' => 'ref', 'google_expires_at' => now()->addHour(), 'google_email' => 'levi@gmail.test']);
    $this->post('/kunjungan', payload($this->host));
    Http::fake([
        'sheets.googleapis.com/v4/spreadsheets' => Http::response(['spreadsheetId' => 'abc', 'spreadsheetUrl' => 'https://docs.google.com/spreadsheets/d/abc']),
        'sheets.googleapis.com/v4/spreadsheets/abc/values:batchUpdate' => Http::response([]),
    ]);
    $this->actingAs($this->user)->post('/admin/laporan/sheets', ['mode' => 'new', 'title' => 'Lap', 'tab' => 'Data kunjungan', 'summary' => '1', 'header' => '1'])
        ->assertSessionHas('sheets_done');
    Http::assertSentCount(3); // buat + tulis Ringkasan + tulis Data
});

it('preferensi notifikasi tersimpan', function () {
    $this->actingAs($this->user)->postJson('/admin/notifikasi/preferensi', ['key' => 'notify_sound', 'value' => true])->assertOk();
    expect($this->user->fresh()->pref('notify_sound'))->toBeTrue();
});
