<?php

use App\Http\Controllers\ProfileController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use App\Models\Estimate; // Add this import
use Illuminate\Support\Facades\Auth; // Add this import
use App\Http\Controllers\BillingController;

Route::get('/', function () {
    return redirect()->route('login');
});

Route::get('/dashboard', function () {
    $user = Auth::user();
    // Show tasks purely based on approval_flow (未承認が存在するもの)。status には依存しない。
    $estimatesWithFlow = Estimate::whereNotNull('approval_flow')->get();
    $toDoEstimates = [];

    foreach ($estimatesWithFlow as $estimate) {
        $approvalFlow = is_array($estimate->approval_flow)
            ? $estimate->approval_flow
            : json_decode($estimate->approval_flow, true);
        if (!is_array($approvalFlow) || empty($approvalFlow)) {
            continue; // Skip if approval_flow is not a valid array or empty
        }

        $isCurrentUserNextApprover = false;
        $waitingForApproverName = null;
        $isCurrentUserInFlow = false;

        // Check if current user is in the flow at all
        foreach ($approvalFlow as $approver) {
            $approverIdInFlow = $approver['id'] ?? null;
            $matchesLocalId = is_numeric($approverIdInFlow) && (int)$approverIdInFlow === (int)$user->id;
            $approverIdInFlowStr = is_null($approverIdInFlow) ? '' : (string)$approverIdInFlow;
            $userExt = (string)($user->external_user_id ?? '');
            $matchesExternalId = ($approverIdInFlowStr !== '') && ($userExt !== '') && ($approverIdInFlowStr === $userExt);

            if ($matchesLocalId || $matchesExternalId) {
                $isCurrentUserInFlow = true;
                break;
            }
        }

        $currentStepIndex = -1;
        foreach ($approvalFlow as $idx => $approver) {
            if (empty($approver['approved_at'])) {
                $currentStepIndex = $idx;
                break;
            }
        }

        if ($currentStepIndex !== -1) {
            $currentApprover = $approvalFlow[$currentStepIndex];

            $approverIdInFlow = $currentApprover['id'] ?? null;
            $matchesLocalId = is_numeric($approverIdInFlow) && (int)$approverIdInFlow === (int)$user->id;
            $approverIdInFlowStr = is_null($approverIdInFlow) ? '' : (string)$approverIdInFlow;
            $userExt = (string)($user->external_user_id ?? '');
            $matchesExternalId = ($approverIdInFlowStr !== '') && ($userExt !== '') && ($approverIdInFlowStr === $userExt);
            if ($matchesLocalId || $matchesExternalId) {
                $isCurrentUserNextApprover = true;
            }

            if (!$isCurrentUserNextApprover) {
                $waitingForApproverName = $currentApprover['name'];
            }
        } else {
            // All steps approved → ダッシュボード対象外
            continue;
        }

        $status_for_dashboard = '';
        if ($isCurrentUserNextApprover) {
            $status_for_dashboard = '確認して承認';
        } elseif ($waitingForApproverName) {
            $status_for_dashboard = "{$waitingForApproverName}さんの承認待ち";
        } else {
            // Should not happen if there is a current step, but as a fallback
            continue;
        }

        $toDoEstimates[] = [
            'id' => $estimate->id,
            'title' => $estimate->title,
            'issue_date' => $estimate->issue_date,
            'status_for_dashboard' => $status_for_dashboard,
            'estimate_number' => $estimate->estimate_number,
            'estimate' => $estimate->toArray(),
            'is_current_user_in_flow' => $isCurrentUserInFlow,
        ];
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
    Route::get('/billing', [BillingController::class, 'index'])->name('billing.index');
    Route::get('/billing/{billing}/pdf', [BillingController::class, 'downloadPdf'])->name('billing.downloadPdf');
    Route::get('/inventory', fn () => Inertia::render('Inventory/Index'))->name('inventory.index');
    Route::get('/products', fn () => Inertia::render('Products/Index'))->name('products.index');

    Route::get('/billing/create', fn () => Inertia::render('Billing/Create'))->name('billing.create');

    // Money Forward API routes
    Route::get('/billing/money-forward/authorize', [App\Http\Controllers\BillingController::class, 'fetchInvoices'])->name('money-forward.authorize');
    Route::get('/callback', [App\Http\Controllers\BillingController::class, 'fetchInvoices'])->name('money-forward.callback');

    Route::get('/quotes', [App\Http\Controllers\EstimateController::class, 'index'])->name('quotes.index');

    Route::post('/estimates/preview-pdf', [App\Http\Controllers\EstimateController::class, 'previewPdf'])->name('estimates.previewPdf');
    Route::post('/estimates', [App\Http\Controllers\EstimateController::class, 'store'])->name('estimates.store');
    Route::post('/estimates/{estimate}', [App\Http\Controllers\EstimateController::class, 'update'])->whereNumber('estimate')->name('estimates.update');
    Route::patch('/estimates/{estimate}', [App\Http\Controllers\EstimateController::class, 'update'])->whereNumber('estimate');
    Route::patch('estimates/{estimate}/cancel', [App\Http\Controllers\EstimateController::class, 'cancel'])->name('estimates.cancel');
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

    // Create Invoice from Estimate
    Route::get('/estimates/{estimate}/create-invoice', [App\Http\Controllers\EstimateController::class, 'redirectToAuthForInvoiceCreation'])->name('estimates.createInvoice.start');
    Route::get('/estimates/create-invoice/callback', [App\Http\Controllers\EstimateController::class, 'handleInvoiceCreationCallback'])->name('estimates.createInvoice.callback');

    // API routes moved outside auth for login page access

    Route::get('/admin', fn () => Inertia::render('Admin/Index'))->name('admin.index');
});

require __DIR__.'/auth.php';
