<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Pengirim notifikasi WhatsApp. Driver dipilih lewat WHATSAPP_DRIVER (log | fonnte).
 * Kegagalan kirim tidak pernah melempar error: hanya dicatat di log dan mengembalikan false.
 */
class WhatsAppService
{
    /** True bila pesan benar-benar dikirim ke WhatsApp (bukan sekadar ditulis ke log). */
    public function enabled(): bool
    {
        return config('whatsapp.driver') === 'fonnte' && filled(config('whatsapp.fonnte.token'));
    }

    /** Ubah 0812-3456-7890 / +62 812 ... / 812... menjadi 6281234567890. Kosong bila tidak valid. */
    public function normalize(?string $phone): string
    {
        $d = preg_replace('/\D/', '', (string) $phone) ?? '';
        $cc = (string) config('whatsapp.country_code', '62');

        if ($d === '') {
            return '';
        }
        if (str_starts_with($d, '0')) {
            $d = $cc.substr($d, 1);
        } elseif (! str_starts_with($d, $cc)) {
            $d = $cc.$d;
        }

        return strlen($d) >= 10 && strlen($d) <= 16 ? $d : '';
    }

    public function send(?string $to, string $message): bool
    {
        $number = $this->normalize($to);
        if ($number === '') {
            return false;
        }

        if (! $this->enabled()) {
            Log::info("[WhatsApp:log] ke {$number}\n{$message}");

            return false;
        }

        try {
            $res = Http::withHeaders(['Authorization' => (string) config('whatsapp.fonnte.token')])
                ->asForm()->timeout(15)
                ->post(config('whatsapp.fonnte.endpoint'), [
                    'target' => $number, 'message' => $message, 'countryCode' => config('whatsapp.country_code'),
                ]);

            if ($res->failed() || $res->json('status') === false) {
                Log::warning('WhatsApp gagal dikirim ke '.$number.': '.($res->json('reason') ?? $res->status()));

                return false;
            }

            return true;
        } catch (Throwable $e) {
            report($e);

            return false;
        }
    }
}
