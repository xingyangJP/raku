<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class FakeApiController extends Controller
{
    public function getCustomers(Request $request)
    {
        $q = (string) $request->input('search', '');
        $list = [
            ['id' => 'c1', 'customer_name' => '株式会社サンプル'],
            ['id' => 'c2', 'customer_name' => '有限会社テスト'],
        ];
        if ($q !== '') {
            $list = array_values(array_filter($list, fn($x) => str_contains($x['customer_name'], $q)));
        }
        return response()->json($list);
    }

    public function getUsers(Request $request)
    {
        // Keep IDs simple and deterministic for tests
        $list = [
            ['id' => 'u1', 'name' => '承認者A', 'email' => 'a@example.com'],
            ['id' => 'u2', 'name' => '承認者B', 'email' => 'b@example.com'],
            ['id' => 'u3', 'name' => '承認者C', 'email' => 'c@example.com'],
            ['id' => 'moribe', 'name' => '守部幸洋', 'email' => 'moribe@example.com'],
        ];
        return response()->json($list);
    }
}
