<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Ekspor satu kali ke Google Sheets memakai OAuth milik pengguna (scope: spreadsheets).
 * Memerlukan GOOGLE_CLIENT_ID / GOOGLE_CLIENT_SECRET di .env.
 */
class GoogleSheetsService
{
    private const SHEETS = 'https://sheets.googleapis.com/v4/spreadsheets';

    public function configured(): bool
    {
        return filled(config('services.google.client_id')) && filled(config('services.google.client_secret'));
    }

    public function authUrl(string $state, bool $chooseAccount = false): string
    {
        return 'https://accounts.google.com/o/oauth2/v2/auth?'.http_build_query([
            'client_id' => config('services.google.client_id'),
            'redirect_uri' => config('services.google.redirect'),
            'response_type' => 'code',
            'scope' => 'openid email https://www.googleapis.com/auth/spreadsheets',
            'access_type' => 'offline',
            'prompt' => $chooseAccount ? 'select_account consent' : 'consent',
            'state' => $state,
        ]);
    }

    public function connect(User $user, string $code): void
    {
        $res = Http::asForm()->post('https://oauth2.googleapis.com/token', [
            'code' => $code,
            'client_id' => config('services.google.client_id'),
            'client_secret' => config('services.google.client_secret'),
            'redirect_uri' => config('services.google.redirect'),
            'grant_type' => 'authorization_code',
        ]);
        if ($res->failed() || ! $res->json('access_token')) {
            throw new RuntimeException('Google menolak kode otorisasi. Coba hubungkan ulang.');
        }

        $email = Http::withToken($res->json('access_token'))->get('https://openidconnect.googleapis.com/v1/userinfo')->json('email');

        $user->update([
            'google_email' => $email,
            'google_access_token' => $res->json('access_token'),
            'google_refresh_token' => $res->json('refresh_token') ?: $user->google_refresh_token,
            'google_expires_at' => now()->addSeconds((int) $res->json('expires_in', 3600)),
        ]);
    }

    public function disconnect(User $user): void
    {
        $user->update(['google_email' => null, 'google_access_token' => null, 'google_refresh_token' => null, 'google_expires_at' => null]);
    }

    /**
     * @param  array{mode:string,title:string,tab:string,existing?:?string,header:bool,sheets:array<int,array{title:string,rows:array}>}  $opt
     * @return array{id:string,url:string,title:string}
     */
    public function export(User $user, array $opt): array
    {
        $api = $this->client($user);

        if ($opt['mode'] === 'existing') {
            $id = $this->spreadsheetId((string) ($opt['existing'] ?? ''));
            $meta = $api->get(self::SHEETS.'/'.$id, ['fields' => 'spreadsheetUrl,properties.title,sheets.properties.title']);
            $this->check($meta, 'Spreadsheet tidak ditemukan atau akun ini tidak punya akses edit.');
            $taken = collect($meta->json('sheets', []))->pluck('properties.title')->all();
            $sheets = array_map(function ($s) use (&$taken) {
                $s['title'] = $this->unique($s['title'], $taken);
                $taken[] = $s['title'];

                return $s;
            }, $opt['sheets']);
            $add = $api->post(self::SHEETS.'/'.$id.':batchUpdate', ['requests' => array_map(fn ($s) => ['addSheet' => ['properties' => ['title' => $s['title']]]], $sheets)]);
            $this->check($add, 'Gagal menambahkan tab ke spreadsheet.');
            $url = $meta->json('spreadsheetUrl');
            $title = $meta->json('properties.title');
        } else {
            $create = $api->post(self::SHEETS, [
                'properties' => ['title' => $opt['title'], 'locale' => 'id_ID', 'timeZone' => 'Asia/Jakarta'],
                'sheets' => array_map(fn ($s) => ['properties' => ['title' => $s['title']]], $opt['sheets']),
            ]);
            $this->check($create, 'Gagal membuat spreadsheet baru.');
            $id = $create->json('spreadsheetId');
            $url = $create->json('spreadsheetUrl');
            $title = $opt['title'];
            $sheets = $opt['sheets'];
        }

        foreach ($sheets as $s) {
            $q = "'".str_replace("'", "''", $s['title'])."'";
            foreach (array_chunk($s['rows'], 5000) as $i => $chunk) {
                $write = $api->post(self::SHEETS.'/'.$id.'/values:batchUpdate', [
                    'valueInputOption' => 'RAW', // RAW: sel berawalan "=" tetap berupa teks
                    'data' => [['range' => $q.'!A'.($i * 5000 + 1), 'majorDimension' => 'ROWS', 'values' => $chunk]],
                ]);
                $this->check($write, 'Gagal menulis data ke Google Sheets.');
            }
        }

        return ['id' => $id, 'url' => $url ?: 'https://docs.google.com/spreadsheets/d/'.$id, 'title' => $title];
    }

    public function spreadsheetId(string $input): string
    {
        if (preg_match('#/spreadsheets/d/([a-zA-Z0-9\-_]+)#', $input, $m)) {
            return $m[1];
        }
        if (preg_match('/^[a-zA-Z0-9\-_]{20,}$/', trim($input))) {
            return trim($input);
        }

        throw new RuntimeException('Tautan atau ID spreadsheet tidak valid.');
    }

    private function client(User $user): PendingRequest
    {
        if (! $user->googleConnected()) {
            throw new RuntimeException('Akun Google belum terhubung.');
        }

        if ($user->google_refresh_token && (! $user->google_expires_at || $user->google_expires_at->lte(now()->addMinute()))) {
            $res = Http::asForm()->post('https://oauth2.googleapis.com/token', [
                'client_id' => config('services.google.client_id'),
                'client_secret' => config('services.google.client_secret'),
                'refresh_token' => $user->google_refresh_token,
                'grant_type' => 'refresh_token',
            ]);
            if ($res->failed() || ! $res->json('access_token')) {
                throw new RuntimeException('Sesi Google berakhir. Hubungkan ulang akun Google Anda.');
            }
            $user->update([
                'google_access_token' => $res->json('access_token'),
                'google_expires_at' => now()->addSeconds((int) $res->json('expires_in', 3600)),
            ]);
        }

        return Http::withToken($user->google_access_token)->acceptJson()->timeout(60);
    }

    private function check($response, string $message): void
    {
        if ($response->failed()) {
            $detail = $response->json('error.message');
            throw new RuntimeException($message.($detail ? " ({$detail})" : ''));
        }
    }

    private function unique(string $title, array $taken): string
    {
        $t = mb_substr($title, 0, 90);
        for ($i = 2; in_array($t, $taken, true); $i++) {
            $t = mb_substr($title, 0, 85).' ('.$i.')';
        }

        return $t;
    }
}
