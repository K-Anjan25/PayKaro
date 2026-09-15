<?php

use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\GoogleOAuthController;
use App\Http\Controllers\Auth\RegisteredUserController;
use App\Http\Controllers\Buyers\BuyerController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\Invoices\DisputeController;
use App\Http\Controllers\Invoices\FinancingController;
use App\Http\Controllers\Invoices\InvoiceController;
use App\Http\Controllers\Invoices\InvoiceMailController;
use App\Http\Controllers\Invoices\PaymentController;
use App\Http\Controllers\PageController;
use App\Http\Controllers\Workspace\AlertController;
use App\Http\Controllers\Workspace\FinanceQueueController;
use App\Http\Controllers\Workspace\ReportController;
use App\Http\Controllers\Workspace\SettingsController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Public
|--------------------------------------------------------------------------
|
| `/` is the landing page for visitors and the workspace overview for members
| (see HomeController). Everything below it is readable signed-out, because the
| pricing, help and news pages are what convince a supplier before they sign up.
|
*/

Route::get('/', HomeController::class)->name('landing');

Route::get('/pricing', [PageController::class, 'pricing'])->name('pricing');
Route::get('/help', [PageController::class, 'help'])->name('help');
Route::get('/contact', [PageController::class, 'contact'])->name('contact');

// The legal set. The flat-PHP app had these as footer text with no page behind
// them; they now have real content, and the paths are unchanged so any existing
// link (or bookmark) lands on the right document instead of the help page.
Route::get('/terms', [PageController::class, 'terms'])->name('terms');
Route::get('/privacy', [PageController::class, 'privacy'])->name('privacy');
Route::get('/security', [PageController::class, 'security'])->name('security');

Route::get('/news', fn () => redirect('/#news'))->name('news.index');
Route::get('/news/{slug}', [PageController::class, 'article'])->name('news.show');

/*
|--------------------------------------------------------------------------
| Sign in / sign up
|--------------------------------------------------------------------------
*/

Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthenticatedSessionController::class, 'create'])->name('login');
    Route::post('/login', [AuthenticatedSessionController::class, 'store']);

    Route::get('/signup', [RegisteredUserController::class, 'create'])->name('register');
    Route::post('/signup', [RegisteredUserController::class, 'store']);

    Route::get('/auth/google', [GoogleOAuthController::class, 'redirect'])->name('auth.google');
});

// The callback is not `guest`-guarded: a member whose session expired mid-
// consent should still be signed in by a valid callback.
Route::get('/auth/google/callback', [GoogleOAuthController::class, 'callback'])->name('auth.google.callback');

/*
|--------------------------------------------------------------------------
| Workspace
|--------------------------------------------------------------------------
|
| Everything here is tenant-scoped by the models, so an id from another
| business resolves to a 404 rather than a 403 — and the write actions are
| further gated by role (a viewer reads, never alters).
|
*/

Route::middleware('auth')->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
    Route::post('/logout', [AuthenticatedSessionController::class, 'destroy'])->name('logout');

    Route::post('/alerts/read', [AlertController::class, 'markRead'])->name('alerts.read');

    // Invoices: the pipeline.
    //
    // The legacy `/invoices/new` address has to be registered *before* the resource,
    // or `invoices/{invoice}` matches it first with `{invoice} = new`, the implicit
    // binding finds nothing, and the alias answers 404.
    Route::redirect('/invoices/new', '/invoices/create');

    Route::resource('invoices', InvoiceController::class)
        ->only(['index', 'create', 'store', 'show', 'edit', 'update'])
        ->parameters(['invoices' => 'invoice']);

    Route::patch('/invoices/{invoice}/status', [InvoiceController::class, 'updateStatus'])->name('invoices.status');
    Route::put('/invoices/{invoice}/evidence', [InvoiceController::class, 'updateEvidence'])->name('invoices.evidence');
    Route::get('/invoices/{invoice}/claim', [InvoiceController::class, 'claim'])->name('invoices.claim');

    Route::post('/invoices/{invoice}/send', [InvoiceMailController::class, 'send'])->name('invoices.send');
    Route::post('/invoices/{invoice}/remind', [InvoiceMailController::class, 'remind'])->name('invoices.remind');
    Route::post('/invoices/{invoice}/request-evidence', [InvoiceMailController::class, 'requestEvidence'])->name('invoices.request-evidence');

    Route::post('/invoices/{invoice}/payments', [PaymentController::class, 'store'])->name('invoices.payments.store');
    Route::post('/invoices/{invoice}/financings', [FinancingController::class, 'store'])->name('invoices.financings.store');
    Route::post('/invoices/{invoice}/disputes', [DisputeController::class, 'store'])->name('invoices.disputes.store');

    // Buyers.
    Route::get('/buyers', [BuyerController::class, 'index'])->name('buyers.index');
    Route::get('/buyers/create', [BuyerController::class, 'create'])->name('buyers.create');
    Route::post('/buyers', [BuyerController::class, 'store'])->name('buyers.store');

    // Money.
    Route::get('/treds', [FinanceQueueController::class, 'index'])->name('treds');
    Route::get('/reports', [ReportController::class, 'index'])->name('reports');

    // Identity.
    Route::get('/settings', [SettingsController::class, 'edit'])->name('settings.edit');
    Route::put('/settings', [SettingsController::class, 'update'])->name('settings.update');

    /*
    | Legacy query-string addresses, kept alive: the flat-PHP app linked
    | `/invoice?id=7`, `/invoice?edit=7` and `/claim?id=7`, and those URLs get
    | shared in email and WhatsApp threads long after a migration.
    |
    | The other legacy address, `/invoices/new`, is the one entry in this set that
    | is *not* here: it has to be matched before `invoices/{invoice}`, so it lives
    | a few lines up, beside the resource.
    */
    Route::get('/invoice', [PageController::class, 'legacyInvoice']);
    Route::get('/claim', [PageController::class, 'legacyClaim']);
});
