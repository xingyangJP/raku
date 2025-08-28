<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Throwable;

class ApiController extends Controller
{
    public function getCustomers(Request $request)
    {
        $search = $request->input('search');
        try {
            $response = Http::withOptions([
                'verify' => env('SSL_VERIFY', true),
                'curl' => [
                    // Workaround for old cURL versions (like 7.29.0) lacking TLSv1.2 constants
                    // CURLOPT_SSLVERSION => 0 (CURL_SSLVERSION_DEFAULT) tells cURL to negotiate the version
                    CURLOPT_SSLVERSION => 0,
                ],
            ])->get('https://api.xerographix.co.jp/api/customers', [
                'search' => $search,
            ]);

            if ($response->successful()) {
                return response()->json($response->json());
            }

            \Log::error('External API error (customers): ' . $response->body());
            return response()->json(['message' => 'Failed to fetch customers from external API. Status: ' . $response->status()], $response->status());
        } catch (Throwable $e) {
            \Log::error($e);
            return response()->json(['message' => 'An unexpected error occurred: ' . $e->getMessage()], 500);
        }
    }

    public function getUsers(Request $request)
    {
        $search = $request->input('search');
        try {
            $response = Http::withOptions([
                'verify' => env('SSL_VERIFY', true),
                'curl' => [
                    // Workaround for old cURL versions (like 7.29.0) lacking TLSv1.2 constants
                    // CURLOPT_SSLVERSION => 0 (CURL_SSLVERSION_DEFAULT) tells cURL to negotiate the version
                    CURLOPT_SSLVERSION => 0,
                ],
            ])->get('https://api.xerographix.co.jp/api/users', [
                'search' => $search,
            ]);

            if ($response->successful()) {
                return response()->json($response->json());
            }

            \Log::error('External API error (users): ' . $response->body());
            return response()->json(['message' => 'Failed to fetch users from external API. Status: ' . $response->status()], $response->status());
        } catch (Throwable $e) {
            \Log::error($e);
            return response()->json(['message' => 'An unexpected error occurred: ' . $e->getMessage()], 500);
        }
    }
}