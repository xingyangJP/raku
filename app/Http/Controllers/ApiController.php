<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Throwable;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Http;
use App\Models\Partner;

class ApiController extends Controller
{
    public function getCustomers(Request $request)
    {
        $search = (string) $request->input('search', '');
        try {
            // Prefer local DB. If partners table is missing, return empty list to avoid runtime error.
            if (!Schema::hasTable('partners')) {
                return response()->json([]);
            }

            $query = Partner::query();
            if ($search !== '') {
                $like = '%' . str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $search) . '%';
                $query->where(function ($q) use ($like) {
                    $q->where('name', 'like', $like)
                      ->orWhere('code', 'like', $like)
                      ->orWhere('mf_partner_id', 'like', $like);
                });
            }

            $partners = $query
                ->orderBy('name')
                ->limit(50)
                ->get(['mf_partner_id', 'name', 'payload']);

            // Map to the shape expected by the frontend combobox
            $customers = $partners->map(function ($p) {
                $deptId = null;
                if (is_array($p->payload) && isset($p->payload['department_id'])) {
                    $deptId = $p->payload['department_id'];
                }
                return [
                    'id' => (string) $p->mf_partner_id,
                    'customer_name' => (string) $p->name,
                    // department_id は取得できれば返す（未保存の場合は null）
                    'department_id' => $deptId,
                ];
            })->values();

            return response()->json($customers);
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

    public function getProjects(Request $request)
    {
        $search = trim((string) $request->input('search', ''));

        try {
            $token = (string) (env('XERO_PM_API_TOKEN') ?: env('EXTERNAL_API_TOKEN') ?: '');
            if ($token === '') {
                return response()->json([]);
            }

            $base = rtrim((string) env('XERO_PM_API_BASE', 'https://api.xerographix.co.jp/api'), '/');
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $token,
                'Accept' => 'application/json',
            ])->get($base . '/projects');

            if (!$response->successful()) {
                \Log::warning('Failed to fetch projects from external API.', [
                    'status' => $response->status(),
                    'url' => $base . '/projects',
                ]);
                return response()->json([]);
            }

            $projects = $response->json();
            if (!is_array($projects)) {
                return response()->json([]);
            }

            $normalized = collect($projects)->map(function ($p) {
                return [
                    'id' => (string) ($p['id'] ?? ''),
                    'name' => (string) ($p['name'] ?? ''),
                    'customer_id' => isset($p['customer_id']) ? (string) $p['customer_id'] : null,
                    'customer_name' => (string) ($p['customer']['name'] ?? ''),
                    'is_active' => (bool) ($p['is_active'] ?? true),
                ];
            })->filter(function ($p) {
                return $p['id'] !== '' && $p['name'] !== '';
            });

            if ($search !== '') {
                $needle = mb_strtolower($search);
                $normalized = $normalized->filter(function ($p) use ($needle) {
                    $name = mb_strtolower((string) ($p['name'] ?? ''));
                    $customer = mb_strtolower((string) ($p['customer_name'] ?? ''));
                    return str_contains($name, $needle) || str_contains($customer, $needle);
                });
            }

            return response()->json($normalized->take(50)->values());
        } catch (Throwable $e) {
            \Log::error($e);
            return response()->json(['message' => 'An unexpected error occurred: ' . $e->getMessage()], 500);
        }
    }

    public function getPartnerDepartments(string $partner)
    {
        try {
            if (!Schema::hasTable('partners')) {
                return response()->json([]);
            }
            $p = Partner::where('mf_partner_id', $partner)->first();
            if (!$p) {
                return response()->json([]);
            }
            $payload = $p->payload ?? [];

            // 再帰的に payload から departments 配列を抽出（offices配下も対象）
            $departments = [];
            $walker = function ($node) use (&$walker, &$departments) {
                if (is_array($node)) {
                    // 直接 departments キーがあれば追加
                    if (isset($node['departments']) && is_array($node['departments'])) {
                        foreach ($node['departments'] as $d) { $departments[] = $d; }
                    }
                    // 子要素を探索
                    foreach ($node as $k => $v) {
                        if (is_array($v)) { $walker($v); }
                    }
                }
            };
            if (is_array($payload)) { $walker($payload); }

            // Normalize to id + name (+ code) array
            $result = collect($departments)
                ->map(function ($d) {
                    return [
                        'id' => (string)($d['id'] ?? ''),
                        // Prefer person_dept (担当部署名), fallback to name, finally '本社'
                        'name' => (string)($d['person_dept'] ?? $d['name'] ?? '本社'),
                        'code' => isset($d['code']) ? (string)$d['code'] : (isset($d['id']) ? (string)$d['id'] : null),
                        'person_name' => isset($d['person_name']) ? (string)$d['person_name'] : null,
                        'person_title' => isset($d['person_title']) ? (string)$d['person_title'] : null,
                    ];
                })
                ->filter(fn ($d) => $d['id'] !== '')
                ->unique('id')
                ->values();

            return response()->json($result);
        } catch (Throwable $e) {
            \Log::error($e);
            return response()->json(['message' => 'An unexpected error occurred: ' . $e->getMessage()], 500);
        }
    }
}
