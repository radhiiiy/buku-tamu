<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreVisitRequest;
use App\Models\Host;
use App\Models\Visit;
use App\Services\VisitService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;
use Illuminate\View\View;

class PublicVisitController extends Controller
{
    public function welcome(): View
    {
        return view('public.welcome');
    }

    public function language(Request $request, string $locale): RedirectResponse
    {
        $request->session()->put('locale', $locale);

        return back();
    }

    public function form(): View
    {
        return view('public.form', [
            'hosts' => Host::where('is_active', true)->orderBy('name')->get(),
            'purposes' => config('visitor.purposes'),
        ]);
    }

    public function store(StoreVisitRequest $request, VisitService $service): RedirectResponse
    {
        $visit = $service->register($request->validated(), 'qr');

        return redirect(URL::temporarySignedRoute('public.done', now()->addHours(12), ['visit' => $visit->id]));
    }

    public function done(Visit $visit): View
    {
        return view('public.done', ['visit' => $visit->load('host')]);
    }
}
