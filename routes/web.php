<?php

use App\Http\Controllers\ProfileController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use App\Models\Estimate; // Add this import
use Illuminate\Support\Facades\Auth; // Add this import

Route::get('/', function () {
    return redirect()->route('login');
});

Route::get('/dashboard', function () {
    $user = Auth::user();
    // Show tasks purely based on approval_flow (未承認が存在するもの)。status には依存しない。
    $estimatesWithFlow = Estimate::whereNotNull('approval_flow')->get();
    $toDoEstimates = [];

    foreach ($estimatesWithFlow as $estimate) {
        // approval_flow is already cast to array by Eloquent; avoid json_decode on array
        $approvalFlow = is_array($estimate->approval_flow)
            ? $estimate->approval_flow
            : json_decode($estimate->approval_flow, true);
        if (!is_array($approvalFlow)) {
            continue; // Skip if approval_flow is not a valid array
        }

        $isCurrentUserInFlow = false;
        $isCurrentUserNextApprover = false;
        $waitingForApproverName = null;
        $authId = (int) ($user->id ?? 0);
        $authExternalId = (string) ($user->external_user_id ?? '');

        $currentStepIndex = -1;
        foreach ($approvalFlow as $idx => $approver) {
            if (!isset($approver['approved_at'])) {
                $currentStepIndex = $idx;
                break;
            }
        }

        if ($currentStepIndex !== -1) {
            $currentApprover = $approvalFlow[$currentStepIndex];

            $approverIdInFlow = (string)($currentApprover['id'] ?? '');
            $authIdStr = (string)($user->id ?? '');
            $authExternalIdStr = (string)($user->external_user_id ?? '');

            if ($approverIdInFlow !== '') {
                if ($approverIdInFlow === $authIdStr) {
                    $isCurrentUserNextApprover = true;
                } elseif ($authExternalIdStr !== '' && $approverIdInFlow === $authExternalIdStr) {
                    $isCurrentUserNextApprover = true;
                }
            }

            if ($isCurrentUserNextApprover) {
                // no-op
            } else {
                $waitingForApproverName = $currentApprover['name'];
            }
        } else {
            // All steps approved → ダッシュボード対象外
            continue;
        }

        // Check if current user is in the flow at all
        foreach ($approvalFlow as $approver) {
            $approverIdInFlow = (string)($approver['id'] ?? '');
            $authIdStr = (string)($user->id ?? '');
            $authExternalIdStr = (string)($user->external_user_id ?? '');

            if ($approverIdInFlow !== '') {
                if ($approverIdInFlow === $authIdStr) {
                    $isCurrentUserInFlow = true;
                    break;
                } elseif ($authExternalIdStr !== '' && $approverIdInFlow === $authExternalIdStr) {
                    $isCurrentUserInFlow = true;
                    break;
                }
            }
        }

        if ($isCurrentUserNextApprover) {
            $toDoEstimates[] = [
                'id' => $estimate->id,
                'title' => $estimate->title,
                'issue_date' => $estimate->issue_date,
                'status_for_dashboard' => '確認して承認',
                'estimate_number' => $estimate->estimate_number,
                // Provide full estimate payload for the modal (no new API)
                'estimate' => [
                    'id' => $estimate->id,
                    'estimate_number' => $estimate->estimate_number,
                    'title' => $estimate->title,
                    'customer_name' => $estimate->customer_name,
                    'status' => $estimate->status,
                    'items' => $estimate->items,
                    'tax_amount' => $estimate->tax_amount,
                    'total_amount' => $estimate->total_amount,
                    'approval_flow' => $approvalFlow,
                    'staff_name' => $estimate->staff_name,
                    'issue_date' => $estimate->issue_date,
                    'due_date' => $estimate->due_date,
                ],
            ];
        } elseif ($isCurrentUserInFlow && $waitingForApproverName) {
            $toDoEstimates[] = [
                'id' => $estimate->id,
                'title' => $estimate->title,
                'issue_date' => $estimate->issue_date,
                'status_for_dashboard' => "{$waitingForApproverName}さんの承認待ち",
                'estimate_number' => $estimate->estimate_number,
                'estimate' => [
                    'id' => $estimate->id,
                    'estimate_number' => $estimate->estimate_number,
                    'title' => $estimate->title,
                    'customer_name' => $estimate->customer_name,
                    'status' => $estimate->status,
                    'items' => $estimate->items,
                    'tax_amount' => $estimate->tax_amount,
                    'total_amount' => $estimate->total_amount,
                    'approval_flow' => $approvalFlow,
                    'staff_name' => $estimate->staff_name,
                    'issue_date' => $estimate->issue_date,
                    'due_date' => $estimate->due_date,
                ],
            ];
        }
    }

    usort($toDoEstimates, function($a, $b) {
        return strtotime($b['issue_date']) - strtotime($a['issue_date']);
    });

    return Inertia::render('Dashboard', [
        'toDoEstimates' => $toDoEstimates,
    ]);
})->middleware(['auth', 'verified'])->name('dashboard');

