<?php

namespace App\Http\Controllers;

use App\Models\Setting;
use App\Models\Visit;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(Request $request): View
    {
        $range = in_array($request->integer('range'), [7, 14, 30], true) ? $request->integer('range') : 7;
        $today = today();
        $q = $this->queryString($request, 'q');
        $statusFilter = $this->queryString($request, 'status');

        // Statistik hari ini + kemarin: satu query dikelompokkan per tanggal & status.
        $byStatus = Visit::query()->onDate($today)->selectRaw('status, count(*) as c')->groupBy('status')->pluck('c', 'status');
        $total = (int) $byStatus->sum();
        $yesterday = Visit::query()->onDate($today->copy()->subDay())->count();

        // Tren: dihitung di database (tanpa memuat ribuan baris ke PHP).
        $perDay = Visit::query()
            ->whereBetween('arrived_at', [$today->copy()->subDays($range - 1)->startOfDay(), $today->copy()->endOfDay()])
            ->selectRaw('DATE(arrived_at) as d, count(*) as c')->groupBy('d')->pluck('c', 'd');
        $trend = [];
        for ($i = $range - 1; $i >= 0; $i--) {
            $d = $today->copy()->subDays($i);
            $trend[] = ['date' => $d, 'count' => (int) ($perDay[$d->format('Y-m-d')] ?? 0)];
        }
        $max = max(array_column($trend, 'count') ?: [0]);
        $step = collect([1, 2, 5, 10, 25, 50, 100, 250, 500, 1000, 5000])->first(fn ($s) => $s >= ceil(max(1, $max) / 4)) ?? 5000;

        $recent = Visit::query()->with('host')->onDate($today)
            ->search($q)
            ->when(array_key_exists($statusFilter, Visit::STATUSES), fn ($query) => $query->where('status', $statusFilter))
            ->orderByDesc('arrived_at')->orderByDesc('id')->limit(5)->get();

        $overdue = Setting::bool('checkout_reminder', true)
            ? Visit::where('status', 'berkunjung')->where('arrived_at', '<=', now()->subHours((int) config('visitor.checkout_reminder_hours')))->count()
            : 0;

        $pending = Visit::with('host')->where('status', 'menunggu')->orderByDesc('arrived_at')->limit(3)->get();
        $pendingCount = $pending->count() < 3 ? $pending->count() : Visit::where('status', 'menunggu')->count();

        return view('dashboard', [
            'today' => $today,
            'total' => $total,
            'diff' => $total - $yesterday,
            'visiting' => (int) ($byStatus['berkunjung'] ?? 0),
            'waiting' => (int) ($byStatus['menunggu'] ?? 0),
            'done' => (int) ($byStatus['selesai'] ?? 0),
            'trend' => $trend,
            'range' => $range,
            'axisMax' => $step * 4,
            'axisStep' => $step,
            'trendTotal' => array_sum(array_column($trend, 'count')),
            'pending' => $pending,
            'pendingCount' => $pendingCount,
            'recent' => $recent,
            'q' => $q,
            'statusFilter' => $statusFilter,
            'overdue' => $overdue,
        ]);
    }
}
