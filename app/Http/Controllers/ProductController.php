<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Http\Requests\ProductRequest;
use App\Services\MoneyForwardApiService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Inertia\Inertia;
use Carbon\Carbon;

class ProductController extends Controller
{
    public function index(Request $request)
    {
        $query = Product::query();

        if ($request->filled('search_name')) {
            $query->where('name', 'like', '%' . $request->input('search_name') . '%');
        }

        if ($request->filled('search_sku')) {
            $query->where('sku', 'like', '%' . $request->input('search_sku') . '%');
        }

        if ($request->filled('search_tax_category') && $request->input('search_tax_category') !== 'all') {
            $query->where('tax_category', $request->input('search_tax_category'));
        }

        if ($request->filled('search_category_id') && $request->input('search_category_id') !== 'all') {
            $query->where('category_id', (int) $request->input('search_category_id'));
        }

        $products = $query->orderBy('updated_at', 'desc')->paginate(10)->withQueryString();

        // Provide categories list for filters/UI, if table exists
        $categories = [];
        if (Schema::hasTable('categories')) {
            $categories = DB::table('categories')->orderBy('name')->get(['id', 'name', 'code']);
        }

        return Inertia::render('Products/Index', [
            'products' => $products,
            'categories' => $categories,
            'filters' => $request->only(['search_name', 'search_sku', 'search_tax_category', 'search_category_id']),
        ]);
    }

    public function create()
    {
        $categories = [];
        if (Schema::hasTable('categories')) {
            $categories = DB::table('categories')->orderBy('name')->get(['id', 'name', 'code']);
        }
        return Inertia::render('Products/Create', [
            'categories' => $categories,
        ]);
    }

    public function store(ProductRequest $request)
    {
        $data = $request->validated();

        // Server-side SKU auto-generation using categories.last_item_seq
        $product = DB::transaction(function () use ($data) {
            $category = DB::table('categories')
                ->where('id', $data['category_id'])
                ->lockForUpdate()
                ->first(['id', 'code', 'last_item_seq']);

            if (!$category) {
                abort(422, 'Invalid category selected.');
            }

            $nextSeq = ((int) $category->last_item_seq) + 1;
            $sku = sprintf('%s-%03d', $category->code, $nextSeq);

            // Create product with generated SKU and seq
            $product = Product::create([
                'sku' => $sku,
                'seq' => $nextSeq,
                'category_id' => $category->id,
                'name' => $data['name'],
                'unit' => $data['unit'] ?? '式',
                'price' => $data['price'] ?? 0,
                'quantity' => $data['quantity'] ?? null,
                'cost' => $data['cost'] ?? 0,
                'tax_category' => $data['tax_category'] ?? 'ten_percent',
                'is_deduct_withholding_tax' => $data['is_deduct_withholding_tax'] ?? null,
                'is_active' => $data['is_active'] ?? true,
                'description' => $data['description'] ?? null,
                'attributes' => $data['attributes'] ?? null,
            ]);

            // Update last_item_seq after successful insert
            DB::table('categories')->where('id', $category->id)->update([
                'last_item_seq' => $nextSeq,
                'updated_at' => now(),
            ]);

            return $product;
        });

        return redirect()->route('products.index')->with('success', '商品を作成しました。コードは自動採番されました。');
    }

    public function edit(Product $product)
    {
        $categories = [];
        if (Schema::hasTable('categories')) {
            $categories = DB::table('categories')->orderBy('name')->get(['id', 'name', 'code']);
        }
        return Inertia::render('Products/Edit', [
            'product' => $product,
            'categories' => $categories,
        ]);
    }

    public function update(ProductRequest $request, Product $product)
    {
        $data = $request->validated();

        // If category changes, re-number in the new category
        $newCategoryId = (int) $data['category_id'];
        if ($product->category_id !== $newCategoryId) {
            DB::transaction(function () use ($data, $product, $newCategoryId) {
                $category = DB::table('categories')
                    ->where('id', $newCategoryId)
                    ->lockForUpdate()
                    ->first(['id', 'code', 'last_item_seq']);

                if (!$category) {
                    abort(422, 'Invalid category selected.');
                }

                $nextSeq = ((int) $category->last_item_seq) + 1;
                $sku = sprintf('%s-%03d', $category->code, $nextSeq);

                $product->fill([
                    'sku' => $sku,
                    'seq' => $nextSeq,
                    'category_id' => $category->id,
                ]);

                $product->fill([
                    'name' => $data['name'],
                    'unit' => $data['unit'] ?? '式',
                    'price' => $data['price'] ?? 0,
                    'quantity' => $data['quantity'] ?? null,
                    'cost' => $data['cost'] ?? 0,
                    'tax_category' => $data['tax_category'] ?? 'ten_percent',
                    'is_deduct_withholding_tax' => $data['is_deduct_withholding_tax'] ?? null,
                    'is_active' => $data['is_active'] ?? true,
                    'description' => $data['description'] ?? null,
                    'attributes' => $data['attributes'] ?? null,
                ]);

                $product->save();

                DB::table('categories')->where('id', $category->id)->update([
                    'last_item_seq' => $nextSeq,
                    'updated_at' => now(),
                ]);
            });
        } else {
            // No category change; just update other fields (SKU stays the same)
            $product->update([
                'name' => $data['name'],
                'unit' => $data['unit'] ?? '式',
                'price' => $data['price'] ?? 0,
                'quantity' => $data['quantity'] ?? null,
                'cost' => $data['cost'] ?? 0,
                'tax_category' => $data['tax_category'] ?? 'ten_percent',
                'is_deduct_withholding_tax' => $data['is_deduct_withholding_tax'] ?? null,
                'is_active' => $data['is_active'] ?? true,
                'description' => $data['description'] ?? null,
                'attributes' => $data['attributes'] ?? null,
            ]);
        }

        return redirect()->route('products.index')->with('success', '商品を更新しました。必要に応じてコードを再採番しました。');
    }

