<?php

namespace Database\Factories;

use App\Models\Estimate;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<\App\Models\Estimate>
 */
class EstimateFactory extends Factory
{
    protected $model = Estimate::class;

    public function definition(): array
    {
        $issueDate = $this->faker->dateTimeBetween('-1 month', 'now');
        $dueDate = (clone $issueDate)->modify('+14 days');

        return [
            'customer_name' => $this->faker->company(),
            'client_contact_name' => $this->faker->name(),
            'client_contact_title' => '御中',
            'client_id' => (string) $this->faker->randomNumber(5),
            'mf_department_id' => (string) $this->faker->randomNumber(5),
            'title' => $this->faker->sentence(3),
            'issue_date' => $issueDate,
            'due_date' => $dueDate,
            'status' => 'draft',
            'total_amount' => 55000,
            'tax_amount' => 5000,
            'notes' => '納期遵守と品質保証をお願いいたします。',
            'items' => [
                [
                    'name' => 'システム開発一式',
                    'description' => '要件定義から結合テストまで',
                    'qty' => 1,
                    'unit' => '式',
                    'price' => 50000,
                    'tax_category' => 'standard',
                ],
                [
                    'name' => '保守サポート',
                    'description' => 'リリース後3ヶ月対応',
                    'qty' => 1,
                    'unit' => '式',
                    'price' => 5000,
                    'tax_category' => 'standard',
                ],
            ],
            'estimate_number' => 'EST-' . Str::upper(Str::random(6)),
            'staff_id' => $this->faker->numberBetween(1000, 9999),
            'staff_name' => $this->faker->name(),
            'approval_flow' => [],
        ];
    }

    public function approved(): self
    {
        return $this->state(fn () => ['status' => 'sent']);
    }
}
