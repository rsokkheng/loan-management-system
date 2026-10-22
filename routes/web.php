<?php

use App\Http\Controllers\Accounting\AccountController;
use App\Http\Controllers\Accounting\AccountingReportController;
use App\Http\Controllers\Accounting\CashTransactionController;
use App\Http\Controllers\Accounting\JournalEntryController;
use App\Http\Controllers\ActivityLogController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\BorrowerController;
use App\Http\Controllers\BorrowerDocumentController;
use App\Http\Controllers\BranchController;
use App\Http\Controllers\CalculatorController;
use App\Http\Controllers\CollateralController;
use App\Http\Controllers\CurrencyController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\FamilyMemberController;
use App\Http\Controllers\GuarantorController;
use App\Http\Controllers\LoanController;
use App\Http\Controllers\LoanProductController;
use App\Http\Controllers\LoanRestructureController;
use App\Http\Controllers\LoanSettlementController;
use App\Http\Controllers\LoanWorkflowController;
use App\Http\Controllers\LocaleController;
use App\Http\Controllers\LocationController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\SettingController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

Route::view('/', 'welcome')->name('home');
Route::post('locale/{locale}', LocaleController::class)->name('locale.switch');

Route::middleware('guest')->group(function () {
    Route::get('login', [LoginController::class, 'create'])->name('login');
    Route::post('login', [LoginController::class, 'store'])->middleware('throttle:login');
});

Route::post('logout', [LoginController::class, 'destroy'])->middleware('auth')->name('logout');

