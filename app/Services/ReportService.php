<?php

namespace App\Services;

use App\Models\Host;
use App\Models\Setting;
use App\Models\User;
use App\Models\Visit;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

class ReportService
{
    public const COLUMNS = [
        'id' => 'ID kunjungan',
        'date' => 'Tanggal kunjungan',
        'guest' => 'Nama tamu',
        'company' => 'Instansi / perusahaan',
        'host' => 'Penanggung jawab',
        'purpose' => 'Tujuan kunjungan',
        'in' => 'Waktu masuk',
        'out' => 'Waktu keluar',
        'duration' => 'Durasi (menit)',
        'status' => 'Status kunjungan',
        'phone' => 'Nomor WhatsApp',
    ];

    /** Normalisasi filter dari request: periode (maks 366 hari), status, penanggung jawab. */
    public function filters(array $in): array
    {
        $to = $this->date($this->str($in['to'] ?? null), now()->startOfDay());
        $from = $this->date($this->str($in['from'] ?? null), $to->copy()->subDays(6));
        if ($from->gt($to)) {
            [$from, $to] = [$to, $from];
        }
        if ($from->diffInDays($to) > 365) {
            $from = $to->copy()->subDays(365);
        }
        $status = $this->str($in['status'] ?? null) ?: 'all';
        $host = $this->str($in['host'] ?? null) ?: 'all';

        return [
            'from' => $from->startOfDay(),
            'to' => $to->startOfDay(),
            'status' => array_key_exists($status, Visit::STATUSES) ? $status : 'all',
            'host' => ctype_digit((string) $host) && Host::whereKey((int) $host)->exists() ? (int) $host : 'all',
        ];
    }

    /** Query dasar tanpa eager load (untuk agregat). */
    private function base(array $f): Builder
    {
        return Visit::query()
            ->whereBetween('arrived_at', [$f['from']->copy()->startOfDay(), $f['to']->copy()->endOfDay()])
            ->when($f['status'] !== 'all', fn ($q) => $q->where('status', $f['status']))
            ->when($f['host'] !== 'all', fn ($q) => $q->where('host_id', $f['host']));
    }

    public function query(array $f): Builder
    {
        return $this->base($f)->with('host');
    }

    public function summary(array $f): array
    {
        $by = $this->base($f)->selectRaw('status, count(*) as c')->groupBy('status')->pluck('c', 'status');
        $total = (int) $by->sum();
        $done = (int) ($by['selesai'] ?? 0);

        // Rata-rata durasi hanya perlu dua kolom dari kunjungan selesai.
        $avg = $this->base($f)->where('status', 'selesai')->whereNotNull('checked_out_at')
            ->get(['arrived_at', 'checked_out_at'])
            ->avg(fn ($v) => $v->arrived_at->diffInMinutes($v->checked_out_at, true));

        return [
            'total' => $total,
            'done' => $done,
            'waiting' => (int) ($by['menunggu'] ?? 0),
            'visiting' => (int) ($by['berkunjung'] ?? 0),
            'rejected' => (int) ($by['ditolak'] ?? 0),
            'unfinished' => (int) (($by['menunggu'] ?? 0) + ($by['berkunjung'] ?? 0)),
            'percent_done' => $total ? (int) round($done / $total * 100) : 0,
            'avg_duration' => $avg ? (int) round($avg) : 0,
        ];
    }

    /** Rekap per tanggal, terbaru di atas (dihitung di database). */
    public function daily(array $f): array
    {
        $rows = $this->base($f)->selectRaw('DATE(arrived_at) as d, status, count(*) as c')->groupBy('d', 'status')->get();
        $byDay = [];
        foreach ($rows as $r) {
            $byDay[$r->d][$r->status] = (int) $r->c;
        }

        $out = [];
        for ($d = $f['to']->copy(); $d->gte($f['from']); $d->subDay()) {
            $g = $byDay[$d->format('Y-m-d')] ?? [];
            $out[] = [
                'date' => $d->copy(),
                'total' => array_sum($g),
                'waiting' => $g['menunggu'] ?? 0,
                'visiting' => $g['berkunjung'] ?? 0,
                'done' => $g['selesai'] ?? 0,
            ];
        }

        return $out;
    }

    /** Kolom yang boleh diekspor oleh user ini (nomor WhatsApp butuh izin + pengaturan privasi). */
    public function allowedColumns(User $user): array
    {
        $cols = array_keys(self::COLUMNS);

        return $this->canExportPhone($user) ? $cols : array_values(array_diff($cols, ['phone']));
    }

