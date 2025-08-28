<?php

use App\Http\Controllers\ProfileController;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

Route::get('/', function () {
    // Redirect root to the login page
    return redirect()->route('login');
});

Route::get('/dashboard', function () {
    return Inertia::render('Dashboard');
})->middleware(['auth', 'verified'])->name('dashboard');

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    Route::get('/sales', function () {
        return Inertia::render('Sales/Index');
    })->name('sales.index');

    Route::get('/deposits', function () {
        return Inertia::render('Deposits/Index');
    })->name('deposits.index');

    Route::get('/billing', function () {
        return Inertia::render('Billing/Index');
    })->name('billing.index');

    Route::get('/inventory', function () {
        return Inertia::render('Inventory/Index');
    })->name('inventory.index');

    Route::get('/products', function () {
        return Inertia::render('Products/Index');
    })->name('products.index');

    Route::get('/quotes', function () {
        return Inertia::render('Quotes/Index');
    })->name('quotes.index');

    Route::get('/estimates/create', [App\Http\Controllers\EstimateController::class, 'create'])->name('estimates.create');

    Route::post('/estimates/preview-pdf', [App\Http\Controllers\EstimateController::class, 'previewPdf'])->name('estimates.previewPdf');

    Route::get('/api/customers', [App\Http\Controllers\ApiController::class, 'getCustomers']);
    Route::get('/api/users', [App\Http\Controllers\ApiController::class, 'getUsers']);

    Route::get('/admin', function () {
        return Inertia::render('Admin/Index');
    })->name('admin.index');
});

require __DIR__.'/auth.php';