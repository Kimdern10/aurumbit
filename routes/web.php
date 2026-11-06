<?php

use App\Http\Controllers\Admin\KycFileController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\PriceController;
use App\Http\Controllers\ReceiptController;
use App\Http\Controllers\SitemapController;
use App\Http\Controllers\StatementController;
use App\Http\Middleware\CatchUpInvestments;
use App\Http\Middleware\RedirectAdminsToAdminArea;
use Illuminate\Support\Facades\Route;

Route::get('/', HomeController::class)->name('home');
Route::get('prices.json', PriceController::class)->middleware('throttle:30,1')->name('prices');
Route::view('privacy-policy', 'legal.privacy')->name('legal.privacy');
Route::view('terms', 'legal.terms')->name('legal.terms');
Route::view('risk-disclosure', 'legal.risk')->name('legal.risk');
Route::get('sitemap.xml', [SitemapController::class, 'sitemap'])->name('sitemap');
Route::get('robots.txt', [SitemapController::class, 'robots'])->name('robots');

Route::middleware(['auth', 'verified', RedirectAdminsToAdminArea::class, CatchUpInvestments::class])->group(function () {
    Route::livewire('dashboard', 'pages::dashboard')->name('dashboard');
    Route::livewire('invest', 'pages::invest')->name('invest');
    Route::livewire('investments', 'pages::investments')->name('investments');
    Route::livewire('deposit', 'pages::deposit')->name('deposit');
    Route::livewire('withdraw', 'pages::withdraw')->name('withdraw');
    Route::livewire('transactions', 'pages::transactions')->name('transactions');
    Route::redirect('history', 'transactions?view=requests')->name('history');
    Route::get('receipts/{kind}/{id}', ReceiptController::class)->whereIn('kind', ['deposit', 'withdrawal'])->whereNumber('id')->name('receipts.show');
    Route::livewire('verification', 'pages::kyc')->name('kyc');
    Route::livewire('referrals', 'pages::referrals')->name('referrals');
    Route::livewire('notifications', 'pages::notifications')->name('notifications');
    Route::livewire('support', 'pages::support')->name('support');
    Route::livewire('support/{ticket}', 'pages::support-ticket')->name('support.show');
    Route::get('transactions/statement.csv', StatementController::class)->name('transactions.export');
});

Route::middleware(['auth', 'verified', 'admin', CatchUpInvestments::class])->prefix('admin')->name('admin.')->group(function () {
    Route::livewire('/', 'pages::admin.dashboard')->name('dashboard');
    Route::livewire('users', 'pages::admin.users')->name('users');
    Route::livewire('users/{user}', 'pages::admin.user')->name('users.show');
    Route::livewire('plans', 'pages::admin.plans')->name('plans');
    Route::livewire('deposits', 'pages::admin.deposits')->name('deposits');
    Route::livewire('withdrawals', 'pages::admin.withdrawals')->name('withdrawals');
    Route::livewire('investments', 'pages::admin.investments')->name('investments');
    Route::livewire('verifications', 'pages::admin.kyc')->name('kyc');
    Route::get('verifications/{kycSubmission}/{file}', KycFileController::class)->whereIn('file', ['document', 'selfie'])->name('kyc.file');
    Route::livewire('announcements', 'pages::admin.announcements')->name('announcements');
    Route::livewire('wallets', 'pages::admin.wallets')->name('wallets');
    Route::get('receipts/{kind}/{id}', ReceiptController::class)->whereIn('kind', ['deposit', 'withdrawal'])->whereNumber('id')->name('receipts.show');
    Route::livewire('support', 'pages::admin.tickets')->name('tickets');
    Route::livewire('support/{ticket}', 'pages::admin.ticket')->name('tickets.show');
    Route::livewire('audit-log', 'pages::admin.ledger')->name('ledger');
    Route::livewire('settings', 'pages::admin.settings')->name('settings');
});

require __DIR__.'/settings.php';
