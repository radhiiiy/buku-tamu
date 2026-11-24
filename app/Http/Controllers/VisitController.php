<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreVisitRequest;
use App\Models\Host;
use App\Models\Setting;
use App\Models\Visit;
use App\Services\VisitService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;
use InvalidArgumentException;

class VisitController extends Controller
{
    public function __construct(private VisitService $visits) {}

    public function index(Request $request): View
    {
        $date = $request->has('date') ? $this->parseDate($this->queryString($request, 'date')) : today();
        $hostParam = $this->queryString($request, 'host');
        $host = ctype_digit($hostParam) ? (int) $hostParam : null;
        $status = $this->queryString($request, 'status');
        $tab = array_key_exists($status, Visit::STATUSES) ? $status : 'all';
        $sort = $this->queryString($request, 'sort') === 'terlama' ? 'asc' : 'desc';
        $term = $this->queryString($request, 'q');

        $base = fn () => Visit::query()
            ->when($date, fn ($q) => $q->onDate($date))
            ->when($host, fn ($q) => $q->where('host_id', $host))
            ->search($term);

        $counts = $base()->selectRaw('status, count(*) as c')->groupBy('status')->pluck('c', 'status');
        $list = $base()->with('host')
            ->when($tab !== 'all', fn ($q) => $q->where('status', $tab))
            ->orderBy('arrived_at', $sort)->orderBy('id', $sort)
            ->paginate((int) config('visitor.per_page'))->withQueryString();

        return view('visits.index', [
            'list' => $list,
            'counts' => $counts,
            'allCount' => $counts->sum(),
            'tab' => $tab,
            'date' => $date,
            'hostId' => $host,
            'hosts' => Host::orderBy('name')->get(),
            'sort' => $sort === 'asc' ? 'terlama' : 'terbaru',
            'term' => $term,
        ]);
    }

    public function create(): View
    {
        return view('visits.create', [
            'hosts' => Host::where('is_active', true)->orderBy('name')->get(),
            'purposes' => config('visitor.purposes'),
        ]);
    }

    public function store(StoreVisitRequest $request): RedirectResponse
    {
        $visit = $this->visits->register($request->validated(), 'manual', $request->user());

        return redirect()->route('visits.show', $visit)->with('success', "Tamu {$visit->guest_name} dicatat dan penanggung jawab diberi tahu.");
    }

    public function show(Visit $visit): View
    {
        return view('visits.show', ['visit' => $visit->load(['host', 'events', 'confirmer'])]);
    }

    public function print(Visit $visit): View
    {
        return view('visits.print', ['visit' => $visit->load('host'), 'office' => Setting::office()]);
    }

    public function checkoutPreview(Visit $visit): JsonResponse
    {
        $now = now();

        return response()->json([
            'can' => $visit->status === 'berkunjung',
            'name' => $visit->guest_name,
            'host' => $visit->host->name,
            'in' => $visit->arrived_at->translatedFormat('d M Y').' · '.$visit->arrived_at->format('H.i'),
            'out' => $now->translatedFormat('d M Y').' · '.$now->format('H.i'),
            'duration' => (int) $visit->arrived_at->diffInMinutes($now, true).' menit',
            'action' => route('visits.checkout', $visit),
        ]);
    }

    public function confirm(Request $request, Visit $visit): RedirectResponse
    {
        return $this->act(fn () => $this->visits->confirm($visit, $request->user()), "Kedatangan {$visit->guest_name} dikonfirmasi.");
    }

    public function reject(Request $request, Visit $visit): RedirectResponse
    {
        return $this->act(fn () => $this->visits->reject($visit, $request->user()), "Kunjungan {$visit->guest_name} ditolak.");
    }

    public function remind(Request $request, Visit $visit): RedirectResponse
    {
        return $this->act(fn () => $this->visits->remind($visit, $request->user()), "Pengingat dikirim ke {$visit->host->name}.");
    }

    public function checkout(Request $request, Visit $visit): RedirectResponse
    {
        return $this->act(fn () => $this->visits->checkOut($visit, $request->user()), "Check-out {$visit->guest_name} tercatat. Kunjungan selesai.");
    }

    public function bulk(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'action' => ['required', 'in:confirm,checkout'],
            'ids' => ['required', 'array', 'min:1', 'max:200'],
            'ids.*' => ['integer'],
        ]);

        $ok = 0;
        $skipped = 0;
        foreach (Visit::with('host')->whereIn('id', $data['ids'])->get() as $visit) {
            try {
                $data['action'] === 'confirm' ? $this->visits->confirm($visit, $request->user()) : $this->visits->checkOut($visit, $request->user());
                $ok++;
            } catch (InvalidArgumentException) {
                $skipped++;
            }
        }

        $msg = "{$ok} kunjungan diproses".($skipped ? ", {$skipped} dilewati karena statusnya tidak sesuai" : '').'.';

        return back()->with($ok ? 'success' : 'error', $msg);
    }

    private function act(callable $fn, string $message): RedirectResponse
    {
        try {
            $fn();
        } catch (InvalidArgumentException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', $message);
    }

    private function parseDate(string $v): ?Carbon
    {
        if ($v === '') {
            return null; // kosong = semua tanggal
        }
        try {
            $d = Carbon::createFromFormat('!Y-m-d', $v);
        } catch (\Throwable) {
            return today();
        }

        // createFromFormat tidak melempar error untuk tanggal tidak valid (mis. 2026-02-31 jadi Maret); tolak itu.
        return $d && $d->format('Y-m-d') === $v ? $d : today();
    }
}