// Guest-accessible API endpoints for login page user/customer lookup
Route::get('/api/customers', [App\Http\Controllers\ApiController::class, 'getCustomers']);
Route::get('/api/users', [App\Http\Controllers\ApiController::class, 'getUsers']);

Route::middleware('auth')->group(function () {
    // Static route first to avoid conflict with /estimates/{estimate}
    Route::post('/estimates/draft', [App\Http\Controllers\EstimateController::class, 'saveDraft'])->name('estimates.saveDraft');

    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    Route::get('/sales', fn () => Inertia::render('Sales/Index'))->name('sales.index');
    Route::get('/deposits', fn () => Inertia::render('Deposits/Index'))->name('deposits.index');
    Route::get('/billing', fn () => Inertia::render('Billing/Index'))->name('billing.index');
    Route::get('/inventory', fn () => Inertia::render('Inventory/Index'))->name('inventory.index');
    Route::get('/products', fn () => Inertia::render('Products/Index'))->name('products.index');

    Route::get('/quotes', [App\Http\Controllers\EstimateController::class, 'index'])->name('quotes.index');

    Route::post('/estimates/preview-pdf', [App\Http\Controllers\EstimateController::class, 'previewPdf'])->name('estimates.previewPdf');
    Route::post('/estimates', [App\Http\Controllers\EstimateController::class, 'store'])->name('estimates.store');
    Route::post('/estimates/{estimate}', [App\Http\Controllers\EstimateController::class, 'update'])->whereNumber('estimate')->name('estimates.update');
    Route::patch('/estimates/{estimate}', [App\Http\Controllers\EstimateController::class, 'update'])->whereNumber('estimate');
    Route::delete('/estimates/{estimate}', [App\Http\Controllers\EstimateController::class, 'destroy'])->whereNumber('estimate')->name('estimates.destroy');
    Route::post('/estimates/bulk-approve', [App\Http\Controllers\EstimateController::class, 'bulkApprove'])->name('estimates.bulkApprove');
    Route::post('/estimates/bulk-reassign', [App\Http\Controllers\EstimateController::class, 'bulkReassign'])->name('estimates.bulkReassign');

    // Approve current step in approval flow
    Route::put('/estimates/{estimate}/approval', [App\Http\Controllers\EstimateController::class, 'updateApproval'])
        ->whereNumber('estimate')
        ->name('estimates.updateApproval');

    Route::get('/estimates/create', [App\Http\Controllers\EstimateController::class, 'create'])->name('estimates.create');
    Route::get('/estimates/{estimate}/edit', [App\Http\Controllers\EstimateController::class, 'edit'])->whereNumber('estimate')->name('estimates.edit');
    Route::post('/estimates/{estimate}/duplicate', [App\Http\Controllers\EstimateController::class, 'duplicate'])->whereNumber('estimate')->name('estimates.duplicate');

    // API routes moved outside auth for login page access

    Route::get('/admin', fn () => Inertia::render('Admin/Index'))->name('admin.index');
});

require __DIR__.'/auth.php';
