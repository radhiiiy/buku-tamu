<?php

namespace App\Services;

use App\Mail\VisitMail;
use App\Models\AppNotification;
use App\Models\Setting;
use App\Models\User;
use App\Models\Visit;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use InvalidArgumentException;
use Throwable;

class VisitService
{
    public function __construct(private WhatsAppService $whatsapp) {}

    /** Mendaftarkan kunjungan baru (formulir QR atau input manual resepsionis). */
    public function register(array $data, string $source = 'qr', ?User $by = null): Visit
    {
        $auto = ! Setting::bool('require_confirmation', true);

        [$visit, $staffEmails] = DB::transaction(function () use ($data, $source, $by, $auto) {
            $now = now();
            $visit = Visit::create([
                'guest_name' => trim($data['guest_name']),
                'guest_phone' => $this->normalizePhone($data['guest_phone']),
                'guest_company' => filled($data['guest_company'] ?? null) ? trim($data['guest_company']) : null,
                'guest_email' => $data['guest_email'] ?? null,
                'purpose' => $data['purpose'],
                'host_id' => $data['host_id'],
                'notes' => $data['notes'] ?? null,
                'status' => $auto ? 'berkunjung' : 'menunggu',
                'source' => $source,
                'arrived_at' => $now,
                'confirmed_at' => $auto ? $now : null,
                'consented_at' => $now,
                'created_by' => $by?->id,
            ]);

            $visit->load('host');
            $channels = $this->channels($visit->host);

            $events = [
                [
                    'title' => 'Data kunjungan diterima',
                    'description' => $source === 'qr'
                        ? 'Tamu mengisi formulir mandiri melalui QR Code.'
                        : 'Tamu dicatat manual oleh '.($by?->name ?? 'resepsionis').'.',
                ],
                [
                    'title' => 'Penanggung jawab diberi tahu',
                    'description' => $channels
                        ? 'Notifikasi '.implode(' dan ', $channels).' dikirim ke '.$visit->host->name.'.'
                        : $visit->host->name.' belum memiliki email/WhatsApp; hanya notifikasi dasbor yang dikirim.',
                ],
            ];
            if ($auto) {
                $events[] = [
                    'title' => 'Kedatangan dikonfirmasi otomatis',
                    'description' => 'Konfirmasi sebelum masuk dinonaktifkan di pengaturan.',
                ];
            }
            $visit->events()->createMany(array_map(fn ($e) => $e + ['created_at' => $now], $events));

            $emails = $this->notifyStaff(
                $visit,
                $auto ? 'visit_confirmed' : 'visit_new',
                $auto ? 'Tamu baru langsung masuk' : 'Tamu baru menunggu konfirmasi',
                "{$visit->guest_name} ingin bertemu {$visit->host->name}.",
                'Tujuan: '.$visit->purpose,
            );

            return [$visit, $emails];
        });

        // Email dikirim setelah respons terkirim ke tamu, sehingga formulir tidak menunggu SMTP.
        $hostEmail = $visit->host->email;
        $this->afterResponse(function () use ($visit, $staffEmails, $hostEmail) {
            if (filled($hostEmail)) {
                $this->send($hostEmail, $visit, 'Ada tamu untuk Anda', "{$visit->guest_name} ingin bertemu Anda ({$visit->purpose}).");
            }
            $this->whatsapp->send($visit->host->phone, $this->waMessage($visit, $visit->status === 'menunggu' ? 'Ada tamu untuk Anda' : 'Tamu Anda sudah masuk'));
            foreach ($staffEmails as $email) {
                if (strcasecmp($email, (string) $hostEmail) !== 0) {
                    $this->send($email, $visit, 'Tamu baru menunggu konfirmasi', "{$visit->guest_name} ingin bertemu {$visit->host->name}.");
                }
            }
        });

        return $visit;
    }

    public function confirm(Visit $visit, User $by): Visit
    {
        return DB::transaction(function () use ($visit, $by) {
            $this->transition($visit, 'menunggu', ['status' => 'berkunjung', 'confirmed_at' => now(), 'confirmed_by' => $by->id], 'Hanya kunjungan berstatus Menunggu yang dapat dikonfirmasi.');
            $visit->events()->create(['title' => 'Kedatangan dikonfirmasi', 'description' => "Dikonfirmasi oleh {$by->name}.", 'created_at' => now()]);
            $this->notifyStaff($visit, 'visit_confirmed', 'Kedatangan berhasil dikonfirmasi', "{$visit->guest_name} sudah dapat menemui {$visit->host->name}.", "Dikonfirmasi oleh {$by->name}");

            return $visit;
        });
    }

    public function reject(Visit $visit, User $by): Visit
    {
        return DB::transaction(function () use ($visit, $by) {
            $this->transition($visit, 'menunggu', ['status' => 'ditolak', 'confirmed_by' => $by->id], 'Hanya kunjungan berstatus Menunggu yang dapat ditolak.');
            $visit->events()->create(['title' => 'Kunjungan ditolak', 'description' => "Ditolak oleh {$by->name}.", 'created_at' => now()]);
            $this->notifyStaff($visit, 'visit_rejected', 'Kunjungan ditolak', "Kunjungan {$visit->guest_name} ke {$visit->host->name} ditolak.", "Oleh {$by->name}");

            return $visit;
        });
    }