Route::middleware(['auth', 'active'])->group(function () {
    Route::get('dashboard', DashboardController::class)->name('dashboard');
    Route::get('calculator', CalculatorController::class)->name('calculator');
    Route::post('branch/switch', [BranchController::class, 'switch'])->name('branches.switch');
    Route::post('currency/switch', CurrencyController::class)->name('currency.switch');

    Route::prefix('locations')->name('locations.')->group(function () {
        Route::get('districts', [LocationController::class, 'districts'])->name('districts');
        Route::get('communes', [LocationController::class, 'communes'])->name('communes');
        Route::get('villages', [LocationController::class, 'villages'])->name('villages');
    });

    Route::get('profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::put('profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::put('profile/password', [ProfileController::class, 'updatePassword'])->name('profile.password');

    Route::resource('borrowers', BorrowerController::class);
    Route::post('borrowers/{borrower}/documents', [BorrowerDocumentController::class, 'store'])->name('borrowers.documents.store');
    Route::get('documents/{document}', [BorrowerDocumentController::class, 'show'])->name('documents.show');
    Route::delete('documents/{document}', [BorrowerDocumentController::class, 'destroy'])->name('documents.destroy');
    Route::post('borrowers/{borrower}/family-members', [FamilyMemberController::class, 'store'])->name('borrowers.family-members.store');
    Route::delete('family-members/{familyMember}', [FamilyMemberController::class, 'destroy'])->name('family-members.destroy');

    Route::resource('loan-products', LoanProductController::class)
        ->except('show')
        ->parameters(['loan-products' => 'loanProduct'])
        ->middleware('can:manage-products');

    Route::resource('loans', LoanController::class);
    Route::get('loans/{loan}/print', [LoanController::class, 'print'])->name('loans.print');
    Route::post('loans/{loan}/approve', [LoanWorkflowController::class, 'approve'])->middleware('can:approve-loans')->name('loans.approve');
    Route::post('loans/{loan}/return', [LoanWorkflowController::class, 'returnForCorrection'])->middleware('can:approve-loans')->name('loans.return');
    Route::post('loans/{loan}/resubmit', [LoanWorkflowController::class, 'resubmit'])->name('loans.resubmit');
    Route::post('loans/{loan}/reject', [LoanWorkflowController::class, 'reject'])->middleware('can:approve-loans')->name('loans.reject');
    Route::post('loans/{loan}/disburse', [LoanWorkflowController::class, 'disburse'])->middleware('can:disburse-loans')->name('loans.disburse');
    Route::post('loans/{loan}/restructure', [LoanRestructureController::class, 'store'])->middleware('can:approve-loans')->name('loans.restructure');
    Route::post('loans/{loan}/write-off', [LoanWorkflowController::class, 'writeOff'])->middleware('can:write-off-loans')->name('loans.write-off');
    Route::post('loans/{loan}/guarantors', [GuarantorController::class, 'store'])->name('loans.guarantors.store');
    Route::delete('guarantors/{guarantor}', [GuarantorController::class, 'destroy'])->name('guarantors.destroy');
    Route::post('loans/{loan}/collaterals', [CollateralController::class, 'store'])->name('loans.collaterals.store');
    Route::get('collaterals/{collateral}/document', [CollateralController::class, 'document'])->name('collaterals.document');
    Route::post('collaterals/{collateral}/release', [CollateralController::class, 'release'])->name('collaterals.release');
    Route::delete('collaterals/{collateral}', [CollateralController::class, 'destroy'])->name('collaterals.destroy');

    Route::get('payments', [PaymentController::class, 'index'])->name('payments.index');
    Route::get('payments/create', [PaymentController::class, 'create'])->name('payments.create');
    Route::post('loans/{loan}/payments', [PaymentController::class, 'store'])->name('payments.store');
    Route::post('loans/{loan}/recoveries', [PaymentController::class, 'storeRecovery'])->name('loans.recoveries.store');
    Route::get('loans/{loan}/settle', [LoanSettlementController::class, 'create'])->name('loans.settle');
    Route::post('loans/{loan}/settle', [LoanSettlementController::class, 'store'])->name('loans.settle.store');
    Route::get('payments/{payment}', [PaymentController::class, 'show'])->name('payments.show');
    Route::get('payments/{payment}/receipt', [PaymentController::class, 'receipt'])->name('payments.receipt');

    Route::middleware('can:view-reports')->prefix('reports')->name('reports.')->group(function () {
        Route::get('/', [ReportController::class, 'index'])->name('index');
        Route::get('collections', [ReportController::class, 'collections'])->name('collections');
        Route::get('disbursements', [ReportController::class, 'disbursements'])->name('disbursements');
        Route::get('arrears', [ReportController::class, 'arrears'])->name('arrears');
        Route::get('portfolio', [ReportController::class, 'portfolio'])->name('portfolio');
        Route::get('portfolio-at-risk', [ReportController::class, 'portfolioAtRisk'])->name('portfolio-at-risk');
        Route::get('branch-profitability', [ReportController::class, 'branchProfitability'])->name('branch-profitability');
    });

    Route::middleware('can:manage-accounting')->prefix('accounting')->name('accounting.')->group(function () {
        Route::resource('accounts', AccountController::class);
        Route::resource('journal', JournalEntryController::class)->only(['index', 'create', 'store', 'show'])->parameters(['journal' => 'journalEntry']);
        Route::post('journal/{journalEntry}/reverse', [JournalEntryController::class, 'reverse'])->name('journal.reverse');
        Route::get('transactions/create', [CashTransactionController::class, 'create'])->name('transactions.create');
        Route::post('transactions', [CashTransactionController::class, 'store'])->name('transactions.store');
        Route::get('trial-balance', [AccountingReportController::class, 'trialBalance'])->name('trial-balance');
        Route::get('income-statement', [AccountingReportController::class, 'incomeStatement'])->name('income-statement');
        Route::get('balance-sheet', [AccountingReportController::class, 'balanceSheet'])->name('balance-sheet');
    });

    Route::resource('branches', BranchController::class)->except('show')->middleware('can:manage-branches');
    Route::resource('users', UserController::class)->except('show')->middleware('can:manage-users');

    Route::get('settings', [SettingController::class, 'edit'])->middleware('can:manage-settings')->name('settings.edit');
    Route::put('settings', [SettingController::class, 'update'])->middleware('can:manage-settings')->name('settings.update');

    Route::get('activity-logs', ActivityLogController::class)->middleware('can:view-activity')->name('activity-logs.index');
});
