<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Log;

class CategoryController extends Controller
{
    public function index()
    {
        if (!Schema::hasTable('categories')) {
            return response()->json([], 200);
        }
        $list = DB::table('categories')->orderBy('name')->get(['id', 'name', 'code']);
        return response()->json($list, 200);
    }

    public function store(Request $request)
    {
        $request->validate(['name' => 'required|string|max:255']);
        if (!Schema::hasTable('categories')) {
            return response()->json(['message' => 'Categories table not found'], 422);
        }

        return DB::transaction(function () use ($request) {
            // Lock table by selecting max code for update via a dummy update
            $last = DB::table('categories')->lockForUpdate()->orderByDesc('code')->value('code');
            $next = $this->nextCode($last);

            $id = DB::table('categories')->insertGetId([
                'name' => $request->string('name'),
                'code' => $next,
                'last_item_seq' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $row = DB::table('categories')->where('id', $id)->first(['id', 'name', 'code']);
            return response()->json($row, 201);
        });
    }

    public function update(Request $request, int $id)
    {
        $request->validate(['name' => 'required|string|max:255']);
        if (!Schema::hasTable('categories')) {
            return response()->json(['message' => 'Categories table not found'], 422);
        }
        DB::table('categories')->where('id', $id)->update([
            'name' => $request->string('name'),
            'updated_at' => now(),
        ]);
        $row = DB::table('categories')->where('id', $id)->first(['id', 'name', 'code']);
        return response()->json($row, 200);
    }

    public function destroy(int $id)
    {
        if (!Schema::hasTable('categories')) {
            return response()->json(['message' => 'Categories table not found'], 422);
        }
        DB::table('categories')->where('id', $id)->delete();
        return response()->json(['status' => 'ok'], 200);
    }

    private function nextCode(?string $current): string
    {
        if (!$current) return 'A';
        $s = strtoupper($current);
        $letters = str_split($s);
        $i = count($letters) - 1;
        while ($i >= 0) {
            if ($letters[$i] !== 'Z') {
                $letters[$i] = chr(ord($letters[$i]) + 1);
                break;
            } else {
                $letters[$i] = 'A';
                $i--;
            }
        }
        if ($i < 0) {
            array_unshift($letters, 'A');
        }
        return implode('', $letters);
    }
}

