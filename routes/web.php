<?php

use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ProductController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use App\Models\Estimate; // Add this import
use Illuminate\Support\Facades\Auth; // Add this import
use App\Http\Controllers\BillingController;

Route::get('/', function () {
    return redirect()->route('login');
});

Route::get('/dashboard', [DashboardController::class, 'index'])
    ->middleware(['auth', 'verified'])
    ->name('dashboard');

// Guest-accessible API endpoints for login page user/customer lookup
Route::get('/api/customers', [App\Http\Controllers\ApiController::class, 'getCustomers']);
Route::get('/api/users', [App\Http\Controllers\ApiController::class, 'getUsers']);

Route::middleware('auth')->group(function () {
    // Static route first to avoid conflict with /estimates/{estimate}
    Route::post('/estimates/draft', [App\Http\Controllers\EstimateController::class, 'saveDraft'])->name('estimates.saveDraft');

    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    // Product Master Routes
    Route::resource('products', ProductController::class);
    Route::get('/api/product-categories', [ProductController::class, 'getCategories']);

    Route::get('/sales', fn () => Inertia::render('Sales/Index'))->name('sales.index');
    Route::get('/deposits', fn () => Inertia::render('Deposits/Index'))->name('deposits.index');
    Route::get('/billing', [BillingController::class, 'index'])->name('billing.index');
    Route::get('/billing/{billing}/pdf', [BillingController::class, 'downloadPdf'])->name('billing.downloadPdf');
    Route::get('/inventory', fn () => Inertia::render('Inventory/Index'))->name('inventory.index');

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

    // Create Quote from Estimate
    Route::get('/estimates/{estimate}/create-quote', [App\Http\Controllers\EstimateController::class, 'redirectToAuthForQuoteCreation'])->name('estimates.createQuote.start');
    Route::get('/estimates/create-quote/callback', [App\Http\Controllers\EstimateController::class, 'handleQuoteCreationCallback'])->name('estimates.createQuote.callback');

    // Convert Quote to Billing
    Route::get('/estimates/{estimate}/convert-to-billing', [App\Http\Controllers\EstimateController::class, 'redirectToAuthForBillingConversion'])->name('estimates.convertToBilling.start');
    Route::get('/estimates/convert-to-billing/callback', [App\Http\Controllers\EstimateController::class, 'handleBillingConversionCallback'])->name('estimates.convertToBilling.callback');

    // API routes moved outside auth for login page access

    Route::get('/admin', fn () => Inertia::render('Admin/Index'))->name('admin.index');
});

require __DIR__.'/auth.php';
