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
    public function index(Request $request, MoneyForwardApiService $apiService)
    {
        $autoSyncResult = $this->handleAutoSyncToMf($request, $apiService);
        if ($autoSyncResult instanceof \Illuminate\Http\RedirectResponse) {
            return $autoSyncResult;
        }

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

        if ($request->filled('search_business_division') && $request->input('search_business_division') !== 'all') {
            $query->where('business_division', $request->input('search_business_division'));
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
            'filters' => $request->only(['search_name', 'search_sku', 'search_tax_category', 'search_category_id', 'search_business_division']),
            'businessDivisions' => $this->businessDivisionOptions(),
            'defaultBusinessDivision' => $this->defaultBusinessDivision(),
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
            'businessDivisions' => $this->businessDivisionOptions(),
            'defaultBusinessDivision' => $this->defaultBusinessDivision(),
        ]);
    }

    public function store(ProductRequest $request)
    {
        $data = $request->validated();
        $selectedBusinessDivision = $data['business_division'] ?? $this->defaultBusinessDivision();

        // Server-side SKU auto-generation using categories.last_item_seq
        $product = DB::transaction(function () use ($data, $selectedBusinessDivision) {
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
                'business_division' => $selectedBusinessDivision,
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
            'businessDivisions' => $this->businessDivisionOptions(),
            'defaultBusinessDivision' => $this->defaultBusinessDivision(),
        ]);
    }

    public function update(ProductRequest $request, Product $product)
    {
        $data = $request->validated();
        $selectedBusinessDivision = $data['business_division'] ?? $this->defaultBusinessDivision();

        // If category changes, re-number in the new category
        $newCategoryId = (int) $data['category_id'];
        if ($product->category_id !== $newCategoryId) {
            DB::transaction(function () use ($data, $product, $newCategoryId, $selectedBusinessDivision) {
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
                    'business_division' => $selectedBusinessDivision,
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
                'business_division' => $selectedBusinessDivision,
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

    private function businessDivisionOptions(): array
    {
        return collect(config('business_divisions.options', []))
            ->map(function (array $option, string $value) {
                return [
                    'value' => $value,
                    'label' => $option['label'] ?? $value,
                    'display_rate' => $option['display_rate'] ?? null,
                    'description' => $option['description'] ?? null,
                ];
            })
            ->values()
            ->all();
    }

    private function defaultBusinessDivision(): string
    {
        return config('business_divisions.default', 'fifth_business');
    }

    // --- Money Forward Sync Logic ---

    public function syncAllToMf(Request $request, MoneyForwardApiService $apiService)
    {
        $request->session()->forget('mf_products_redirect_back');

        $requiredScopes = ['mfc/invoice/data.read', 'mfc/invoice/data.write'];

        if ($token = $apiService->getValidAccessToken(null, $requiredScopes)) {
            return $this->doSyncAllToMf($token, $apiService);
        }

        $request->session()->put('mf_redirect_action', 'sync_all_to_mf');
        $request->session()->put('mf_products_redirect_back', url()->full());

        return redirect()->route('products.auth.start');
    }

    public function syncOneToMf(Product $product, Request $request, MoneyForwardApiService $apiService)
    {
        $request->session()->forget('mf_products_redirect_back');

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
        $action = $request->session()->pull('mf_redirect_action');

        if (in_array($action, ['sync_all', 'sync_all_to_mf'], true)) {
            return $this->doSyncAllToMf($token, $apiService);
        } elseif ($action === 'sync_one') {
            $productId = $request->session()->pull('mf_product_sync_id');
            $product = Product::find($productId);
            if (!$product) {
                return redirect()->route('products.index')->with('error', 'Product to sync not found.');
            }
            return $this->doSyncOne($product, $token, $apiService);
        } elseif ($action === 'sync_from_mf') {
            return $this->doSyncFromMf($token, $apiService);
        }

        return redirect()->route('products.index')->with('error', 'Unknown sync action.');
    }

    private function doSyncOne(Product $product, string $token, MoneyForwardApiService $apiService)
    {
        $data = [
            'name' => $product->name,
            'code' => $product->sku,
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

        if (!$result || isset($result['error_message']) || (is_array($result) && isset($result['error']))) {
            Log::error('MF Sync One Error', ['result' => $result]);
            $errorMessage = is_array($result) ? json_encode($result) : 'Check logs.';
            return redirect()->route('products.index')->with('error', 'Failed to sync product to Money Forward. ' . $errorMessage);
        }

        if (isset($result['id'])) {
            $product->mf_id = $result['id'];
        }

        if (!empty($result['updated_at'])) {
            try {
                $product->mf_updated_at = Carbon::parse($result['updated_at']);
            } catch (\Exception $e) {
                Log::warning('Failed to parse Money Forward updated_at after sync', [
                    'product_id' => $product->id,
                    'updated_at' => $result['updated_at'],
                    'exception' => $e->getMessage(),
                ]);
            }
        }

        $product->save();

        return redirect()->route('products.index')->with('success', "Product {$product->name} synced to Money Forward successfully.");
    }

    private function doSyncAllToMf(string $token, MoneyForwardApiService $apiService)
    {
        $result = $this->performSyncAllToMf($token, $apiService);
        $flashKey = ($result['status'] ?? null) === 'success' ? 'success' : 'error';

        session()->flash('mf_skip_product_auto_sync', true);

        $redirectBack = session()->pull('mf_products_redirect_back');
        $redirectResponse = $redirectBack ? redirect()->to($redirectBack) : redirect()->route('products.index');

        return $redirectResponse->with($flashKey, $result['message'] ?? '同期結果が不明です。');
    }

    private function performSyncAllToMf(string $token, MoneyForwardApiService $apiService): array
    {
        $remoteItems = $apiService->getItems($token);
        if (!is_array($remoteItems)) {
            return [
                'status' => 'error',
                'message' => 'Money Forwardの品目一覧取得に失敗しました。',
            ];
        }

        $remoteById = [];
        $remoteByCode = [];
        foreach ($remoteItems as $item) {
            $id = $item['id'] ?? null;
            if ($id) {
                $remoteById[$id] = $item;
            }
            if (!empty($item['code'])) {
                $remoteByCode[$item['code']] = $item;
            }
        }

        $stats = [
            'updated_remote' => 0,
            'created_remote' => 0,
            'updated_local' => 0,
            'created_local' => 0,
        ];
        $errors = [];

        Product::query()->orderBy('id')->chunkById(100, function ($products) use (
            $apiService,
            $token,
            &$remoteById,
            &$remoteByCode,
            &$stats,
            &$errors
        ) {
            foreach ($products as $product) {
                $remoteItem = null;
                $remoteId = null;

                if ($product->mf_id && isset($remoteById[$product->mf_id])) {
                    $remoteId = $product->mf_id;
                    $remoteItem = $remoteById[$remoteId];
                } elseif (isset($remoteByCode[$product->sku])) {
                    $remoteItem = $remoteByCode[$product->sku];
                    $remoteId = $remoteItem['id'] ?? null;
                }

                if ($remoteItem) {
                    unset($remoteById[$remoteId]);
                    if (!empty($remoteItem['code'])) {
                        unset($remoteByCode[$remoteItem['code']]);
                    }

                    $remoteUpdatedAt = $this->parseRemoteTimestamp($remoteItem['updated_at'] ?? null);
                    $localUpdatedAt = $product->updated_at ?? $product->created_at;

                    if ($remoteUpdatedAt && (!$localUpdatedAt || $remoteUpdatedAt->gt($localUpdatedAt))) {
                        $this->applyRemoteItemToProduct($product, $remoteItem, $remoteUpdatedAt);
                        $product->save();
                        $stats['updated_local']++;
                    } else {
                        $result = $this->syncProductToMoneyForward($product, $token, $apiService, $remoteId);
                        if ($result === null) {
                            $errors[] = ['product_id' => $product->id, 'action' => 'update'];
                        } elseif ($result === 'created') {
                            $stats['created_remote']++;
                        } else {
                            $stats['updated_remote']++;
                        }
                    }
                } else {
                    $result = $this->syncProductToMoneyForward($product, $token, $apiService, null);
                    if ($result === null) {
                        $errors[] = ['product_id' => $product->id, 'action' => 'create'];
                    } else {
                        $stats['created_remote']++;
                    }
                }
            }
        });

        foreach ($remoteById as $item) {
            $mfId = (string) ($item['id'] ?? '');
            if ($mfId === '') {
                continue;
            }

            $baseSku = !empty($item['code']) ? $item['code'] : 'MF-' . $mfId;
            $sku = $this->ensureUniqueSku($baseSku);

            $product = Product::create([
                'mf_id' => $mfId,
                'sku' => $sku,
                'name' => $item['name'] ?? '名称未設定',
                'description' => $item['detail'] ?? null,
                'unit' => $item['unit'] ?? '式',
                'price' => $this->normalizeNumber($item['price'] ?? 0, 0.0) ?? 0,
                'quantity' => $this->normalizeNumber($item['quantity'] ?? null),
                'tax_category' => $this->mapExciseToTaxCategory($item['excise'] ?? null),
                'is_deduct_withholding_tax' => $this->normalizeBoolean($item['is_deduct_withholding_tax'] ?? null),
                'business_division' => null,
                'cost' => 0,
                'category_id' => null,
                'seq' => null,
            ]);

            if (!empty($item['updated_at'])) {
                try {
                    $product->mf_updated_at = Carbon::parse($item['updated_at']);
                    $product->save();
                } catch (\Throwable $e) {
                    Log::warning('Failed to parse Money Forward item updated_at during import', [
                        'item_id' => $mfId,
                        'exception' => $e->getMessage(),
                    ]);
                }
            }

            $stats['created_local']++;
        }

        $message = sprintf(
            'Money Forwardと同期しました（MF更新: %d件 / MF作成: %d件 / ローカル更新: %d件 / ローカル作成: %d件）',
            $stats['updated_remote'],
            $stats['created_remote'],
            $stats['updated_local'],
            $stats['created_local']
        );

        if (!empty($errors)) {
            $message .= ' 一部の品目で同期に失敗しました。ログを確認してください。';
        }

        return [
            'status' => empty($errors) ? 'success' : 'error',
            'message' => $message,
            'errors' => $errors,
        ];
    }

    private function syncProductToMoneyForward(Product $product, string $token, MoneyForwardApiService $apiService, ?string $remoteId = null): ?string
    {
        $payload = $this->buildMfItemPayload($product);
        $action = 'updated';
        $result = null;

        if ($remoteId) {
            $result = $apiService->updateItem($token, $remoteId, $payload);
            if (is_array($result) && isset($result['error']) && $result['error'] === 'not_found') {
                Log::info('MF item not found during sync, creating new one.', [
                    'product_id' => $product->id,
                    'stale_mf_id' => $remoteId,
                ]);
                $remoteId = null;
                $result = null;
            }
        }

        if (!$remoteId) {
            $result = $apiService->createItem($token, $payload);
            $action = 'created';
        }

        if (!$this->isSuccessfulMfResponse($result)) {
            Log::error('Failed to sync product to Money Forward.', [
                'product_id' => $product->id,
                'payload' => $payload,
                'response' => $result,
            ]);
            return null;
        }

        $mfId = $result['id'] ?? $remoteId;
        if ($mfId) {
            $product->mf_id = $mfId;
        }

        if (!empty($result['updated_at'])) {
            $parsed = $this->parseRemoteTimestamp($result['updated_at']);
            if ($parsed) {
                $product->mf_updated_at = $parsed;
            }
        }

        $product->save();

        return $action;
    }

    private function applyRemoteItemToProduct(Product $product, array $remoteItem, ?Carbon $remoteUpdatedAt = null): void
    {
        $product->mf_id = (string) ($remoteItem['id'] ?? $product->mf_id);
        $product->name = $remoteItem['name'] ?? $product->name;
        $product->description = $remoteItem['detail'] ?? $product->description;
        $product->unit = $remoteItem['unit'] ?? $product->unit;
        $product->price = $this->normalizeNumber($remoteItem['price'] ?? $product->price, $product->price) ?? $product->price;
        $qty = $this->normalizeNumber($remoteItem['quantity'] ?? null);
        if ($qty !== null) {
            $product->quantity = $qty;
        }
        $product->tax_category = $this->mapExciseToTaxCategory($remoteItem['excise'] ?? null);
        $withholding = $this->normalizeBoolean($remoteItem['is_deduct_withholding_tax'] ?? null);
        if ($withholding !== null) {
            $product->is_deduct_withholding_tax = $withholding;
        }
        if ($remoteUpdatedAt) {
            $product->mf_updated_at = $remoteUpdatedAt;
        }
    }

    private function parseRemoteTimestamp(?string $timestamp): ?Carbon
    {
        if (!$timestamp) {
            return null;
        }

        try {
            return Carbon::parse($timestamp);
        } catch (\Throwable $e) {
            Log::warning('Failed to parse remote timestamp', [
                'timestamp' => $timestamp,
                'exception' => $e->getMessage(),
            ]);
            return null;
        }
    }

    private function buildMfItemPayload(Product $product): array
    {
        $payload = [
            'name' => $product->name,
            'code' => $product->sku,
            'detail' => $product->description,
            'unit' => $product->unit,
            'price' => $this->normalizeNumber($product->price, 0.0),
            'excise' => $product->tax_category,
        ];

        $quantity = $this->normalizeNumber($product->quantity);
        if ($quantity !== null) {
            $payload['quantity'] = $quantity;
        }

        $withholding = $this->normalizeBoolean($product->is_deduct_withholding_tax);
        if ($withholding !== null) {
            $payload['is_deduct_withholding_tax'] = $withholding;
        }

        return array_filter($payload, static fn($value) => $value !== null && $value !== '');
    }

    private function handleAutoSyncToMf(Request $request, MoneyForwardApiService $apiService): array|\Illuminate\Http\RedirectResponse|null
    {
        if ($request->session()->pull('mf_skip_product_auto_sync', false)) {
            return ['status' => 'skipped'];
        }

        $token = $apiService->getValidAccessToken(null, ['mfc/invoice/data.read', 'mfc/invoice/data.write']);
        if (!$token) {
            $request->session()->put('mf_redirect_action', 'sync_all_to_mf');
            $request->session()->put('mf_products_redirect_back', url()->full());
            return redirect()->route('products.auth.start');
        }

        $result = $this->performSyncAllToMf($token, $apiService);
        $flashKey = ($result['status'] ?? null) === 'success' ? 'success' : 'error';

        session()->flash($flashKey, $result['message'] ?? '同期結果が不明です。');

        return $result;
    }

    private function isSuccessfulMfResponse(mixed $response): bool
    {
        if ($response === null) {
            return false;
        }

        if (is_array($response)) {
            if (isset($response['error']) || isset($response['error_message'])) {
                return false;
            }

            return true;
        }

        return true;
    }

    private function normalizeNumber(mixed $value, ?float $default = null): ?float
    {
        if ($value === null) {
            return $default;
        }

        if (is_string($value)) {
            $value = trim($value);
            if ($value === '') {
                return $default;
            }
            $value = str_replace(',', '', $value);
        }

        if (is_numeric($value)) {
            return (float) $value;
        }

        return $default;
    }

    private function normalizeBoolean(mixed $value): ?bool
    {
        if ($value === null) {
            return null;
        }

        if (is_string($value)) {
            $value = strtolower(trim($value));
            if ($value === '') {
                return null;
            }
            if (in_array($value, ['1', 'true', 'yes'], true)) {
                return true;
            }
            if (in_array($value, ['0', 'false', 'no'], true)) {
                return false;
            }
            return null;
        }

        if (is_bool($value)) {
            return $value;
        }

        if (is_numeric($value)) {
            return (int) $value === 1 ? true : ((int) $value === 0 ? false : null);
        }

        return null;
    }

    private function mapExciseToTaxCategory(?string $excise): string
    {
        return match ($excise) {
            'ten_percent' => 'ten_percent',
            'eight_percent', 'eight_percent_reduced', 'eight_percent_as_reduced_tax_rate' => 'eight_percent_as_reduced_tax_rate',
            'five_percent' => 'five_percent',
            'tax_exemption' => 'tax_exemption',
            'non_taxable' => 'non_taxable',
            'untaxable' => 'untaxable',
            default => 'ten_percent',
        };
    }

    private function ensureUniqueSku(string $baseSku): string
    {
        $candidate = $baseSku;
        $suffix = 1;
        while (Product::where('sku', $candidate)->exists()) {
            $candidate = $baseSku . '-' . $suffix;
            $suffix++;
        }

        return $candidate;
    }
}
