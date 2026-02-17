<?php

namespace App\Console\Commands;

use App\Models\Estimate;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\Console\Attribute\AsCommand;

#[AsCommand(name: 'estimates:backfill-project-id')]
class BackfillEstimateProjectIds extends Command
{
    protected $signature = 'estimates:backfill-project-id
        {--apply : 実際に見積へ xero_project_id を更新する}
        {--limit=0 : 処理件数上限(0は無制限)}
        {--only-confirmed=1 : 受注確定済みのみ対象(1/0)}
        {--output= : CSV出力先(storage/app配下)}';

    protected $description = '既存見積の xero_project_id を候補突合で一括補完する';

    public function handle(): int
    {
        $apply = (bool) $this->option('apply');
        $limit = max(0, (int) $this->option('limit'));
        $onlyConfirmed = ((string) $this->option('only-confirmed')) !== '0';

        $projects = $this->fetchProjects();
        if ($projects->isEmpty()) {
            $this->error('プロジェクト一覧を取得できませんでした。XERO_PM_API_BASE / XERO_PM_API_TOKEN を確認してください。');
            return Command::FAILURE;
        }

        $query = Estimate::query()
            ->whereNull('mf_deleted_at')
            ->where(function ($q) {
                $q->whereNull('xero_project_id')
                    ->orWhere('xero_project_id', '');
            })
            ->orderBy('id');

        if ($onlyConfirmed) {
            $query->where('is_order_confirmed', true);
        }

        if ($limit > 0) {
            $query->limit($limit);
        }

        $estimates = $query->get([
            'id',
            'estimate_number',
            'client_id',
            'customer_name',
            'title',
            'delivery_date',
            'xero_project_name',
            'is_order_confirmed',
        ]);

        if ($estimates->isEmpty()) {
            $this->warn('対象見積がありません。');
            return Command::SUCCESS;
        }

        $this->info('対象見積: ' . $estimates->count() . '件');
        $this->info('適用モード: ' . ($apply ? 'ON' : 'DRY-RUN'));

        $rows = [];
        $autoLinked = 0;
        $reviewRequired = 0;
        $unmatched = 0;

        foreach ($estimates as $estimate) {
            $judged = $this->judgeCandidate($estimate, $projects);
            $status = $judged['status'];
            $best = $judged['best'];

            if ($status === 'AUTO_LINKED') {
                $autoLinked++;
                if ($apply && $best) {
                    $estimate->xero_project_id = $best['id'];
                    if (empty($estimate->xero_project_name)) {
                        $estimate->xero_project_name = $best['name'];
                    }
                    $estimate->save();
                }
            } elseif ($status === 'REVIEW_REQUIRED') {
                $reviewRequired++;
            } else {
                $unmatched++;
            }

            $rows[] = [
                'status' => $status,
                'estimate_id' => (string) $estimate->id,
                'estimate_number' => (string) ($estimate->estimate_number ?? ''),
                'client_id' => (string) ($estimate->client_id ?? ''),
                'title' => (string) ($estimate->title ?? ''),
                'delivery_date' => (string) optional($estimate->delivery_date)->toDateString(),
                'matched_project_id' => (string) ($best['id'] ?? ''),
                'matched_project_name' => (string) ($best['name'] ?? ''),
                'matched_customer_id' => (string) ($best['customer_id'] ?? ''),
                'rule' => (string) ($best['rule'] ?? ''),
                'score' => isset($best['score']) ? (string) $best['score'] : '',
                'candidate_count' => (string) ($judged['candidate_count'] ?? 0),
            ];
        }

        $path = $this->writeCsv($rows);

        $this->info('AUTO_LINKED: ' . $autoLinked . '件');
        $this->info('REVIEW_REQUIRED: ' . $reviewRequired . '件');
        $this->info('UNMATCHED: ' . $unmatched . '件');
        $this->info('CSV: storage/app/' . $path);

        return Command::SUCCESS;
    }

    private function fetchProjects(): Collection
    {
        $token = (string) (env('XERO_PM_API_TOKEN') ?: env('EXTERNAL_API_TOKEN') ?: '');
        if ($token === '') {
            return collect();
        }

        $base = rtrim((string) env('XERO_PM_API_BASE', 'https://api.xerographix.co.jp/api'), '/');
        $response = Http::withHeaders([
            'Accept' => 'application/json',
            'Authorization' => 'Bearer ' . $token,
        ])->get($base . '/projects');

        if (!$response->successful()) {
            return collect();
        }

        $payload = $response->json();
        if (!is_array($payload)) {
            return collect();
        }

        return collect($payload)->map(function (array $p) {
            $startDate = $p['start_date'] ?? $p['started_at'] ?? null;
            $endDate = $p['end_date'] ?? $p['delivery_date'] ?? $p['due_date'] ?? null;

            return [
                'id' => (string) ($p['id'] ?? ''),
                'name' => trim((string) ($p['name'] ?? '')),
                'name_key' => $this->normalizeText($p['name'] ?? ''),
                'customer_id' => (string) ($p['customer_id'] ?? data_get($p, 'customer.id') ?? ''),
                'start_date' => $this->normalizeDate($startDate),
                'end_date' => $this->normalizeDate($endDate),
                'is_active' => (bool) ($p['is_active'] ?? true),
            ];
        })->filter(function (array $p) {
            return $p['id'] !== '' && $p['name'] !== '';
        })->values();
    }

