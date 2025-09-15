<?php

namespace Database\Seeders;

use App\Models\Estimate;
use App\Models\Product;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Faker\Factory as Faker;
use Carbon\Carbon;

class QuoteSeeder extends Seeder
{
    public function run(): void
    {
        $faker = Faker::create('ja_JP');
        $users = User::all();
        $products = Product::all();

        if ($users->isEmpty() || $products->isEmpty()) {
            $this->command->warn('Skipping QuoteSeeder: No users or products found.');
            return;
        }

        // 承認申請前 (draft)
        $this->createQuote($faker, $users, $products, 'draft');

        // 承認途中 (pending)
        $this->createQuote($faker, $users, $products, 'pending', true);

        // 承認済み (sent)
        $this->createQuote($faker, $users, $products, 'sent', true, true);
    }

    private function createQuote($faker, $users, $products, $status, $withApprovalFlow = false, $isApproved = false): void
    {
        $staff = $users->random();
        $customerName = $faker->company;
        $clientId = $faker->uuid; // Dummy client_id for MF partner
        $mfDepartmentId = $faker->uuid; // Dummy mf_department_id

        $itemsData = [];
        $totalAmount = 0;
        $taxAmount = 0;

        $numItems = $faker->numberBetween(1, 3);
        for ($i = 0; $i < $numItems; $i++) {
            $product = $products->random();
            $qty = $faker->numberBetween(1, 10);
            $price = $product->price;
            $cost = $product->cost;
            $itemTotal = $qty * $price;
            $itemTax = $itemTotal * 0.1; // Assuming 10% tax for simplicity

            $itemsData[] = [
                'product_id' => $product->id,
                'name' => $product->name,
                'description' => $product->description,
                'qty' => $qty,
                'unit' => $product->unit,
                'price' => $price,
                'cost' => $cost,
                'tax_category' => $product->tax_category,
            ];
            $totalAmount += $itemTotal;
            $taxAmount += $itemTax;
        }

        $approvalFlow = [];
        if ($withApprovalFlow) {
            $approver1 = $users->random();
            $approver2 = $users->random();
            $approvalFlow[] = [
                'id' => $approver1->id,
                'name' => $approver1->name,
                'approved_at' => $isApproved ? Carbon::now()->subDays(1)->toDateTimeString() : null,
                'status' => $isApproved ? 'approved' : 'pending',
            ];
            if ($faker->boolean(70)) { // 70% chance for a second approver
                $approvalFlow[] = [
                    'id' => $approver2->id,
                    'name' => $approver2->name,
                    'approved_at' => $isApproved ? Carbon::now()->toDateTimeString() : null,
                    'status' => $isApproved ? 'approved' : 'pending',
                ];
            }
        }

        Estimate::create([
            'customer_name' => $customerName,
            'client_id' => $clientId,
            'mf_department_id' => $mfDepartmentId,
            'title' => $faker->sentence(3),
            'issue_date' => $faker->date(),
            'due_date' => $faker->date(),
            'total_amount' => round($totalAmount),
            'tax_amount' => round($taxAmount),
            'notes' => $faker->paragraph(1),
            'internal_memo' => $faker->paragraph(1),
            'delivery_location' => $faker->address,
            'items' => $itemsData,
            'estimate_number' => Estimate::generateReadableEstimateNumber($staff->id, $clientId, $status === 'draft'),
            'staff_id' => $staff->id,
            'staff_name' => $staff->name,
            'approval_flow' => $approvalFlow,
            'status' => $status,
            'approval_started' => ($status === 'pending' || $status === 'sent'),
        ]);
    }
}
