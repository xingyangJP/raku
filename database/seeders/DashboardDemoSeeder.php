<?php

namespace Database\Seeders;

use App\Models\CompanySetting;
use App\Models\Estimate;
use App\Models\MaintenanceFeeSnapshot;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Database\Seeder;

class DashboardDemoSeeder extends Seeder
{
    private const SNAPSHOT_SOURCE = 'dashboard_demo_seed_v1';
    private const ESTIMATE_NUMBER_PREFIX = 'DEMO-DASH';

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
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

    private function configureCompanySetting(): void
    {
        $setting = CompanySetting::current();
        $setting->fill([
            'company_name' => $setting->company_name ?: 'KCS販売管理',
            'operational_staff_count' => 10,
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
        $staffNames = $this->staffNames();
        $customerNames = $this->developmentCustomers();
        $staffIndex = ($index + ($isConfirmed ? 0 : 3)) % count($staffNames);
        $customerIndex = ($index + ($isConfirmed ? 1 : 4)) % count($customerNames);
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
            [
                'name' => '要件定義・設計',
                'description' => '上流設計と仕様整理',
                'qty' => $designDays,
                'unit' => '人日',
                'price' => $laborUnitPrice,
                'cost' => $laborUnitCost,
                'business_division' => 'fifth_business',
            ],
            [
                'name' => '実装・検証',
                'description' => '実装と単体・結合テスト',
                'qty' => $buildDays,
                'unit' => '人日',
                'price' => $laborUnitPrice + 4000,
                'cost' => $laborUnitCost + 1000,
                'business_division' => 'fifth_business',
            ],
            [
                'name' => 'クラウド・ライセンス',
                'description' => '検証環境と運用準備',
                'qty' => 1,
                'unit' => '式',
                'price' => $cloudPrice,
                'cost' => $cloudCost,
                'business_division' => 'fifth_business',
            ],
        ];

        $this->upsertEstimate(
            estimateNumber: sprintf('%s-%s-%s', self::ESTIMATE_NUMBER_PREFIX, $month->format('Ym'), $suffix),
            title: sprintf('[DEMO-DASHBOARD] %s月 開発%s案件', $month->format('Y年n'), $isConfirmed ? '受注' : '見込'),
            customerName: $customerNames[$customerIndex],
            staffName: $staffNames[$staffIndex],
            staffId: $staffIndex + 1,
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
        $staffNames = $this->staffNames();
        $customerNames = $this->salesCustomers();
        $staffIndex = ($index + ($isConfirmed ? 5 : 7)) % count($staffNames);
        $customerIndex = ($index + ($isConfirmed ? 2 : 5)) % count($customerNames);

        $deviceUnits = 2 + (($index + ($isConfirmed ? 0 : 1)) % 3);
        $devicePrice = 360000 + (($index % 4) * 30000);
        $deviceCost = 245000 + (($index % 5) * 20000);
        $setupPrice = 180000 + (($index % 3) * 25000);
        $setupCost = 110000 + (($index % 4) * 18000);

        $issueDate = $month->copy()->subDays(12);
        $deliveryDate = $month->copy()->day(min(26, 12 + ($index % 10)));
        $dueDate = $deliveryDate->copy()->addDays(25);

        $items = [
            [
                'name' => 'ネットワーク機器一式',
                'description' => '仕入れ販売案件',
                'qty' => $deviceUnits,
                'unit' => '台',
                'price' => $devicePrice,
                'cost' => $deviceCost,
                'business_division' => 'first_business',
            ],
            [
                'name' => '導入セットアップ',
                'description' => '現地設定と初期設定',
                'qty' => 1,
                'unit' => '式',
                'price' => $setupPrice,
                'cost' => $setupCost,
                'business_division' => 'first_business',
            ],
        ];

        $this->upsertEstimate(
            estimateNumber: sprintf('%s-%s-%s', self::ESTIMATE_NUMBER_PREFIX, $month->format('Ym'), $suffix),
            title: sprintf('[DEMO-DASHBOARD] %s月 販売%s案件', $month->format('Y年n'), $isConfirmed ? '受注' : '見込'),
            customerName: $customerNames[$customerIndex],
            staffName: $staffNames[$staffIndex],
            staffId: $staffIndex + 1,
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
                'customer_name' => '青山クリニック',
                'maintenance_fee' => 220000 + (($index % 4) * 12000),
                'support_type' => 'フルサポート',
            ],
            [
                'customer_name' => '熊本物流センター',
                'maintenance_fee' => 180000 + (($index % 3) * 15000),
                'support_type' => '運用保守',
            ],
            [
                'customer_name' => '中央建設',
                'maintenance_fee' => 140000 + (($index % 5) * 10000),
                'support_type' => '監視',
            ],
            [
                'customer_name' => '東町学園',
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
                'client_id' => 'demo-client-' . strtolower($estimateNumber),
                'mf_department_id' => 'demo-dept',
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
                'approval_flow' => $this->buildApprovalFlow($staffName, $isOrderConfirmed),
                'approval_started' => $approvalStarted,
                'is_order_confirmed' => $isOrderConfirmed,
            ]
        );
    }

    private function buildApprovalFlow(string $staffName, bool $isApproved): array
    {
        return [
            [
                'id' => 9001,
                'name' => '部門承認者',
                'approved_at' => $isApproved ? now()->subDays(2)->toDateTimeString() : null,
                'status' => $isApproved ? 'approved' : 'pending',
            ],
            [
                'id' => 9002,
                'name' => $staffName . ' マネージャー',
                'approved_at' => $isApproved ? now()->subDay()->toDateTimeString() : null,
                'status' => $isApproved ? 'approved' : 'pending',
            ],
        ];
    }

    private function staffNames(): array
    {
        return [
            '青木',
            '井上',
            '上田',
            '岡本',
            '川口',
            '清水',
            '高橋',
            '田口',
            '中村',
            '守部',
        ];
    }

    private function developmentCustomers(): array
    {
        return [
            '九州製造',
            '東海ソリューション',
            '西日本メディカル',
            '熊本市教育委員会',
            '南星物流',
            '光洋設備',
        ];
    }

    private function salesCustomers(): array
    {
        return [
            '大牟田商事',
            '西部フーズ',
            '天草観光開発',
            '水前寺工業',
            '阿蘇エネルギー',
            '玉名印刷',
        ];
    }
}
