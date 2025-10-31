<?php

namespace Tests\Feature;

use App\Models\Estimate;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EstimateDuplicateTest extends TestCase
{
    use RefreshDatabase;

    public function testDuplicatingApprovedEstimateResetsApprovalAndIncrementsNumber(): void
    {
        $user = User::factory()->create();

        $staffId = 1234;
        $clientId = 'CLIENT-1';
        $originalNumber = Estimate::generateReadableEstimateNumber($staffId, $clientId, false);

        $estimate = Estimate::factory()->create([
            'status' => 'sent',
            'staff_id' => $staffId,
            'client_id' => $clientId,
            'estimate_number' => $originalNumber,
            'approval_flow' => [
                [
                    'id' => 'approver-1',
                    'name' => '承認者A',
                    'status' => 'approved',
                    'approved_at' => now()->toDateTimeString(),
                ],
            ],
            'approval_started' => false,
            'mf_quote_id' => 'mf-quote-1',
            'mf_quote_pdf_url' => 'https://example.com/quote.pdf',
            'mf_invoice_id' => 'mf-invoice-1',
            'mf_invoice_pdf_url' => 'https://example.com/invoice.pdf',
            'mf_deleted_at' => null,
        ]);

        $response = $this->actingAs($user)->post(route('estimates.duplicate', $estimate));
        $response->assertRedirect();

        $newEstimate = Estimate::where('id', '!=', $estimate->id)->latest('id')->first();
        $this->assertNotNull($newEstimate);

        $this->assertSame('draft', $newEstimate->status);
        $this->assertSame([], $newEstimate->approval_flow);
        $this->assertFalse($newEstimate->approval_started);
        $this->assertNull($newEstimate->mf_quote_id);
        $this->assertNull($newEstimate->mf_quote_pdf_url);
        $this->assertNull($newEstimate->mf_invoice_id);
        $this->assertNull($newEstimate->mf_invoice_pdf_url);
        $this->assertNull($newEstimate->mf_deleted_at);
        $this->assertNotSame($estimate->estimate_number, $newEstimate->estimate_number);
        $this->assertStringStartsWith('EST-', $newEstimate->estimate_number);

        $this->assertSame(
            $this->extractSequence($estimate->estimate_number) + 1,
            $this->extractSequence($newEstimate->estimate_number)
        );
    }

    public function testDuplicatingDraftEstimateUsesDraftSequence(): void
    {
        $user = User::factory()->create();

        $staffId = 9876;
        $clientId = 'CLIENT-2';
        $originalNumber = Estimate::generateReadableEstimateNumber($staffId, $clientId, true);

        $estimate = Estimate::factory()->create([
            'status' => 'draft',
            'staff_id' => $staffId,
            'client_id' => $clientId,
            'estimate_number' => $originalNumber,
            'approval_flow' => [
                [
                    'id' => 'approver-2',
                    'name' => '承認者B',
                    'status' => 'pending',
                    'approved_at' => null,
                ],
            ],
            'approval_started' => true,
        ]);

        $response = $this->actingAs($user)->post(route('estimates.duplicate', $estimate));
        $response->assertRedirect();

        $newEstimate = Estimate::where('id', '!=', $estimate->id)->latest('id')->first();
        $this->assertNotNull($newEstimate);

        $this->assertSame('draft', $newEstimate->status);
        $this->assertSame([], $newEstimate->approval_flow);
        $this->assertFalse($newEstimate->approval_started);
        $this->assertNull($newEstimate->mf_quote_id);
        $this->assertStringStartsWith('EST-D-', $newEstimate->estimate_number);
        $this->assertNotSame($estimate->estimate_number, $newEstimate->estimate_number);

        $this->assertSame(
            $this->extractSequence($estimate->estimate_number) + 1,
            $this->extractSequence($newEstimate->estimate_number)
        );
    }

    private function extractSequence(string $estimateNumber): int
    {
        if (preg_match('/(\d{3})$/', $estimateNumber, $matches)) {
            return (int) $matches[1];
        }

        $this->fail('Estimate number does not end with a three digit sequence: ' . $estimateNumber);
    }
}
