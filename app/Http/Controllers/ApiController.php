<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Http\Client\ConnectionException;

class ApiController extends Controller
{
    public function getCustomers(Request $request)
    {
        $search = $request->input('search');
        try {
            $response = Http::withOptions([
                'verify' => env('SSL_VERIFY', true),
            ])->get('https://api.xerographix.co.jp/api/customers', [
                'search' => $search,
            ]);

            if ($response->successful()) {
                return response()->json($response->json());
            }

            return response()->json(['message' => 'Failed to fetch customers from external API.'], $response->status());
        } catch (ConnectionException $e) {
            return response()->json(['message' => 'Could not connect to external API.'], 500);
        }
    }

    public function getUsers(Request $request)
    {
        $search = $request->input('search');
        try {
            $response = Http::withOptions([
                'verify' => env('SSL_VERIFY', true),
            ])->get('https://api.xerographix.co.jp/api/users', [
                'search' => $search,
            ]);

            if ($response->successful()) {
                return response()->json($response->json());
            }

            return response()->json(['message' => 'Failed to fetch users from external API.'], $response->status());
        } catch (ConnectionException $e) {
            return response()->json(['message' => 'Could not connect to external API.'], 500);
        }
    }
}