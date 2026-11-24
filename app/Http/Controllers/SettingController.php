<?php

namespace App\Http\Controllers;

use App\Models\Host;
use App\Models\Setting;
use App\Models\User;
use App\Services\GoogleSheetsService;
use App\Services\WhatsAppService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class SettingController extends Controller
{
    public function show(Request $request, GoogleSheetsService $google, string $section = 'profil'): View
    {
        return view('settings.index', [
            'section' => $section,
            'office' => [
                'name' => Setting::get('office_name'), 'location' => Setting::get('office_location'),
                'address' => Setting::get('office_address'), 'email' => Setting::get('reception_email'),
            ],
            'require_confirmation' => Setting::bool('require_confirmation', true),
            'checkout_reminder' => Setting::bool('checkout_reminder', true),
            'phone_export_enabled' => Setting::bool('phone_export_enabled', false),
            'timezone' => config('app.timezone'),
            'members' => $section === 'tim' ? User::orderBy('name')->get() : collect(),
            'waEnabled' => app(WhatsAppService::class)->enabled(),
            'hosts' => $section === 'penerima' ? Host::withCount('visits')->orderBy('name')->get() : collect(),
            'prefs' => array_merge(User::DEFAULT_PREFS, $request->user()->prefs ?? []),
            'googleConfigured' => $google->configured(),
        ]);
    }

    public function updateOffice(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'office_name' => ['required', 'string', 'max:120'],
            'office_location' => ['nullable', 'string', 'max:120'],
            'office_address' => ['nullable', 'string', 'max:255'],
            'reception_email' => ['required', 'email', 'max:150'],
        ], ['office_name.required' => 'Nama kantor wajib diisi.', 'reception_email.required' => 'Email resepsionis wajib diisi.', 'reception_email.email' => 'Format email tidak valid.']);

        foreach ($data as $k => $v) {
            Setting::put($k, $v ?? '');
        }
        Setting::put('require_confirmation', $request->boolean('require_confirmation'));
        Setting::put('checkout_reminder', $request->boolean('checkout_reminder'));

        return redirect()->route('settings.show', 'profil')->with('success', 'Pengaturan disimpan.');
    }

    public function updatePrivacy(Request $request): RedirectResponse
    {
        abort_unless($request->user()->isAdmin(), 403);
        Setting::put('phone_export_enabled', $request->boolean('phone_export_enabled'));

        return redirect()->route('settings.show', 'privasi')->with('success', 'Pengaturan privasi disimpan.');
    }

    public function storeMember(Request $request): RedirectResponse
    {
        abort_unless($request->user()->isAdmin(), 403);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email', 'max:150', 'unique:users,email'],
            'role' => ['required', 'in:resepsionis,admin'],
            'password' => ['required', 'string', 'min:8'],
        ]);
        User::create($data);

        return redirect()->route('settings.show', 'tim')->with('success', 'Anggota tim ditambahkan.');
    }

    public function togglePhonePermission(Request $request, User $user): RedirectResponse
    {
        abort_unless($request->user()->isAdmin(), 403);
        $user->update(['can_export_phone' => ! $user->can_export_phone]);

        return redirect()->route('settings.show', 'tim')->with('success', 'Izin ekspor nomor WhatsApp untuk '.$user->name.($user->can_export_phone ? ' diaktifkan.' : ' dinonaktifkan.'));
    }

    // ── Penerima notifikasi (orang kantor yang dituju tamu) ─────────────────
    public function storeHost(Request $request): RedirectResponse
    {
        abort_unless($request->user()->isAdmin(), 403);
        $data = $this->hostData($request, 'hostNew');
        Host::create($data + ['is_active' => true]);

        return redirect()->route('settings.show', 'penerima')->with('success', "Penerima {$data['name']} ditambahkan.");
    }

    public function updateHost(Request $request, Host $host): RedirectResponse
    {
        abort_unless($request->user()->isAdmin(), 403);
        $host->update($this->hostData($request, 'host'.$host->id, $host));

        return redirect()->route('settings.show', 'penerima')->with('success', "Data {$host->name} diperbarui.");
    }

    public function toggleHost(Request $request, Host $host): RedirectResponse
    {
        abort_unless($request->user()->isAdmin(), 403);
        $host->update(['is_active' => ! $host->is_active]);

        return redirect()->route('settings.show', 'penerima')->with('success', $host->name.($host->is_active ? ' diaktifkan (muncul di formulir tamu).' : ' dinonaktifkan (tidak muncul di formulir tamu).'));
    }

    public function destroyHost(Request $request, Host $host): RedirectResponse
    {
        abort_unless($request->user()->isAdmin(), 403);
        if ($host->visits()->exists()) {
            return redirect()->route('settings.show', 'penerima')->with('error', "{$host->name} sudah punya riwayat kunjungan, jadi tidak bisa dihapus. Nonaktifkan saja.");
        }
        $host->delete();

        return redirect()->route('settings.show', 'penerima')->with('success', "{$host->name} dihapus.");
    }

    private function hostData(Request $request, string $bag, ?Host $host = null): array
    {
        $data = $request->validateWithBag($bag, [
            'name' => ['required', 'string', 'max:100'],
            'team' => ['nullable', 'string', 'max:100'],
            'email' => ['nullable', 'email', 'max:150', Rule::unique('hosts', 'email')->ignore($host?->id)],
            'phone' => ['nullable', 'string', 'regex:/^\+?[0-9][0-9\s\-]{7,17}$/'],
        ], ['email.unique' => 'Email ini sudah dipakai penerima lain.', 'phone.regex' => 'Nomor WhatsApp tidak valid (contoh: 0812 3456 7890).']);
        $data['phone'] = app(WhatsAppService::class)->normalize($data['phone'] ?? null) ?: null;

        return $data;
    }
}
