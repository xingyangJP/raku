<?php

use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ProductController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use App\Models\Estimate; // Add this import
use Illuminate\Support\Facades\Auth; // Add this import
use App\Http\Controllers\BillingController;
use App\Http\Controllers\CategoryController;

Route::get('/', function () {
    return redirect()->route('login');
});

Route::get('/dashboard', [DashboardController::class, 'index'])
    ->middleware(['auth', 'verified'])
    ->name('dashboard');

// Guest-accessible API endpoints for login page user/customer lookup
Route::get('/api/customers', [App\Http\Controllers\ApiController::class, 'getCustomers']);
Route::get('/api/users', [App\Http\Controllers\ApiController::class, 'getUsers']);
// Partner departments lookup (by mf_partner_id)
Route::get('/api/partners/{partner}/departments', [App\Http\Controllers\ApiController::class, 'getPartnerDepartments']);

Route::middleware('auth')->group(function () {
    // Static route first to avoid conflict with /estimates/{estimate}
    Route::post('/estimates/draft', [App\Http\Controllers\EstimateController::class, 'saveDraft'])->name('estimates.saveDraft');

    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    // Product Master Routes - New Sync Flow
    Route::get('products/sync-all', [ProductController::class, 'syncAllToMf'])->name('products.sync.all');
    Route::get('products/{product}/sync-one', [ProductController::class, 'syncOneToMf'])->name('products.sync.one');
    Route::get('products/auth/start', [ProductController::class, 'redirectToAuth'])->name('products.auth.start');
    Route::get('products/auth/callback', [ProductController::class, 'handleCallback'])->name('products.auth.callback');

    Route::resource('products', ProductController::class);
    // Legacy helper API (not used anymore): Route::get('/api/product-categories', [ProductController::class, 'getCategories']);

    // Categories API for CategoryDialog.jsx
    Route::get('categories', [CategoryController::class, 'index'])->name('categories.index');
    Route::post('categories', [CategoryController::class, 'store'])->name('categories.store');
    Route::put('categories/{id}', [CategoryController::class, 'update'])->whereNumber('id')->name('categories.update');
    Route::delete('categories/{id}', [CategoryController::class, 'destroy'])->whereNumber('id')->name('categories.destroy');

    Route::get('/billing', [BillingController::class, 'index'])->name('billing.index');
    Route::get('/mf/billings/auth/start', [BillingController::class, 'redirectToAuth'])->name('billing.auth.start');
    Route::get('/billing/{billing}/pdf', [BillingController::class, 'downloadPdf'])->name('billing.downloadPdf');
    Route::get('/inventory', fn () => Inertia::render('Inventory/Index'))->name('inventory.index');
    Route::get('/help', fn () => Inertia::render('Help/Index'))->name('help.index');

    Route::get('/billing/create', fn () => Inertia::render('Billing/Create'))->name('billing.create');

    // Money Forward API routes
    Route::get('/billing/money-forward/authorize', [App\Http\Controllers\BillingController::class, 'fetchInvoices'])->name('money-forward.authorize');
    Route::get('/callback', [App\Http\Controllers\BillingController::class, 'fetchInvoices'])->name('money-forward.callback');

    Route::get('/quotes', [App\Http\Controllers\EstimateController::class, 'index'])->name('quotes.index');
    Route::post('/quotes/sync', [App\Http\Controllers\EstimateController::class, 'syncQuotes'])->name('quotes.sync');
    Route::get('/quotes/mf/auth/start', [App\Http\Controllers\EstimateController::class, 'redirectToAuthForQuoteSync'])->name('quotes.auth.start');
    Route::get('/quotes/mf/auth/callback', [App\Http\Controllers\EstimateController::class, 'handleQuoteSyncCallback'])->name('quotes.auth.callback');
    Route::get('/quotes/mf/callback', [App\Http\Controllers\EstimateController::class, 'handleQuoteSyncCallback'])->name('quotes.auth.callback.legacy');

    Route::post('/estimates/preview-pdf', [App\Http\Controllers\EstimateController::class, 'previewPdf'])->name('estimates.previewPdf');
    Route::post('/estimates', [App\Http\Controllers\EstimateController::class, 'store'])->name('estimates.store');
    Route::post('/estimates/{estimate}', [App\Http\Controllers\EstimateController::class, 'update'])->whereNumber('estimate')->name('estimates.update');
    Route::patch('/estimates/{estimate}', [App\Http\Controllers\EstimateController::class, 'update'])->whereNumber('estimate');
    Route::patch('estimates/{estimate}/cancel', [App\Http\Controllers\EstimateController::class, 'cancel'])->name('estimates.cancel');
    Route::delete('/estimates/{estimate}', [App\Http\Controllers\EstimateController::class, 'destroy'])->whereNumber('estimate')->name('estimates.destroy');
    Route::post('/estimates/bulk-approve', [App\Http\Controllers\EstimateController::class, 'bulkApprove'])->name('estimates.bulkApprove');
    Route::post('/estimates/bulk-reassign', [App\Http\Controllers\EstimateController::class, 'bulkReassign'])->name('estimates.bulkReassign');
    Route::post('/estimates/generate-notes', [App\Http\Controllers\EstimateController::class, 'generateNotes'])->name('estimates.generateNotes');

    // Approve current step in approval flow
    Route::put('/estimates/{estimate}/approval', [App\Http\Controllers\EstimateController::class, 'updateApproval'])
        ->whereNumber('estimate')
        ->name('estimates.updateApproval');

    Route::get('/estimates/create', [App\Http\Controllers\EstimateController::class, 'create'])->name('estimates.create');
    Route::get('/estimates/{estimate}/edit', [App\Http\Controllers\EstimateController::class, 'edit'])->whereNumber('estimate')->name('estimates.edit');
    Route::post('/estimates/{estimate}/duplicate', [App\Http\Controllers\EstimateController::class, 'duplicate'])->whereNumber('estimate')->name('estimates.duplicate');
    // Local invoice flow
    Route::post('/invoices/from-estimate/{estimate}', [App\Http\Controllers\LocalInvoiceController::class, 'createFromEstimate'])->whereNumber('estimate')->name('invoices.fromEstimate');
    Route::get('/invoices/{invoice}/edit', [App\Http\Controllers\LocalInvoiceController::class, 'edit'])->whereNumber('invoice')->name('invoices.edit');
    Route::patch('/invoices/{invoice}', [App\Http\Controllers\LocalInvoiceController::class, 'update'])->whereNumber('invoice')->name('invoices.update');
    Route::get('/invoices/{invoice}/send', [App\Http\Controllers\LocalInvoiceController::class, 'redirectToAuthForSending'])->whereNumber('invoice')->name('invoices.send.start');
    Route::get('/invoices/send/callback', [App\Http\Controllers\LocalInvoiceController::class, 'handleSendCallback'])->name('invoices.send.callback'); // This was pointing to the correct controller but the auth redirect was wrong. Let's fix the controller side.
    Route::get('/invoices/{invoice}/view-pdf', [App\Http\Controllers\LocalInvoiceController::class, 'redirectToAuthForPdf'])->whereNumber('invoice')->name('invoices.viewPdf.start');
    Route::get('/invoices/view-pdf/callback', [App\Http\Controllers\LocalInvoiceController::class, 'handleViewPdfCallback'])->name('invoices.viewPdf.callback');
    Route::get('/invoices/{invoice}/pdf', [App\Http\Controllers\LocalInvoiceController::class, 'downloadPdf'])->whereNumber('invoice')->name('invoices.downloadPdf');
    Route::delete('/invoices/{invoice}', [App\Http\Controllers\LocalInvoiceController::class, 'destroy'])->whereNumber('invoice')->name('invoices.destroy');
    

    // View MF Quote PDF via OAuth
    Route::get('/estimates/{estimate}/view-quote', [App\Http\Controllers\EstimateController::class, 'viewMfQuotePdf'])->name('estimates.viewQuote.start');
    Route::get('/estimates/{estimate}/purchase-order/preview', [App\Http\Controllers\EstimateController::class, 'purchaseOrderPreview'])
        ->whereNumber('estimate')
        ->name('estimates.purchaseOrder.preview');

    // Create Quote from Estimate
    Route::get('/estimates/{estimate}/create-quote', [App\Http\Controllers\EstimateController::class, 'createMfQuote'])->name('estimates.createQuote.start');

    // Convert Quote to Billing
    Route::get('/estimates/{estimate}/convert-to-billing', [App\Http\Controllers\EstimateController::class, 'convertMfQuoteToBilling'])->name('estimates.convertToBilling.start');

    // Estimate Auth Flow
    Route::get('/estimates/auth/start', [App\Http\Controllers\EstimateController::class, 'redirectToAuth'])->name('estimates.auth.start');
    Route::get('/estimates/auth/callback', [App\Http\Controllers\EstimateController::class, 'handleCallback'])->name('estimates.auth.callback');
    Route::get('/estimates/create-quote/callback', [App\Http\Controllers\EstimateController::class, 'handleCallback'])->name('estimates.createQuote.callback');
    Route::get('/estimates/convert-to-billing/callback', [App\Http\Controllers\EstimateController::class, 'handleCallback'])->name('estimates.convertToBilling.callback');
    Route::get('/estimates/view-quote/callback', [App\Http\Controllers\EstimateController::class, 'handleCallback'])->name('estimates.viewQuote.callback');

    // API routes moved outside auth for login page access

    Route::get('/admin', fn () => Inertia::render('Admin/Index'))->name('admin.index');

    // Money Forward partners sync (Dashboard)
    Route::get('/mf/partners/sync', [DashboardController::class, 'syncPartners'])->name('partners.sync');
    Route::get('/mf/partners/auth/start', [DashboardController::class, 'redirectToAuthForPartners'])->name('partners.auth.start');
    Route::get('/mf/partners/auth/callback', [DashboardController::class, 'handlePartnersCallback'])->name('partners.auth.callback');
});

require __DIR__.'/auth.php';
