<?php

namespace Tests\Feature;

use App\Models\Estimate;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class PurchaseOrderPreviewTest extends TestCase
{
    use RefreshDatabase;

    public function test_only_sent_estimate_can_be_previewed(): void
    {
        $user = User::factory()->create();
        $estimate = Estimate::factory()->create(['status' => 'pending']);

        $response = $this->actingAs($user)->get(route('estimates.purchaseOrder.preview', $estimate));

        $response->assertForbidden();
    }

    public function test_preview_renders_inertia_page(): void
    {
        $user = User::factory()->create();
        $estimate = Estimate::factory()->create([
            'status' => 'sent',
            'estimate_number' => 'EST-001',
        ]);

        $response = $this->actingAs($user)->get(route('estimates.purchaseOrder.preview', $estimate));

        $response->assertOk();
        $response->assertInertia(function (AssertableInertia $page) use ($estimate) {
            $page->component('Estimates/PurchaseOrderPreview')
                ->where('purchaseOrderNumber', 'PO-' . $estimate->estimate_number)
                ->has('estimate')
                ->has('company')
                ->has('client', function (AssertableInertia $client) use ($estimate) {
                    $client->where('name', $estimate->customer_name ?? '')
                        ->etc();
                });
        });
    }
}
