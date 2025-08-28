<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class ApiController extends Controller
{
    public function getCustomers(Request $request)
    {
        $search = $request->input('search');
        $response = Http::get('https://api.xerographix.co.jp/api/customers', [
            'search' => $search,
        ]);
        return response()->json($response->json());
    }

    public function getUsers(Request $request)
    {
        $search = $request->input('search');
        $response = Http::get('https://api.xerographix.co.jp/api/users', [
            'search' => $search,
        ]);
        return response()->json($response->json());
    }
}