    public function destroy(Product $product)
    {
        $product->delete();
        return redirect()->route('products.index')->with('success', 'Product deleted successfully.');
    }

    // --- Money Forward Sync Logic ---

    public function syncAllFromMf(Request $request, MoneyForwardApiService $apiService)
    {
        if ($token = $apiService->getValidAccessToken(null, 'mfc/invoice/data.read')) {
            // Token is valid, sync directly
            return $this->doSyncAll($token, $apiService);
        } else {
            // No valid token, start OAuth flow
            $request->session()->put('mf_redirect_action', 'sync_all');
            return redirect()->route('products.auth.start');
        }
    }

    public function syncOneToMf(Product $product, Request $request, MoneyForwardApiService $apiService)
    {
        if ($token = $apiService->getValidAccessToken(null, 'mfc/invoice/data.write')) {
            // Token is valid, sync directly
            return $this->doSyncOne($product, $token, $apiService);
        } else {
            // No valid token, start OAuth flow
            $request->session()->put('mf_redirect_action', 'sync_one');
            $request->session()->put('mf_product_sync_id', $product->id);
            return redirect()->route('products.auth.start');
        }
    }

    public function redirectToAuth(Request $request)
    {
        $authUrl = config('services.money_forward.authorization_url') . '?' . http_build_query([
            'response_type' => 'code',
            'client_id' => config('services.money_forward.client_id'),
            'redirect_uri' => route('products.auth.callback'),
            'scope' => 'mfc/invoice/data.read mfc/invoice/data.write',
        ]);
        return Inertia::location($authUrl);
    }

    public function handleCallback(Request $request, MoneyForwardApiService $apiService)
    {
        if (!$request->has('code')) {
            return redirect()->route('products.index')->with('error', 'Authorization failed.');
        }

        $tokenData = $apiService->getAccessTokenFromCode($request->code, route('products.auth.callback'));
        if (!$tokenData) {
            return redirect()->route('products.index')->with('error', 'Failed to get access token.');
        }

        $apiService->storeToken($tokenData, Auth::id());
        $token = $tokenData['access_token'];

        // Perform action based on what was stored in session
        $action = $request->session()->get('mf_redirect_action');

        if ($action === 'sync_all') {
            return $this->doSyncAll($token, $apiService);
        } elseif ($action === 'sync_one') {
            $productId = $request->session()->get('mf_product_sync_id');
            $product = Product::find($productId);
            if (!$product) {
                return redirect()->route('products.index')->with('error', 'Product to sync not found.');
            }
            return $this->doSyncOne($product, $token, $apiService);
        }

        return redirect()->route('products.index')->with('error', 'Unknown sync action.');
    }

    private function doSyncAll(string $token, MoneyForwardApiService $apiService)
    {
        $mfItems = $apiService->getItems($token);
        if (is_null($mfItems)) { // getItems returns null on failure
            return redirect()->route('products.index')->with('error', 'Failed to fetch items from Money Forward.');
        }

        $syncedCount = 0;
        foreach ($mfItems as $mfItem) {
            Product::updateOrCreate(
                ['mf_id' => $mfItem['id']],
                [
                    'name' => $mfItem['name'],
                    'sku' => $mfItem['code'] ?? $mfItem['id'],
                    'price' => $mfItem['price'],
                    'unit' => $mfItem['unit'],
                    'description' => $mfItem['detail'],
                    'tax_category' => $mfItem['excise'],
                    'quantity' => $mfItem['quantity'],
                    'is_deduct_withholding_tax' => $mfItem['is_deduct_withholding_tax'] ?? null,
                    'mf_updated_at' => Carbon::parse($mfItem['updated_at']),
                ]
            );
            $syncedCount++;
        }

        return redirect()->route('products.index')->with('success', "Synced {$syncedCount} products from Money Forward.");
    }

    private function doSyncOne(Product $product, string $token, MoneyForwardApiService $apiService)
    {
        $data = [
            'name' => $product->name,
            'sku' => $product->sku,
            'detail' => $product->description,
            'unit' => $product->unit,
            'price' => $product->price,
            'quantity' => $product->quantity,
            'excise' => $product->tax_category,
            'is_deduct_withholding_tax' => $product->is_deduct_withholding_tax,
        ];

        $result = null;
        if ($product->mf_id) {
            $result = $apiService->updateItem($token, $product->mf_id, $data);

            // Handle case where item was deleted on MF side (stale mf_id)
            if (is_array($result) && isset($result['error']) && $result['error'] === 'not_found') {
                Log::info('MF item not found, attempting to re-create.', ['product_id' => $product->id, 'stale_mf_id' => $product->mf_id]);
                $product->mf_id = null;
                $result = $apiService->createItem($token, $data);
            }
        } else {
            $result = $apiService->createItem($token, $data);
        }

        // After create or update, save the new mf_id if it was a creation
        if ($result && isset($result['id']) && !$product->mf_id) {
            $product->mf_id = $result['id'];
            $product->mf_updated_at = Carbon::parse($result['updated_at']);
            $product->save();
        }

        if (!$result || isset($result['error_message']) || (is_array($result) && isset($result['error']))) {
            Log::error('MF Sync One Error', ['result' => $result]);
            $errorMessage = is_array($result) ? json_encode($result) : 'Check logs.';
            return redirect()->route('products.index')->with('error', 'Failed to sync product to Money Forward. ' . $errorMessage);
        }

        return redirect()->route('products.index')->with('success', "Product {$product->name} synced to Money Forward successfully.");
    }
}