    private function judgeCandidate(Estimate $estimate, Collection $projects): array
    {
        $customerId = (string) ($estimate->client_id ?? '');
        $titleKey = $this->normalizeText($estimate->title ?? '');
        $projectNameKey = $this->normalizeText($estimate->xero_project_name ?? '');
        $deliveryDate = $estimate->delivery_date ? Carbon::parse($estimate->delivery_date)->toDateString() : null;

        $candidates = collect();

        if ($projectNameKey !== '') {
            $matchedByProjectName = $projects->filter(function (array $project) use ($projectNameKey, $customerId) {
                if ($project['name_key'] !== $projectNameKey) {
                    return false;
                }
                if ($customerId !== '' && $project['customer_id'] !== '' && $project['customer_id'] !== $customerId) {
                    return false;
                }
                return true;
            })->map(fn (array $project) => array_merge($project, [
                'score' => 100,
                'rule' => 'project_name_exact',
            ]));

            $candidates = $candidates->merge($matchedByProjectName);
        }

        if ($titleKey !== '') {
            $matchedByTitle = $projects->filter(function (array $project) use ($titleKey) {
                return $project['name_key'] === $titleKey;
            })->map(function (array $project) use ($customerId, $deliveryDate) {
                $score = 70;
                $rule = 'title_exact';

                if ($customerId !== '' && $project['customer_id'] !== '' && $project['customer_id'] === $customerId) {
                    $score = 90;
                    $rule = 'title_exact_customer_exact';
                }

                if ($deliveryDate !== null && $this->isWithinProjectRange($deliveryDate, $project['start_date'], $project['end_date'])) {
                    $score += 5;
                    $rule .= '_delivery_in_range';
                }

                return array_merge($project, [
                    'score' => $score,
                    'rule' => $rule,
                ]);
            });

            $candidates = $candidates->merge($matchedByTitle);
        }

        if ($candidates->isEmpty()) {
            return [
                'status' => 'UNMATCHED',
                'best' => null,
                'candidate_count' => 0,
            ];
        }

        $deduped = $candidates->sortByDesc('score')
            ->unique('id')
            ->values();

        $best = $deduped->first();
        $second = $deduped->skip(1)->first();

        $isAuto = $best
            && (int) ($best['score'] ?? 0) >= 90
            && (!$second || (int) ($second['score'] ?? 0) < (int) ($best['score'] ?? 0));

        return [
            'status' => $isAuto ? 'AUTO_LINKED' : 'REVIEW_REQUIRED',
            'best' => $best,
            'candidate_count' => $deduped->count(),
        ];
    }

    private function isWithinProjectRange(string $deliveryDate, ?string $startDate, ?string $endDate): bool
    {
        if ($startDate === null && $endDate === null) {
            return false;
        }

        $target = Carbon::parse($deliveryDate)->startOfDay();
        $start = $startDate ? Carbon::parse($startDate)->startOfDay() : null;
        $end = $endDate ? Carbon::parse($endDate)->endOfDay() : null;

        if ($start && $target->lt($start)) {
            return false;
        }
        if ($end && $target->gt($end)) {
            return false;
        }

        return true;
    }

    private function normalizeDate(mixed $value): ?string
    {
        if (!$value) {
            return null;
        }

        try {
            return Carbon::parse((string) $value)->toDateString();
        } catch (\Throwable $e) {
            return null;
        }
    }

    private function normalizeText(mixed $value): string
    {
        $text = trim((string) $value);
        if ($text === '') {
            return '';
        }

        $text = preg_replace('/\s+/u', ' ', $text) ?: $text;
        return mb_strtolower($text);
    }

    private function writeCsv(array $rows): string
    {
        $path = (string) $this->option('output');
        if ($path === '') {
            $path = 'reports/estimate_project_backfill_' . now()->format('Ymd_His') . '.csv';
        }

        $dir = dirname($path);
        if ($dir !== '.' && !Storage::exists($dir)) {
            Storage::makeDirectory($dir);
        }

        $stream = fopen('php://temp', 'w+');
        if ($stream === false) {
            return $path;
        }

        $headers = [
            'status',
            'estimate_id',
            'estimate_number',
            'client_id',
            'title',
            'delivery_date',
            'matched_project_id',
            'matched_project_name',
            'matched_customer_id',
            'rule',
            'score',
            'candidate_count',
        ];

        fputcsv($stream, $headers);
        foreach ($rows as $row) {
            $line = [];
            foreach ($headers as $header) {
                $line[] = $row[$header] ?? '';
            }
            fputcsv($stream, $line);
        }

        rewind($stream);
        $contents = stream_get_contents($stream) ?: '';
        fclose($stream);

        Storage::put($path, $contents);

        return $path;
    }
}
