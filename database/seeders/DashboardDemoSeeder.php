<?php

namespace Database\Seeders;

use App\Models\CompanySetting;
use App\Models\Estimate;
use App\Models\MaintenanceFeeSnapshot;
use App\Models\Partner;
use App\Models\Product;
use App\Models\User;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;

class DashboardDemoSeeder extends Seeder
{
    private const SNAPSHOT_SOURCE = 'dashboard_demo_seed_v1';
    private const ESTIMATE_NUMBER_PREFIX = 'DEMO-DASH';

    private Collection $partnerPool;
    private Collection $staffPool;
    private Collection $productPool;

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $this->bootstrapReferenceData();
        $this->configureCompanySetting();
        $this->purgeDemoData();

        $months = collect(CarbonPeriod::create('2025-01-01', '1 month', '2026-12-01'))
            ->map(fn ($month) => Carbon::instance($month)->startOfMonth())
            ->values();

        $months->each(function (Carbon $month, int $index): void {
            $this->seedMonth($month, $index);
        });

        $this->command?->info(sprintf(
            'DashboardDemoSeeder: %d months / %d estimates / %d maintenance snapshots を投入しました。',
            $months->count(),
            Estimate::query()->where('estimate_number', 'like', self::ESTIMATE_NUMBER_PREFIX . '-%')->count(),
            MaintenanceFeeSnapshot::query()->where('source', self::SNAPSHOT_SOURCE)->count()
        ));
    }

    private function bootstrapReferenceData(): void
    {
        $this->productPool = Product::query()
            ->select(['id', 'sku', 'name', 'unit', 'price', 'cost', 'tax_category', 'business_division'])
            ->where('is_active', true)
            ->orderBy('id')
            ->get()
            ->values();

        if ($this->productPool->isEmpty()) {
            throw new \RuntimeException('DashboardDemoSeeder: products が存在しないため、既存商品マスタベースのダミーデータを生成できません。');
        }

        $this->partnerPool = Partner::query()
            ->select(['id', 'name', 'mf_partner_id'])
            ->whereNotNull('name')
            ->where('name', '!=', '')
            ->whereNotNull('mf_partner_id')
            ->where('mf_partner_id', '!=', '')
            ->orderBy('id')
            ->get()
            ->values();

        if ($this->partnerPool->isEmpty()) {
            throw new \RuntimeException('DashboardDemoSeeder: partners が存在しないため、既存顧客ベースのダミーデータを生成できません。');
        }

        $staffPool = User::query()
            ->select(['id', 'name', 'email', 'external_user_id'])
            ->whereNotNull('name')
            ->where('name', '!=', '')
            ->orderBy('id')
            ->get()
            ->filter(function (User $user): bool {
                $haystack = mb_strtolower(trim(($user->name ?? '') . ' ' . ($user->email ?? '')));

                return !str_contains($haystack, 'codex')
                    && !str_contains($haystack, 'test')
                    && !str_contains($haystack, 'session');
            })
            ->values();

        if ($staffPool->isEmpty()) {
            $staffPool = User::query()
                ->select(['id', 'name', 'email', 'external_user_id'])
                ->whereNotNull('name')
                ->where('name', '!=', '')
                ->orderBy('id')
                ->get()
                ->values();
        }

        if ($staffPool->isEmpty()) {
            throw new \RuntimeException('DashboardDemoSeeder: users が存在しないため、既存スタッフベースのダミーデータを生成できません。');
        }

        $this->staffPool = $staffPool;
    }

    private function configureCompanySetting(): void
    {
        $setting = CompanySetting::current();
        $setting->fill([
            'company_name' => $setting->company_name ?: 'KCS販売管理',
            'operational_staff_count' => max(1, $this->staffPool->count()),
        ]);
        $setting->save();
    }

    private function purgeDemoData(): void
    {
        Estimate::query()
            ->where('estimate_number', 'like', self::ESTIMATE_NUMBER_PREFIX . '-%')
            ->delete();

        $snapshots = MaintenanceFeeSnapshot::query()
            ->where('source', self::SNAPSHOT_SOURCE)
            ->get();

        foreach ($snapshots as $snapshot) {
            $snapshot->items()->delete();
            $snapshot->delete();
        }
    }

    private function seedMonth(Carbon $month, int $index): void
    {
        $isCurrentMonth = $month->isSameMonth(Carbon::now()->startOfMonth());

        $this->seedDevelopmentEstimate($month, $index, true, $isCurrentMonth ? 88 : 36 + (($index + 1) % 4) * 10);
        $this->seedDevelopmentEstimate($month, $index, false, $isCurrentMonth ? 132 : 22 + (($index + 2) % 5) * 8);
        $this->seedSalesEstimate($month, $index, true);

        if ($index % 2 === 0 || $isCurrentMonth) {
            $this->seedSalesEstimate($month, $index, false);
        }

        $this->seedMaintenanceSnapshot($month, $index);
    }

    private function seedDevelopmentEstimate(Carbon $month, int $index, bool $isConfirmed, float $personDays): void
    {
        $suffix = $isConfirmed ? 'DEV-C' : 'DEV-P';
        $staff = $this->resolveStaff($index + ($isConfirmed ? 0 : 3));
        $customer = $this->resolvePartner($index + ($isConfirmed ? 1 : 4));
        $laborUnitPrice = 78000 + (($index % 4) * 2000);
        $laborUnitCost = 30000 + (($index % 3) * 1500);
        $cloudPrice = 150000 + (($index % 5) * 20000);
        $cloudCost = 90000 + (($index % 4) * 12000);

        $designDays = round($personDays * 0.35, 1);
        $buildDays = round($personDays * 0.65, 1);
        $issueDate = $month->copy()->subDays(18);
        $deliveryDate = $month->copy()->day(min(25, 10 + ($index % 12)));
        $dueDate = $deliveryDate->copy()->addDays(20);

        $items = [
            $this->buildProductItem('A-001', [
                'description' => '上流設計と仕様整理',
                'qty' => $designDays,
                'price' => $laborUnitPrice,
                'cost' => $laborUnitCost,
                'business_division' => 'fifth_business',
                'assignees' => $this->buildDevelopmentAssignees($index + ($isConfirmed ? 0 : 3), false),
            ]),
            $this->buildProductItem('B-001', [
                'description' => '実装と単体・結合テスト',
                'qty' => $buildDays,
                'price' => $laborUnitPrice + 4000,
                'cost' => $laborUnitCost + 1000,
                'business_division' => 'fifth_business',
                'assignees' => $this->buildDevelopmentAssignees($index + ($isConfirmed ? 0 : 3), true),
            ]),
            $this->buildProductItem('F-001', [
                'description' => '検証環境と運用準備',
                'qty' => 1,
                'price' => $cloudPrice,
                'cost' => $cloudCost,
                'business_division' => 'fifth_business',
            ]),
        ];

        $this->upsertEstimate(
            estimateNumber: sprintf('%s-%s-%s', self::ESTIMATE_NUMBER_PREFIX, $month->format('Ym'), $suffix),
            title: sprintf('[DEMO-DASHBOARD] %s月 開発%s案件', $month->format('Y年n'), $isConfirmed ? '受注' : '見込'),
            customerName: (string) $customer['name'],
            clientId: (string) $customer['mf_partner_id'],
            staffName: (string) $staff['name'],
            staffId: (int) $staff['id'],
            issueDate: $issueDate,
            dueDate: $dueDate,
            deliveryDate: $deliveryDate,
            items: $items,
            status: $isConfirmed ? 'sent' : 'pending',
            isOrderConfirmed: $isConfirmed,
            approvalStarted: true
        );
    }

    private function seedSalesEstimate(Carbon $month, int $index, bool $isConfirmed): void
    {
        $suffix = $isConfirmed ? 'SAL-C' : 'SAL-P';
        $staff = $this->resolveStaff($index + ($isConfirmed ? 5 : 7));
        $customer = $this->resolvePartner($index + ($isConfirmed ? 18 : 27));

        $deviceUnits = 2 + (($index + ($isConfirmed ? 0 : 1)) % 3);
        $devicePrice = 360000 + (($index % 4) * 30000);
        $deviceCost = 245000 + (($index % 5) * 20000);
        $setupPrice = 180000 + (($index % 3) * 25000);
        $setupCost = 110000 + (($index % 4) * 18000);

        $issueDate = $month->copy()->subDays(12);
        $deliveryDate = $month->copy()->day(min(26, 12 + ($index % 10)));
        $dueDate = $deliveryDate->copy()->addDays(25);

        $items = [
            $this->buildProductItem('E-001', [
                'description' => '仕入れ販売案件',
                'qty' => $deviceUnits,
                'price' => $devicePrice,
                'cost' => $deviceCost,
                'business_division' => 'first_business',
            ]),
            $this->buildProductItem('F-001', [
                'description' => '現地設定と初期設定',
                'qty' => 1,
                'price' => $setupPrice,
                'cost' => $setupCost,
                'business_division' => 'first_business',
            ]),
        ];

        $this->upsertEstimate(
            estimateNumber: sprintf('%s-%s-%s', self::ESTIMATE_NUMBER_PREFIX, $month->format('Ym'), $suffix),
            title: sprintf('[DEMO-DASHBOARD] %s月 販売%s案件', $month->format('Y年n'), $isConfirmed ? '受注' : '見込'),
            customerName: (string) $customer['name'],
            clientId: (string) $customer['mf_partner_id'],
            staffName: (string) $staff['name'],
            staffId: (int) $staff['id'],
            issueDate: $issueDate,
            dueDate: $dueDate,
            deliveryDate: $deliveryDate,
            items: $items,
            status: $isConfirmed ? 'sent' : 'pending',
            isOrderConfirmed: $isConfirmed,
            approvalStarted: true
        );
    }

    private function seedMaintenanceSnapshot(Carbon $month, int $index): void
    {
        $items = collect([
            [
                'customer_name' => $this->resolvePartner($index + 40)['name'],
                'maintenance_fee' => 220000 + (($index % 4) * 12000),
                'support_type' => 'フルサポート',
            ],
            [
                'customer_name' => $this->resolvePartner($index + 52)['name'],
                'maintenance_fee' => 180000 + (($index % 3) * 15000),
                'support_type' => '運用保守',
            ],
            [
                'customer_name' => $this->resolvePartner($index + 64)['name'],
                'maintenance_fee' => 140000 + (($index % 5) * 10000),
                'support_type' => '監視',
            ],
            [
                'customer_name' => $this->resolvePartner($index + 76)['name'],
                'maintenance_fee' => 95000 + (($index % 4) * 8000),
                'support_type' => 'ヘルプデスク',
            ],
        ])->map(function (array $item): array {
            return [
                ...$item,
                'status' => 'active',
                'maintenance_fee' => (float) $item['maintenance_fee'],
            ];
        });

        $totalFee = (float) $items->sum('maintenance_fee');
        $grossRate = 0.84 + (($index % 3) * 0.02);

        $snapshot = MaintenanceFeeSnapshot::query()->updateOrCreate(
            ['month' => $month->toDateString()],
            [
                'total_fee' => $totalFee,
                'total_gross' => round($totalFee * $grossRate, 2),
                'source' => self::SNAPSHOT_SOURCE,
            ]
        );

        $snapshot->items()->delete();
        $snapshot->items()->createMany($items->all());
    }

    private function upsertEstimate(
        string $estimateNumber,
        string $title,
        string $customerName,
        string $clientId,
        string $staffName,
        int $staffId,
        Carbon $issueDate,
        Carbon $dueDate,
        Carbon $deliveryDate,
        array $items,
        string $status,
        bool $isOrderConfirmed,
        bool $approvalStarted
    ): void {
        $subtotal = collect($items)->sum(function (array $item): float {
            return (float) ($item['qty'] ?? 0) * (float) ($item['price'] ?? 0);
        });

        Estimate::query()->updateOrCreate(
            ['estimate_number' => $estimateNumber],
            [
                'customer_name' => $customerName,
                'client_id' => $clientId,
                'mf_department_id' => null,
                'title' => $title,
                'issue_date' => $issueDate->toDateString(),
                'due_date' => $dueDate->toDateString(),
                'delivery_date' => $deliveryDate->toDateString(),
                'status' => $status,
                'total_amount' => round($subtotal),
                'tax_amount' => round($subtotal * 0.1),
                'notes' => 'ローカル確認用ダミーデータです。',
                'internal_memo' => 'seed:' . self::SNAPSHOT_SOURCE,
                'items' => $items,
                'staff_id' => $staffId,
                'staff_name' => $staffName,
                'approval_flow' => $this->buildApprovalFlow($staffId, $staffName, $isOrderConfirmed),
                'approval_started' => $approvalStarted,
                'is_order_confirmed' => $isOrderConfirmed,
            ]
        );
    }

    private function buildApprovalFlow(int $staffId, string $staffName, bool $isApproved): array
    {
        $primaryApprover = $this->resolveStaff($staffId);
        $secondaryApprover = $this->resolveStaff($staffId + 1);

        return [
            [
                'id' => $primaryApprover['id'],
                'name' => $primaryApprover['name'],
                'approved_at' => $isApproved ? now()->subDays(2)->toDateTimeString() : null,
                'status' => $isApproved ? 'approved' : 'pending',
            ],
            [
                'id' => $secondaryApprover['id'],
                'name' => $secondaryApprover['name'] ?: ($staffName . ' マネージャー'),
                'approved_at' => $isApproved ? now()->subDay()->toDateTimeString() : null,
                'status' => $isApproved ? 'approved' : 'pending',
            ],
        ];
    }

    private function buildDevelopmentAssignees(int $staffIndex, bool $isImplementation): array
    {
        return $this->buildAssigneesFromShares(
            $staffIndex,
            $isImplementation ? [45.0, 35.0, 20.0] : [60.0, 40.0]
        );
    }

    private function buildAssigneesFromShares(int $staffIndex, array $shares): array
    {
        $merged = [];

        foreach ($shares as $offset => $sharePercent) {
            $staff = $this->resolveStaff($staffIndex + $offset);
            $key = (string) $staff['id'];

            if (!isset($merged[$key])) {
                $merged[$key] = [
                    'user_id' => (string) $staff['id'],
                    'user_name' => (string) $staff['name'],
                    'share_percent' => 0.0,
                ];
            }

            $merged[$key]['share_percent'] += (float) $sharePercent;
        }

        return array_values(array_map(function (array $assignee): array {
            $assignee['share_percent'] = round((float) $assignee['share_percent'], 1);

            return $assignee;
        }, $merged));
    }

    private function buildProductItem(string $sku, array $overrides = []): array
    {
        $product = $this->resolveProduct($sku);

        return array_filter([
            'product_id' => $product['id'],
            'code' => $product['sku'],
            'name' => $overrides['name'] ?? $product['name'],
            'description' => $overrides['description'] ?? null,
            'qty' => $overrides['qty'] ?? 1,
            'unit' => $overrides['unit'] ?? $product['unit'],
            'price' => $overrides['price'] ?? $product['price'],
            'cost' => $overrides['cost'] ?? $product['cost'],
            'tax_category' => $overrides['tax_category'] ?? $product['tax_category'],
            'business_division' => $overrides['business_division'] ?? $product['business_division'],
            'assignees' => $overrides['assignees'] ?? null,
        ], static fn ($value) => $value !== null);
    }

    private function resolveProduct(string $sku): array
    {
        $product = $this->productPool->first(fn ($row) => (string) $row->sku === $sku);

        if (!$product) {
            throw new \RuntimeException(sprintf('DashboardDemoSeeder: SKU %s の商品が見つかりません。', $sku));
        }

        return [
            'id' => (int) $product->id,
            'sku' => (string) $product->sku,
            'name' => (string) $product->name,
            'unit' => (string) $product->unit,
            'price' => (float) $product->price,
            'cost' => (float) $product->cost,
            'tax_category' => (string) ($product->tax_category ?? 'taxable'),
            'business_division' => (string) ($product->business_division ?? 'fifth_business'),
        ];
    }

    private function resolvePartner(int $offset): array
    {
        $partner = $this->partnerPool->get($offset % $this->partnerPool->count());

        return [
            'id' => (int) $partner->id,
            'name' => (string) $partner->name,
            'mf_partner_id' => (string) $partner->mf_partner_id,
        ];
    }

    private function resolveStaff(int $offset): array
    {
        $staff = $this->staffPool->get($offset % $this->staffPool->count());

        return [
            'id' => (int) $staff->id,
            'name' => (string) $staff->name,
            'external_user_id' => $staff->external_user_id ? (string) $staff->external_user_id : null,
        ];
    }
}
