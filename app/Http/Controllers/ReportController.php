<?php

namespace App\Http\Controllers;

use App\Models\Host;
use App\Models\ReportExport;
use App\Services\GoogleSheetsService;
use App\Services\ReportService;
use App\Services\XlsxWriter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\View\View;
use RuntimeException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportController extends Controller
{
    public function __construct(private ReportService $reports, private GoogleSheetsService $google) {}

    public function index(Request $request): View
    {
        $f = $this->reports->filters($request->query());

        return view('reports.index', [
            'f' => $f,
            'q' => $this->query($f),
            's' => $this->reports->summary($f),
            'daily' => $this->reports->daily($f),
            'hosts' => Host::orderBy('name')->get(),
            'last' => ReportExport::with('user')->latest('id')->first(),
        ]);
    }

    public function excel(Request $request): View
    {
        $f = $this->reports->filters($request->query());
        $user = $request->user();
        $cols = $this->reports->selectColumns($request->query('cols'), $user);
        $export = session('export_done') ? ReportExport::find(session('export_done')) : null;

        return view('reports.excel', [
            'f' => $f,
            'q' => $this->query($f),
            'cols' => $cols,
            'allowed' => $this->reports->allowedColumns($user),
            'columns' => ReportService::COLUMNS,
            'total' => $this->reports->query($f)->count(),
            'previewRows' => $this->reports->rows($f, array_slice($cols, 0, 6), 5),
            'previewHeaders' => $this->reports->headers(array_slice($cols, 0, 6)),
            'filename' => $this->reports->defaultFilename($f, 'xlsx'),
            'hostName' => $f['host'] === 'all' ? 'Semua penerima' : Host::find($f['host'])?->name,
            'phoneAllowed' => $this->reports->canExportPhone($user),
            'done' => $export,
        ]);
    }

    /** Potongan HTML pratinjau (dipakai JS saat kolom diganti). */
    public function preview(Request $request): View
    {
        $f = $this->reports->filters($request->query());
        $raw = $request->query('cols');
        $cols = $this->reports->selectColumns(is_array($raw) ? $raw : [], $request->user());

        return view('reports.partials.preview', [
            'total' => $this->reports->query($f)->count(),
            'cols' => $cols,
            'previewRows' => $this->reports->rows($f, array_slice($cols, 0, 6), 5),
            'previewHeaders' => $this->reports->headers(array_slice($cols, 0, 6)),
        ]);
    }

    public function excelStore(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'format' => ['required', 'in:xlsx,csv'],
            'filename' => ['nullable', 'string', 'max:120'],
            'cols' => ['required', 'array', 'min:1'],
            'cols.*' => ['string'],
        ], ['cols.required' => 'Pilih minimal satu kolom untuk diekspor.', 'cols.min' => 'Pilih minimal satu kolom untuk diekspor.']);

        $f = $this->reports->filters($request->all());
        $cols = $this->reports->selectColumns($data['cols'], $request->user());
        if ($cols === []) {
            return back()->withInput()->with('error', 'Kolom yang dipilih tidak diizinkan untuk Anda.');
        }

        $this->pruneOldExports();
        $name = $this->reports->sanitizeFilename($data['filename'] ?? null, $data['format'], $f);
        $rows = $this->reports->rows($f, $cols);
        $path = 'exports/'.Str::uuid().'.'.$data['format'];
        $full = Storage::disk('local')->path($path);
        @mkdir(dirname($full), 0775, true);

        try {
            if ($data['format'] === 'xlsx') {
                $dataRows = array_merge([$this->reports->headers($cols)], $rows);
                (new XlsxWriter)
                    ->addSheet('Ringkasan', $this->reports->summarySheet($f), ReportService::SUMMARY_STYLES, [30, 22, 12, 12, 12])
                    ->addSheet('Data kunjungan', $dataRows, [0 => XlsxWriter::STYLE_HEADER], array_map(fn ($k) => in_array($k, ['guest', 'company', 'host', 'purpose']) ? 26 : 16, $cols))
                    ->save($full);
            } else {
                $this->writeCsv($full, $this->reports->headers($cols), $rows);
            }
        } catch (RuntimeException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        $export = ReportExport::create([
            'user_id' => $request->user()->id, 'kind' => $data['format'], 'filename' => $name, 'path' => $path,
            'rows_count' => count($rows), 'columns_count' => count($cols),
            'period_from' => $f['from'], 'period_to' => $f['to'],
            'filters' => ['status' => $f['status'], 'host' => $f['host'], 'cols' => $cols],
        ]);

        return redirect()->route('reports.excel', $this->carry($f) + ['cols' => $cols])->with('export_done', $export->id);
    }

    public function download(Request $request, ReportExport $export): StreamedResponse
    {
        abort_unless($export->path && ($export->user_id === $request->user()->id || $request->user()->isAdmin()), 403);
        abort_unless(Storage::disk('local')->exists($export->path), 404, 'Berkas ekspor sudah tidak tersedia.');

        return Storage::disk('local')->download($export->path, $export->filename, [
            'Content-Type' => $export->kind === 'csv' ? 'text/csv; charset=UTF-8' : 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    public function sheets(Request $request): View
    {
        $f = $this->reports->filters($request->query());
        $user = $request->user();
        $cols = $this->reports->selectColumns(null, $user);

        return view('reports.sheets', [
            'f' => $f,
            'q' => $this->query($f),
            'cols' => $cols,
            'total' => $this->reports->query($f)->count(),
            'configured' => $this->google->configured(),
            'phoneAllowed' => $this->reports->canExportPhone($user),
            'title' => 'Laporan Kunjungan · '.$f['from']->translatedFormat('j').'–'.$f['to']->translatedFormat('j M Y'),
            'done' => session('sheets_done'),
        ]);
    }

    public function sheetsStore(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'mode' => ['required', 'in:new,existing'],
            'title' => ['required_if:mode,new', 'nullable', 'string', 'max:120'],
            'existing' => ['required_if:mode,existing', 'nullable', 'string', 'max:300'],
            'tab' => ['required', 'string', 'max:60'],
        ], ['title.required_if' => 'Nama spreadsheet wajib diisi.', 'existing.required_if' => 'Tempel tautan atau ID spreadsheet yang sudah ada.', 'tab.required' => 'Nama tab data wajib diisi.']);

        $user = $request->user();
        if (! $this->google->configured()) {
            return back()->withInput()->with('error', 'Integrasi Google belum dikonfigurasi (GOOGLE_CLIENT_ID / GOOGLE_CLIENT_SECRET di .env).');
        }
        if (! $user->googleConnected()) {
            return back()->withInput()->with('error', 'Hubungkan akun Google Anda terlebih dahulu.');
        }

        $f = $this->reports->filters($request->all());
        $cols = $this->reports->selectColumns($request->boolean('with_phone') ? array_keys(ReportService::COLUMNS) : array_diff(array_keys(ReportService::COLUMNS), ['phone']), $user);
        $rows = $this->reports->rows($f, $cols);
        $dataRows = $request->boolean('header', true) ? array_merge([$this->reports->headers($cols)], $rows) : $rows;

        $sheets = [];
        if ($request->boolean('summary')) {
            $sheets[] = ['title' => 'Ringkasan harian', 'rows' => $this->reports->summarySheet($f)];
        }
        $sheets[] = ['title' => $data['tab'], 'rows' => $dataRows];

        try {
            $result = $this->google->export($user, [
                'mode' => $data['mode'], 'title' => $data['title'] ?? '', 'tab' => $data['tab'],
                'existing' => $data['existing'] ?? null, 'header' => $request->boolean('header', true), 'sheets' => $sheets,
            ]);
        } catch (RuntimeException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        $export = ReportExport::create([
            'user_id' => $user->id, 'kind' => 'sheets', 'filename' => $result['title'], 'url' => $result['url'],
            'rows_count' => count($rows), 'columns_count' => count($cols),
            'period_from' => $f['from'], 'period_to' => $f['to'], 'filters' => ['status' => $f['status'], 'host' => $f['host'], 'cols' => $cols],
        ]);

        return redirect()->route('reports.sheets', $this->carry($f))->with('sheets_done', [
            'title' => $result['title'], 'url' => $result['url'], 'rows' => $export->rows_count, 'cols' => $export->columns_count,
            'tabs' => collect($sheets)->pluck('title')->implode(' + '), 'phone' => in_array('phone', $cols, true),
            'period' => $f['from']->translatedFormat('j').'–'.$f['to']->translatedFormat('j M Y'),
        ]);
    }

    public function googleConnect(Request $request): RedirectResponse
    {
        if (! $this->google->configured()) {
            return back()->with('error', 'Integrasi Google belum dikonfigurasi (GOOGLE_CLIENT_ID / GOOGLE_CLIENT_SECRET di .env).');
        }
        $request->session()->put('google_state', $state = Str::random(40));
        $request->session()->put('google_return', url()->previous());

        return redirect()->away($this->google->authUrl($state, $request->boolean('ganti')));
    }

    public function googleCallback(Request $request): RedirectResponse
    {
        $back = $request->session()->pull('google_return', route('reports.sheets'));
        $state = $request->session()->pull('google_state');

        if ($request->query('error') || ! $state || ! hash_equals((string) $state, (string) $request->query('state'))) {
            return redirect($back)->with('error', 'Penghubungan akun Google dibatalkan atau tidak valid.');
        }

        try {
            $this->google->connect($request->user(), (string) $request->query('code'));
        } catch (RuntimeException $e) {
            return redirect($back)->with('error', $e->getMessage());
        }

        return redirect($back)->with('success', 'Akun Google berhasil terhubung.');
    }

    public function googleDisconnect(Request $request): RedirectResponse
    {
        $this->google->disconnect($request->user());

        return back()->with('success', 'Akun Google diputuskan.');
    }

    /** Hapus berkas ekspor lebih dari 7 hari agar storage tidak menumpuk (riwayat tetap tersimpan). */
    private function pruneOldExports(): void
    {
        $old = ReportExport::query()->whereNotNull('path')->where('created_at', '<', now()->subDays(7))->get();
        foreach ($old as $e) {
            Storage::disk('local')->delete($e->path);
            $e->update(['path' => null]);
        }
    }

    private function carry(array $f): array
    {
        return ['from' => $f['from']->format('Y-m-d'), 'to' => $f['to']->format('Y-m-d'), 'status' => $f['status'], 'host' => $f['host']];
    }

    private function query(array $f): array
    {
        return $this->carry($f);
    }

    private function writeCsv(string $path, array $headers, array $rows): void
    {
        $h = fopen($path, 'w');
        fwrite($h, "\xEF\xBB\xBF"); // BOM agar Excel membaca UTF-8
        $safe = fn ($v) => is_string($v) && $v !== '' && in_array($v[0], ['=', '+', '-', '@'], true) ? "'".$v : $v; // cegah CSV injection
        fputcsv($h, $headers, ',', '"', '');
        foreach ($rows as $r) {
            fputcsv($h, array_map($safe, $r), ',', '"', '');
        }
        fclose($h);
    }
}
