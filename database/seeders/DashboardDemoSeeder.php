<?php

namespace Database\Seeders;

use App\Models\Estimate;
use App\Models\Partner;
use App\Models\Product;
use App\Models\User;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;

class DashboardDemoSeeder extends Seeder
{
    private const ESTIMATE_NUMBER_PREFIX = 'DEMO-DASH';
    private const INTERNAL_MEMO_TAG = 'dashboard_demo_estimate_seed_v2';
    private const START_MONTH = '2025-01-01';
    private const END_MONTH = '2026-05-01';

    private Collection $partnerPool;
    private Collection $staffPool;
    private Collection $productPool;

    public function run(): void
    {
        $this->bootstrapReferenceData();
        $this->purgeDemoEstimates();

        $months = collect(CarbonPeriod::create(self::START_MONTH, '1 month', self::END_MONTH))
            ->map(fn ($month) => Carbon::instance($month)->startOfMonth())
            ->values();

        $months->each(function (Carbon $month, int $index): void {
            $this->seedMonth($month, $index);
        });

        $this->command?->info(sprintf(
            'DashboardDemoSeeder: %d months / %d estimates を投入しました。',
            $months->count(),
            Estimate::query()->where('estimate_number', 'like', self::ESTIMATE_NUMBER_PREFIX . '-%')->count()
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

    private function purgeDemoEstimates(): void
    {
        Estimate::query()
            ->where('estimate_number', 'like', self::ESTIMATE_NUMBER_PREFIX . '-%')
            ->delete();
    }

    private function seedMonth(Carbon $month, int $index): void
    {
        $this->seedDevelopmentConfirmed($month, $index);
        $this->seedDevelopmentPending($month, $index);
        $this->seedDevelopmentFollowUp($month, $index);
        $this->seedSalesConfirmed($month, $index);
        $this->seedSalesSent($month, $index);
        $this->seedSalesLost($month, $index);
        $this->seedDraftEstimate($month, $index);
    }

    private function seedDevelopmentConfirmed(Carbon $month, int $index): void
    {
        $personDays = 28 + (($index % 4) * 6);
        $staff = $this->resolveStaff($index + 1);
        $customer = $this->resolvePartner($index + 2);
        $designDays = round($personDays * 0.35, 1);
        $buildDays = round($personDays * 0.65, 1);
        $laborUnitPrice = 80000 + (($index % 3) * 2500);
        $laborUnitCost = 31000 + (($index % 4) * 1800);
        $cloudPrice = 120000 + (($index % 5) * 18000);
        $cloudCost = 70000 + (($index % 4) * 12000);

        $issueDate = $month->copy()->subDays(18);
        $deliveryDate = $month->copy()->day(min(24, 12 + ($index % 9)));
        $dueDate = $deliveryDate->copy()->addDays(20);

        $items = [
            $this->buildProductItem('A-001', [
                'description' => '要件整理と画面設計',
                'qty' => $designDays,
                'price' => $laborUnitPrice,
                'cost' => $laborUnitCost,
                'business_division' => 'fifth_business',
                'assignees' => $this->buildDevelopmentAssignees($index, false),
            ]),
            $this->buildProductItem('B-001', [
                'description' => '実装と結合試験',
                'qty' => $buildDays,
                'price' => $laborUnitPrice + 5000,
                'cost' => $laborUnitCost + 1500,
                'business_division' => 'fifth_business',
                'assignees' => $this->buildDevelopmentAssignees($index, true),
            ]),
            $this->buildProductItem('F-001', [
                'description' => '検証環境と導入準備',
                'qty' => 1,
                'price' => $cloudPrice,
                'cost' => $cloudCost,
                'business_division' => 'fifth_business',
            ]),
        ];

        $this->upsertEstimate([
            'estimate_number' => sprintf('%s-%s-DEV-C', self::ESTIMATE_NUMBER_PREFIX, $month->format('Ym')),
            'title' => sprintf('[DEMO-DASHBOARD] %s月 開発受注案件', $month->format('Y年n')),
            'customer_name' => (string) $customer['name'],
            'client_id' => (string) $customer['mf_partner_id'],
            'staff_name' => (string) $staff['name'],
            'staff_id' => (int) $staff['id'],
            'issue_date' => $issueDate,
            'due_date' => $dueDate,
            'delivery_date' => $deliveryDate,
            'status' => 'sent',
            'is_order_confirmed' => true,
            'approval_started' => true,
            'approval_flow' => $this->buildApprovalFlow((int) $staff['id'], (string) $staff['name'], 'approved', $issueDate),
            'items' => $items,
            'mf_quote_id' => sprintf('demo-mfq-devc-%s', strtolower($month->format('Ym'))),
            'notes' => 'ローカル / dev で開発受注パターンを確認するためのダミーデータです。',
            'internal_memo' => sprintf('seed:%s:dev_confirmed', self::INTERNAL_MEMO_TAG),
        ]);
    }

    private function seedDevelopmentPending(Carbon $month, int $index): void
    {
        $personDays = 16 + (($index % 3) * 5);
        $staff = $this->resolveStaff($index + 3);
        $customer = $this->resolvePartner($index + 9);
        $laborUnitPrice = 76000 + (($index % 4) * 1800);
        $laborUnitCost = 29500 + (($index % 3) * 1400);

        $issueDate = $month->copy()->day(4);
        $deliveryDate = $month->copy()->day(min(28, 20 + ($index % 6)));
        $dueDate = $deliveryDate->copy()->addDays(18);

        $items = [
            $this->buildProductItem('A-001', [
                'description' => '業務整理と基本設計',
                'qty' => round($personDays * 0.4, 1),
                'price' => $laborUnitPrice,
                'cost' => $laborUnitCost,
                'business_division' => 'fifth_business',
                'assignees' => $this->buildDevelopmentAssignees($index + 2, false),
            ]),
            $this->buildProductItem('B-001', [
                'description' => '実装見積と技術検証',
                'qty' => round($personDays * 0.6, 1),
                'price' => $laborUnitPrice + 3500,
                'cost' => $laborUnitCost + 1200,
                'business_division' => 'fifth_business',
                'assignees' => $this->buildDevelopmentAssignees($index + 2, true),
            ]),
        ];

        $this->upsertEstimate([
            'estimate_number' => sprintf('%s-%s-DEV-P', self::ESTIMATE_NUMBER_PREFIX, $month->format('Ym')),
            'title' => sprintf('[DEMO-DASHBOARD] %s月 開発承認待ち案件', $month->format('Y年n')),
            'customer_name' => (string) $customer['name'],
            'client_id' => (string) $customer['mf_partner_id'],
            'staff_name' => (string) $staff['name'],
            'staff_id' => (int) $staff['id'],
            'issue_date' => $issueDate,
            'due_date' => $dueDate,
            'delivery_date' => $deliveryDate,
            'status' => 'pending',
            'is_order_confirmed' => false,
            'approval_started' => true,
            'approval_flow' => $this->buildApprovalFlow((int) $staff['id'], (string) $staff['name'], 'pending', $issueDate),
            'items' => $items,
            'notes' => '承認待ちの見積パターン確認用ダミーデータです。',
            'internal_memo' => sprintf('seed:%s:dev_pending', self::INTERNAL_MEMO_TAG),
        ]);
    }

    private function seedDevelopmentFollowUp(Carbon $month, int $index): void
    {
        $personDays = 10 + (($index % 4) * 3);
        $staff = $this->resolveStaff($index + 5);
        $customer = $this->resolvePartner($index + 16);
        $laborUnitPrice = 72000 + (($index % 3) * 2000);
        $laborUnitCost = 28000 + (($index % 3) * 1300);

        $issueDate = $month->copy()->day(8);
        $deliveryDate = $month->copy()->day(min(22, 14 + ($index % 7)));
        $dueDate = $deliveryDate->copy()->addDays(12);
        $followUpDueDate = $dueDate->copy()->addDays(7);
        $overduePromptedAt = $followUpDueDate->copy()->addDay()->setTime(9, 30);

        $items = [
            $this->buildProductItem('B-001', [
                'description' => '追加改修と不具合是正',
                'qty' => round($personDays * 0.7, 1),
                'price' => $laborUnitPrice,
                'cost' => $laborUnitCost,
                'business_division' => 'fifth_business',
                'assignees' => $this->buildDevelopmentAssignees($index + 4, true),
            ]),
            $this->buildProductItem('F-001', [
                'description' => '受入調整と切替準備',
                'qty' => 1,
                'price' => 95000 + (($index % 4) * 10000),
                'cost' => 52000 + (($index % 3) * 9000),
                'business_division' => 'fifth_business',
            ]),
        ];

        $this->upsertEstimate([
            'estimate_number' => sprintf('%s-%s-DEV-F', self::ESTIMATE_NUMBER_PREFIX, $month->format('Ym')),
            'title' => sprintf('[DEMO-DASHBOARD] %s月 開発追跡案件', $month->format('Y年n')),
            'customer_name' => (string) $customer['name'],
            'client_id' => (string) $customer['mf_partner_id'],
            'staff_name' => (string) $staff['name'],
            'staff_id' => (int) $staff['id'],
            'issue_date' => $issueDate,
            'due_date' => $dueDate,
            'follow_up_due_date' => $followUpDueDate,
            'delivery_date' => $deliveryDate,
            'status' => 'sent',
            'is_order_confirmed' => false,
            'approval_started' => true,
            'approval_flow' => $this->buildApprovalFlow((int) $staff['id'], (string) $staff['name'], 'approved', $issueDate),
            'overdue_prompted_at' => $overduePromptedAt,
            'items' => $items,
            'notes' => '期限超過の追跡対応を確認するためのダミーデータです。',
            'internal_memo' => sprintf('seed:%s:dev_followup', self::INTERNAL_MEMO_TAG),
        ]);
    }

    private function seedSalesConfirmed(Carbon $month, int $index): void
    {
        $staff = $this->resolveStaff($index + 7);
        $customer = $this->resolvePartner($index + 21);
        $deviceUnits = 2 + (($index + 1) % 3);
        $devicePrice = 340000 + (($index % 4) * 28000);
        $deviceCost = 230000 + (($index % 5) * 17000);
        $setupPrice = 160000 + (($index % 3) * 22000);
        $setupCost = 102000 + (($index % 4) * 14000);

        $issueDate = $month->copy()->subDays(11);
        $deliveryDate = $month->copy()->day(min(25, 11 + ($index % 10)));
        $dueDate = $deliveryDate->copy()->addDays(25);

        $items = [
            $this->buildProductItem('E-001', [
                'description' => 'ハードウェア一式',
                'qty' => $deviceUnits,
                'price' => $devicePrice,
                'cost' => $deviceCost,
                'business_division' => 'first_business',
            ]),
            $this->buildProductItem('F-001', [
                'description' => '現地設定と初期導入',
                'qty' => 1,
                'price' => $setupPrice,
                'cost' => $setupCost,
                'business_division' => 'first_business',
            ]),
        ];

        $this->upsertEstimate([
            'estimate_number' => sprintf('%s-%s-SAL-C', self::ESTIMATE_NUMBER_PREFIX, $month->format('Ym')),
            'title' => sprintf('[DEMO-DASHBOARD] %s月 販売受注案件', $month->format('Y年n')),
            'customer_name' => (string) $customer['name'],
            'client_id' => (string) $customer['mf_partner_id'],
            'staff_name' => (string) $staff['name'],
            'staff_id' => (int) $staff['id'],
            'issue_date' => $issueDate,
            'due_date' => $dueDate,
            'delivery_date' => $deliveryDate,
            'status' => 'sent',
            'is_order_confirmed' => true,
            'approval_started' => true,
            'approval_flow' => $this->buildApprovalFlow((int) $staff['id'], (string) $staff['name'], 'approved', $issueDate),
            'items' => $items,
            'mf_quote_id' => sprintf('demo-mfq-salc-%s', strtolower($month->format('Ym'))),
            'notes' => '物販受注パターン確認用ダミーデータです。',
            'internal_memo' => sprintf('seed:%s:sales_confirmed', self::INTERNAL_MEMO_TAG),
        ]);
    }

    private function seedSalesSent(Carbon $month, int $index): void
    {
        $staff = $this->resolveStaff($index + 10);
        $customer = $this->resolvePartner($index + 29);
        $deviceUnits = 1 + (($index + 2) % 2);
        $devicePrice = 280000 + (($index % 3) * 35000);
        $deviceCost = 190000 + (($index % 4) * 16000);
        $setupPrice = 135000 + (($index % 2) * 18000);
        $setupCost = 90000 + (($index % 3) * 12000);

        $issueDate = $month->copy()->day(6);
        $deliveryDate = $month->copy()->day(min(27, 17 + ($index % 8)));
        $dueDate = $deliveryDate->copy()->addDays(21);
        $hasMfQuote = $index % 2 === 0;

        $items = [
            $this->buildProductItem('E-001', [
                'description' => '端末入替と周辺機器',
                'qty' => $deviceUnits,
                'price' => $devicePrice,
                'cost' => $deviceCost,
                'business_division' => 'first_business',
            ]),
            $this->buildProductItem('F-001', [
                'description' => '設置立会いと操作説明',
                'qty' => 1,
                'price' => $setupPrice,
                'cost' => $setupCost,
                'business_division' => 'first_business',
            ]),
        ];

        $this->upsertEstimate([
            'estimate_number' => sprintf('%s-%s-SAL-S', self::ESTIMATE_NUMBER_PREFIX, $month->format('Ym')),
            'title' => sprintf('[DEMO-DASHBOARD] %s月 販売送付済み案件', $month->format('Y年n')),
            'customer_name' => (string) $customer['name'],
            'client_id' => (string) $customer['mf_partner_id'],
            'staff_name' => (string) $staff['name'],
            'staff_id' => (int) $staff['id'],
            'issue_date' => $issueDate,
            'due_date' => $dueDate,
            'delivery_date' => $deliveryDate,
            'status' => 'sent',
            'is_order_confirmed' => false,
            'approval_started' => true,
            'approval_flow' => $this->buildApprovalFlow((int) $staff['id'], (string) $staff['name'], 'approved', $issueDate),
            'items' => $items,
            'mf_quote_id' => $hasMfQuote ? sprintf('demo-mfq-sals-%s', strtolower($month->format('Ym'))) : null,
            'notes' => $hasMfQuote
                ? '送付済み・未受注・MF連携済みの確認用ダミーデータです。'
                : '送付済み・未受注・MF未連携の確認用ダミーデータです。',
            'internal_memo' => sprintf('seed:%s:sales_sent', self::INTERNAL_MEMO_TAG),
        ]);
    }

    private function seedSalesLost(Carbon $month, int $index): void
    {
        $staff = $this->resolveStaff($index + 12);
        $customer = $this->resolvePartner($index + 34);
        $lostReasons = ['予算未確保', '競合選定', '時期見送り'];

        $issueDate = $month->copy()->day(3);
        $deliveryDate = $month->copy()->day(min(20, 12 + ($index % 6)));
        $dueDate = $deliveryDate->copy()->addDays(14);
        $lostAt = $month->copy()->day(min(27, 21 + ($index % 5)));
        $lostReason = $lostReasons[$index % count($lostReasons)];

        $items = [
            $this->buildProductItem('E-001', [
                'description' => '機器更新提案',
                'qty' => 1 + ($index % 2),
                'price' => 260000 + (($index % 3) * 30000),
                'cost' => 175000 + (($index % 4) * 14000),
                'business_division' => 'first_business',
            ]),
            $this->buildProductItem('F-001', [
                'description' => '導入支援',
                'qty' => 1,
                'price' => 98000 + (($index % 3) * 12000),
                'cost' => 65000 + (($index % 2) * 9000),
                'business_division' => 'first_business',
            ]),
        ];

        $this->upsertEstimate([
            'estimate_number' => sprintf('%s-%s-SAL-L', self::ESTIMATE_NUMBER_PREFIX, $month->format('Ym')),
            'title' => sprintf('[DEMO-DASHBOARD] %s月 販売失注案件', $month->format('Y年n')),
            'customer_name' => (string) $customer['name'],
            'client_id' => (string) $customer['mf_partner_id'],
            'staff_name' => (string) $staff['name'],
            'staff_id' => (int) $staff['id'],
            'issue_date' => $issueDate,
            'due_date' => $dueDate,
            'delivery_date' => $deliveryDate,
            'status' => 'lost',
            'is_order_confirmed' => false,
            'approval_started' => true,
            'approval_flow' => $this->buildApprovalFlow((int) $staff['id'], (string) $staff['name'], 'approved', $issueDate),
            'lost_at' => $lostAt,
            'lost_reason' => $lostReason,
            'lost_note' => sprintf('%sのため今月は見送り。次回提案余地あり。', $lostReason),
            'items' => $items,
            'notes' => '失注パターン確認用ダミーデータです。',
            'internal_memo' => sprintf('seed:%s:sales_lost', self::INTERNAL_MEMO_TAG),
        ]);
    }

    private function seedDraftEstimate(Carbon $month, int $index): void
    {
        $staff = $this->resolveStaff($index + 15);
        $customer = $this->resolvePartner($index + 41);
        $draftType = $index % 2 === 0 ? '追加提案' : '小規模改善';
        $issueDate = $month->copy()->day(10);
        $dueDate = $month->copy()->endOfMonth()->addDays(10);
        $deliveryDate = $month->copy()->endOfMonth()->addDays(20);

        $items = [
            $this->buildProductItem('A-001', [
                'description' => $draftType . 'のたたき台',
                'qty' => 1.5 + (($index % 3) * 0.5),
                'price' => 68000 + (($index % 4) * 2500),
                'cost' => 24000 + (($index % 3) * 1200),
                'business_division' => 'fifth_business',
                'assignees' => $this->buildAssigneesFromShares($index + 15, [100.0]),
            ]),
        ];

        $this->upsertEstimate([
            'estimate_number' => sprintf('%s-%s-DRF', self::ESTIMATE_NUMBER_PREFIX, $month->format('Ym')),
            'title' => sprintf('[DEMO-DASHBOARD] %s月 %sドラフト', $month->format('Y年n'), $draftType),
            'customer_name' => (string) $customer['name'],
            'client_id' => (string) $customer['mf_partner_id'],
            'staff_name' => (string) $staff['name'],
            'staff_id' => (int) $staff['id'],
            'issue_date' => $issueDate,
            'due_date' => $dueDate,
            'delivery_date' => $deliveryDate,
            'status' => 'draft',
            'is_order_confirmed' => false,
            'approval_started' => false,
            'approval_flow' => null,
            'items' => $items,
            'notes' => 'ドラフト状態の見積確認用ダミーデータです。',
            'internal_memo' => sprintf('seed:%s:draft', self::INTERNAL_MEMO_TAG),
        ]);
    }

    private function upsertEstimate(array $payload): void
    {
        $items = $payload['items'] ?? [];
        $subtotal = collect($items)->sum(function (array $item): float {
            return (float) ($item['qty'] ?? 0) * (float) ($item['price'] ?? 0);
        });

        Estimate::query()->updateOrCreate(
            ['estimate_number' => $payload['estimate_number']],
            [
                'customer_name' => $payload['customer_name'],
                'client_id' => $payload['client_id'],
                'mf_department_id' => null,
                'title' => $payload['title'],
                'issue_date' => $payload['issue_date']?->toDateString(),
                'due_date' => $payload['due_date']?->toDateString(),
                'follow_up_due_date' => ($payload['follow_up_due_date'] ?? null)?->toDateString(),
                'delivery_date' => $payload['delivery_date']?->toDateString(),
                'lost_at' => ($payload['lost_at'] ?? null)?->toDateString(),
                'overdue_prompted_at' => ($payload['overdue_prompted_at'] ?? null)?->toDateTimeString(),
                'status' => $payload['status'],
                'lost_reason' => $payload['lost_reason'] ?? null,
                'lost_note' => $payload['lost_note'] ?? null,
                'overdue_decision_note' => $payload['overdue_decision_note'] ?? null,
                'total_amount' => round($subtotal),
                'tax_amount' => round($subtotal * 0.1),
                'notes' => $payload['notes'] ?? 'ローカル確認用ダミーデータです。',
                'items' => $items,
                'estimate_number' => $payload['estimate_number'],
                'staff_id' => $payload['staff_id'],
                'staff_name' => $payload['staff_name'],
                'approval_flow' => $payload['approval_flow'] ?? null,
                'approval_started' => $payload['approval_started'] ?? false,
                'internal_memo' => $payload['internal_memo'] ?? ('seed:' . self::INTERNAL_MEMO_TAG),
                'mf_quote_id' => $payload['mf_quote_id'] ?? null,
                'mf_quote_pdf_url' => $payload['mf_quote_pdf_url'] ?? null,
                'is_order_confirmed' => $payload['is_order_confirmed'] ?? false,
            ]
        );
    }

    private function buildApprovalFlow(int $staffId, string $staffName, string $mode, Carbon $baseDate): ?array
    {
        if ($mode === 'none') {
            return null;
        }

        $primaryApprover = $this->resolveStaff($staffId);
        $secondaryApprover = $this->resolveStaff($staffId + 1);
        $isApproved = $mode === 'approved';

        return [
            [
                'id' => $primaryApprover['id'],
                'name' => $primaryApprover['name'],
                'approved_at' => $isApproved ? $baseDate->copy()->addDays(1)->setTime(10, 0)->toDateTimeString() : null,
                'status' => $isApproved ? 'approved' : 'pending',
            ],
            [
                'id' => $secondaryApprover['id'],
                'name' => $secondaryApprover['name'] ?: ($staffName . ' マネージャー'),
                'approved_at' => $isApproved ? $baseDate->copy()->addDays(2)->setTime(15, 0)->toDateTimeString() : null,
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