    public function canExportPhone(User $user): bool
    {
        return $user->can_export_phone && Setting::bool('phone_export_enabled', false);
    }

    /** Daftar kunci kolom terpilih yang valid; default semua kecuali nomor WhatsApp. */
    public function selectColumns(mixed $requested, User $user): array
    {
        $allowed = $this->allowedColumns($user);
        if (! is_array($requested)) {
            return array_values(array_diff($allowed, ['phone']));
        }

        return array_values(array_filter(array_keys(self::COLUMNS), fn ($k) => in_array($k, $requested, true) && in_array($k, $allowed, true)));
    }

    public function headers(array $cols): array
    {
        return array_map(fn ($k) => self::COLUMNS[$k], $cols);
    }

    /** @return array<int, array<int, string|int>> */
    public function rows(array $f, array $cols, ?int $limit = null): array
    {
        $q = $this->query($f)->orderBy('arrived_at', 'desc')->orderByDesc('id');
        if ($limit) {
            $q->limit($limit);
        } else {
            $q->limit((int) config('visitor.export_max_rows'));
        }

        return $q->get()->map(fn (Visit $v) => array_map(fn ($c) => $this->cell($v, $c), $cols))->all();
    }

    public function cell(Visit $v, string $col): string|int
    {
        return match ($col) {
            'id' => $v->code,
            'date' => $v->arrived_at->format('Y-m-d'),
            'guest' => $v->guest_name,
            'company' => (string) $v->guest_company,
            'host' => (string) $v->host?->name,
            'purpose' => $v->purpose,
            'in' => $v->arrived_at->format('H:i'),
            'out' => $v->checked_out_at?->format('H:i') ?? '',
            'duration' => $v->duration_minutes ?? '',
            'status' => $v->status_label,
            'phone' => $v->guest_phone,
            default => '',
        };
    }

    /** Baris untuk tab "Ringkasan" (Excel dan Google Sheets). */
    public function summarySheet(array $f): array
    {
        $s = $this->summary($f);
        $rows = [
            ['Laporan Kunjungan'],
            ['Periode', $f['from']->format('Y-m-d').' s/d '.$f['to']->format('Y-m-d')],
            ['Status', $f['status'] === 'all' ? 'Semua status' : Visit::STATUSES[$f['status']]],
            ['Penanggung jawab', $f['host'] === 'all' ? 'Semua penerima' : (string) Host::find($f['host'])?->name],
            ['Dibuat', now()->format('Y-m-d H:i').' WIB'],
            [],
            ['Total kunjungan', $s['total']],
            ['Kunjungan selesai', $s['done']],
            ['Belum selesai', $s['unfinished']],
            ['Rata-rata durasi (menit)', $s['avg_duration']],
            [],
            ['Tanggal', 'Total', 'Menunggu', 'Berkunjung', 'Selesai'],
        ];
        foreach ($this->daily($f) as $d) {
            $rows[] = [$d['date']->format('Y-m-d'), $d['total'], $d['waiting'], $d['visiting'], $d['done']];
        }

        return $rows;
    }

    /** Indeks baris pada summarySheet() yang berupa judul / header tabel. */
    public const SUMMARY_STYLES = [0 => XlsxWriter::STYLE_TITLE, 11 => XlsxWriter::STYLE_HEADER];

    public function defaultFilename(array $f, string $ext): string
    {
        return 'Kunjungan_'.$f['from']->format('Y-m-d').'_'.$f['to']->format('Y-m-d').'.'.$ext;
    }

    public function sanitizeFilename(?string $name, string $ext, array $f): string
    {
        $name = trim((string) $name);
        $name = preg_replace('/\.(xlsx|csv)$/i', '', $name) ?? '';
        $name = preg_replace('/[^\p{L}\p{N}_\- .]/u', '', $name) ?? '';
        $name = trim(mb_substr($name, 0, 100), ' .');

        return $name === '' ? $this->defaultFilename($f, $ext) : $name.'.'.$ext;
    }

    private function str(mixed $v): string
    {
        return is_string($v) ? trim($v) : '';
    }

    private function date(string $value, Carbon $default): Carbon
    {
        if ($value === '') {
            return $default;
        }
        try {
            $d = Carbon::createFromFormat('!Y-m-d', $value);
        } catch (\Throwable) {
            return $default;
        }

        // Tolak tanggal yang "melompat" (mis. 2026-02-31) alih-alih diam-diam jadi 3 Maret.
        return $d && $d->format('Y-m-d') === $value ? $d : $default;
    }
}
