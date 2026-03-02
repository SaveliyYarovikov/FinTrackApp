<?php

use App\Http\Controllers\AccountController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\BudgetController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\CsvImportController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\EmailVerificationController;
use App\Http\Controllers\FinancialGoalController;
use App\Http\Controllers\RecurringOperationController;
use App\Http\Controllers\TransactionController;
use App\Http\Controllers\PasswordResetController;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function () {
   Route::get('/login', [AuthController::class, 'showLoginForm'])
       ->name('login');
   Route::post('/login', [AuthController::class, 'login'])
       ->name('login.post')
       ->middleware('throttle:login');
   Route::get('/register', [AuthController::class, 'showRegistrationForm'])
       ->name('register');
   Route::post('/register', [AuthController::class, 'register'])
       ->name('register.post');

   Route::get('/forgot-password', [PasswordResetController::class, 'showPasswordResetRequestForm'])
       ->name('password.request');

    Route::post('/forgot-password', [PasswordResetController::class, 'sendPasswordResetEmail'])
        ->middleware('throttle:password-reset-request')
        ->name('password.email');

    Route::get('/reset-password/{token}', [PasswordResetController::class, 'showResetPasswordForm'])
        ->name('password.reset');

    Route::post('/reset-password', [PasswordResetController::class, 'resetPassword'])
        ->middleware('throttle:password-reset')
        ->name('password.store');
});

Route::middleware('auth')->group(function () {
   Route::get('/email/verify', [EmailVerificationController::class, 'index'])
       ->name('verification.notice');
   Route::get('/email/verify/{id}/{hash}', [EmailVerificationController::class, 'verify'])
       ->name('verification.verify')
       ->middleware(['signed', 'throttle:5,1']);
   Route::post('/logout', [AuthController::class, 'logout'])
       ->name('logout');
   Route::post('/email/verification-notification', [EmailVerificationController::class, 'resend'])
       ->name('verification.send')
       ->middleware('throttle:5,1');
});

Route::middleware(['auth', 'verified'])->group(function () {
   Route::get('/dashboard', [DashboardController::class, 'index'])
       ->name('dashboard');

   Route::resource('accounts', AccountController::class)
       ->only(['index', 'create', 'store', 'edit', 'update']);
   Route::post('/accounts/{account}/archive', [AccountController::class, 'archive'])
       ->name('accounts.archive');

   Route::delete('/categories/bulk-delete', [CategoryController::class, 'destroyMany'])
       ->name('categories.bulk-destroy');
   Route::resource('categories', CategoryController::class)
       ->only(['index', 'create', 'store', 'edit', 'update', 'destroy']);

   Route::resource('recurring-operations', RecurringOperationController::class)
       ->parameters(['recurring-operations' => 'recurringOperation'])
       ->only(['index', 'create', 'store', 'edit', 'update', 'destroy']);
   Route::post('recurring-operations/{recurringOperation}/apply', [RecurringOperationController::class, 'apply'])
       ->name('recurring-operations.apply');

   Route::resource('budgets', BudgetController::class)
       ->only(['index', 'create', 'store', 'edit', 'update', 'destroy']);

   Route::resource('financial-goals', FinancialGoalController::class)
       ->parameters(['financial-goals' => 'financialGoal'])
       ->only(['index', 'create', 'store', 'edit', 'update', 'destroy']);
   Route::post('/financial-goals/{financialGoal}/archive', [FinancialGoalController::class, 'archive'])
       ->name('financial-goals.archive');

   Route::get('/transactions', [TransactionController::class, 'index'])->name('transactions.index');
   Route::get('/transactions/create-income', [TransactionController::class, 'createIncome'])->name('transactions.create-income');
   Route::post('/transactions/income', [TransactionController::class, 'storeIncome'])->name('transactions.store-income');
   Route::get('/transactions/create-expense', [TransactionController::class, 'createExpense'])->name('transactions.create-expense');
   Route::post('/transactions/expense', [TransactionController::class, 'storeExpense'])->name('transactions.store-expense');
   Route::get('/transactions/{entry}/edit', [TransactionController::class, 'edit'])->name('transactions.edit');
   Route::put('/transactions/{entry}', [TransactionController::class, 'update'])->name('transactions.update');
   Route::delete('/transactions/bulk-delete', [TransactionController::class, 'destroyMany'])->name('transactions.bulk-destroy');
   Route::delete('/transactions/{entry}', [TransactionController::class, 'destroy'])->name('transactions.destroy');

   Route::get('/imports/csv', [CsvImportController::class, 'create'])->name('imports.csv.create');
   Route::post('/imports/csv', [CsvImportController::class, 'store'])->name('imports.csv.store');

   Route::redirect('/', '/dashboard');
});