    public function checkOut(Visit $visit, User $by): Visit
    {
        return DB::transaction(function () use ($visit, $by) {
            $this->transition($visit, 'berkunjung', ['status' => 'selesai', 'checked_out_at' => now()], 'Check-out hanya tersedia untuk tamu yang sedang berkunjung.');
            $minutes = $visit->duration_minutes;
            $visit->events()->create(['title' => 'Check-out', 'description' => "Dicatat oleh {$by->name}. Durasi {$minutes} menit.", 'created_at' => now()]);
            $this->notifyStaff($visit, 'visit_done', 'Kunjungan telah selesai', "{$visit->guest_name} sudah melakukan check-out.", "Durasi kunjungan: {$minutes} menit");

            return $visit;
        });
    }

    public function remind(Visit $visit, User $by): void
    {
        $this->ensureStatus($visit, ['menunggu'], 'Pengingat hanya untuk kunjungan yang menunggu konfirmasi.');
        $visit->loadMissing('host');
        $channels = $this->channels($visit->host);
        if (! $channels) {
            throw new InvalidArgumentException("{$visit->host->name} belum memiliki email atau WhatsApp, pengingat tidak dapat dikirim.");
        }

        if (filled($visit->host->email)) {
            $this->send($visit->host->email, $visit, 'Pengingat: tamu menunggu konfirmasi', "{$visit->guest_name} masih menunggu konfirmasi kedatangan Anda.");
        }
        $this->whatsapp->send($visit->host->phone, $this->waMessage($visit, 'Pengingat: tamu menunggu Anda'));
        $visit->events()->create(['title' => 'Pengingat dikirim', 'description' => 'Pengingat '.implode(' dan ', $channels)." ke {$visit->host->name} oleh {$by->name}.", 'created_at' => now()]);
    }

    /** Saluran notifikasi yang benar-benar tersedia untuk penerima ini. */
    private function channels($host): array
    {
        return array_values(array_filter([
            filled($host->email) ? 'email' : null,
            $this->whatsapp->enabled() && $this->whatsapp->normalize($host->phone) !== '' ? 'WhatsApp' : null,
        ]));
    }

    private function waMessage(Visit $visit, string $headline): string
    {
        return implode("\n", array_filter([
            "*{$headline}*",
            '',
            "Nama: {$visit->guest_name}",
            $visit->guest_company ? "Instansi: {$visit->guest_company}" : null,
            "Tujuan: {$visit->purpose}",
            'Datang: '.$visit->arrived_at->format('H.i').' WIB',
            "Kode: {$visit->code}",
            '',
            $visit->status === 'menunggu' ? 'Mohon konfirmasi ke resepsionis.' : 'Tamu sudah dipersilakan masuk.',
            '_'.config('app.name').'_',
        ], fn ($l) => $l !== null));
    }

    public function normalizePhone(string $phone): string
    {
        return preg_replace('/[^\d+]/', '', $phone) ?? '';
    }

    private function ensureStatus(Visit $visit, array $allowed, string $message): void
    {
        if (! in_array($visit->status, $allowed, true)) {
            throw new InvalidArgumentException($message);
        }
    }

    /**
     * Pindah status secara atomik: UPDATE hanya berhasil bila status masih $from.
     * Mencegah klik ganda / dua petugas memproses kunjungan yang sama dua kali.
     */
    private function transition(Visit $visit, string $from, array $attributes, string $message): void
    {
        if (Visit::query()->whereKey($visit->getKey())->where('status', $from)->update($attributes) === 0) {
            throw new InvalidArgumentException($message);
        }
        $visit->refresh()->loadMissing('host');
    }

    /**
     * Buat notifikasi dasbor untuk semua petugas (satu INSERT) dan kembalikan email petugas
     * yang perlu diberi email (hanya untuk tamu baru menunggu konfirmasi).
     *
     * @return array<int, string>
     */
    private function notifyStaff(Visit $visit, string $type, string $title, string $body, ?string $meta): array
    {
        $now = now();
        $rows = [];
        $emails = [];

        foreach (User::query()->get(['id', 'email', 'prefs']) as $user) {
            if ($user->pref('notify_dashboard')) {
                $rows[] = [
                    'user_id' => $user->id, 'type' => $type, 'title' => $title, 'body' => $body,
                    'meta' => $meta, 'visit_id' => $visit->id, 'created_at' => $now, 'updated_at' => $now,
                ];
            }
            if ($type === 'visit_new' && $user->pref('notify_email') && filled($user->email)) {
                $emails[] = $user->email;
            }
        }
        if ($rows) {
            AppNotification::insert($rows);
        }

        return $emails;
    }

    private function afterResponse(callable $fn): void
    {
        app()->terminating($fn);
    }

    private function send(string $to, Visit $visit, string $headline, string $line): void
    {
        try {
            Mail::to($to)->send(new VisitMail($visit, $headline, $line));
        } catch (Throwable $e) {
            report($e); // kegagalan email tidak boleh membatalkan pencatatan kunjungan
        }
    }
}
