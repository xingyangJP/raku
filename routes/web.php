<?php

use App\Http\Controllers\ProfileController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    return redirect()->route('login');
});

Route::get('/dashboard', function () {
    return Inertia::render('Dashboard');
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

    Route::get('/estimates/create', [App\Http\Controllers\EstimateController::class, 'create'])->name('estimates.create');
    Route::get('/estimates/{estimate}/edit', [App\Http\Controllers\EstimateController::class, 'edit'])->whereNumber('estimate')->name('estimates.edit');
    Route::post('/estimates/{estimate}/duplicate', [App\Http\Controllers\EstimateController::class, 'duplicate'])->whereNumber('estimate')->name('estimates.duplicate');

    // API routes moved outside auth for login page access

    Route::get('/admin', fn () => Inertia::render('Admin/Index'))->name('admin.index');
});

require __DIR__.'/auth.php';
