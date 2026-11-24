<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

abstract class Controller
{
    /** Ambil query string sebagai teks; nilai bukan string (mis. ?q[]=x) dianggap kosong, bukan error 500. */
    protected function queryString(Request $request, string $key): string
    {
        $v = $request->query($key);

        return is_string($v) ? trim($v) : '';
    }
}
