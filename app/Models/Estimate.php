<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Services\RequirementDocumentChecker;
use Illuminate\Support\Facades\Schema;

class Estimate extends Model
{
    use HasFactory;

    protected $appends = [
        'requires_requirement_doc',
    ];

    /**
     * Normalize stored notes by stripping trailing whitespace on each line.
     */
    public function setNotesAttribute($value): void
    {
        $this->attributes['notes'] = $this->sanitizeMultilineText($value);
    }

    protected $fillable = [
        'customer_name',
        'client_contact_name',
        'client_contact_title',
        'client_id',
        'xero_project_id',
        'xero_project_name',
        'mf_department_id',
        'title',
        'issue_date',
        'due_date',
        'delivery_date',
        'status',
        'total_amount',
        'tax_amount',
        'notes',
        'items',
        'estimate_number',
        'staff_id',
        'staff_name',
        'approval_flow',
        'approval_started',
        'internal_memo',
        'requirement_summary',
        'google_docs_url',
        'structured_requirements',
        'delivery_location',
        'mf_quote_id',
        'mf_quote_pdf_url',
        'mf_invoice_id',
        'mf_invoice_pdf_url',
        'mf_deleted_at',
        'is_order_confirmed',
    ];

    protected $casts = [
        'items' => 'array',
        'issue_date' => 'date',
        'due_date' => 'date',
        'delivery_date' => 'date',
        'approval_flow' => 'array',
        'approval_started' => 'boolean',
        'mf_deleted_at' => 'datetime',
        'is_order_confirmed' => 'boolean',
        'structured_requirements' => 'array',
    ];

    // No local FK relation for staff; staff_id refers to external directory

    public static function generateReadableEstimateNumber($staffId, $clientId, bool $is_draft): string
    {
        // Spec: EST[-D]-{staff}-{client}-{yyddmm}-{seq}
        $date = now()->format('ydm'); // yy dd mm
        $staff = $staffId ?: 'X';

        // Prefer short partner code from DB; fallback to leading 6 of partner_id
        $client = 'X';
        if (!empty($clientId)) {
            $client = (string)$clientId;
            if (Schema::hasTable('partners')) {
                try {
                    $code = \DB::table('partners')->where('mf_partner_id', (string)$clientId)->value('code');
                    if (!empty($code)) { $client = $code; }
                } catch (\Throwable $e) {}
            }
        }

        $client = self::sanitizeClientCode($client);

        if (strlen($client) > 12) {
            $client = substr($client, 0, 6);
        }
        $kind = $is_draft ? 'EST-D' : 'EST';
        $prefix = "$kind-$staff-$client-$date-";

        $latest = self::where('estimate_number', 'like', $prefix . '%')
            ->orderBy('estimate_number', 'desc')
            ->first();

        $seq = 1;
        if ($latest) {
            $tail = substr($latest->estimate_number, strlen($prefix));
            $num = (int) $tail;
            $seq = $num + 1;
        }

        return $prefix . str_pad((string) $seq, 3, '0', STR_PAD_LEFT);
    }

    private static function sanitizeClientCode(string $code): string
    {
        if ($code === '') {
            return 'X';
        }

        $sanitized = str_replace('CRM', '', $code);
        $sanitized = ltrim($sanitized, '-_');

        return $sanitized === '' ? 'X' : $sanitized;
    }

    private function sanitizeMultilineText($value): ?string
    {
        if ($value === null) {
            return null;
        }

        $text = str_replace(["\r\n", "\r"], "\n", (string) $value);
        $lines = explode("\n", $text);
        $lines = array_map(static fn($line) => rtrim($line, " \t"), $lines);
        $normalized = implode("\n", $lines);

        return trim($normalized) === '' ? null : $normalized;
    }

    public function getRequiresRequirementDocAttribute(): bool
    {
        $checker = app(RequirementDocumentChecker::class);
        return $checker->requiresDesignOrDevelopmentAttachment($this->items ?? []);
    }
}
