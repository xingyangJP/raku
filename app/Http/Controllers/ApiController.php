<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Throwable;

class ApiController extends Controller
{
    public function getCustomers(Request $request)
    {
        $search = $request->input('search');
        try {
            $url = 'https://api.xerographix.co.jp/api/customers?search=' . urlencode($search);

            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, env('SSL_VERIFY', true));
            // Workaround for old cURL versions
            curl_setopt($ch, CURLOPT_SSLVERSION, 0); // 0 is CURL_SSLVERSION_DEFAULT

            $response = curl_exec($ch);
            $httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $error = curl_error($ch);
            curl_close($ch);

            if ($httpcode >= 200 && $httpcode < 300) {
                return response($response)->header('Content-Type', 'application/json');
            }

            \Log::error('External API error (customers): ' . $response);
            return response()->json(['message' => 'Failed to fetch customers from external API. Status: ' . $httpcode . ' Error: ' . $error], $httpcode);
        } catch (Throwable $e) {
            \Log::error($e);
            return response()->json(['message' => 'An unexpected error occurred: ' . $e->getMessage()], 500);
        }
    }

    public function getUsers(Request $request)
    {
        $search = $request->input('search');
        try {
            $url = 'https://api.xerographix.co.jp/api/users?search=' . urlencode($search);

            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, env('SSL_VERIFY', true));
            // Workaround for old cURL versions
            curl_setopt($ch, CURLOPT_SSLVERSION, 0); // 0 is CURL_SSLVERSION_DEFAULT

            $response = curl_exec($ch);
            $httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $error = curl_error($ch);
            curl_close($ch);

            if ($httpcode >= 200 && $httpcode < 300) {
                return response($response)->header('Content-Type', 'application/json');
            }

            \Log::error('External API error (users): ' . $response);
            return response()->json(['message' => 'Failed to fetch users from external API. Status: ' . $httpcode . ' Error: ' . $error], $httpcode);
        } catch (Throwable $e) {
            \Log::error($e);
            return response()->json(['message' => 'An unexpected error occurred: ' . $e->getMessage()], 500);
        }
    }
}
