<?php

use App\Http\Controllers\ProfileController;
use App\Http\Controllers\OrganizationSettingsController;
use App\Http\Controllers\MembersController;
use App\Http\Controllers\InvitationController;
use App\Http\Controllers\InvitationAcceptController;
use App\Http\Controllers\DocumentController;
use App\Http\Controllers\DocumentSignerController;
use App\Http\Controllers\SigningController;
use App\Http\Controllers\DocumentSignatureFieldController;
use App\Http\Controllers\BillingController;
use App\Http\Controllers\BillingTopupController;
use App\Http\Controllers\StripeWebhookController;
use App\Http\Controllers\DashboardController;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/

Route::middleware('guest')->group(function () {
        Route::get('/', function () {
            return Inertia::render('Landing');
        })->name('landing');

    Route::get(
            '/invitations/{token}',
            [InvitationAcceptController::class, 'show']
        )->name('invitations.accept');
    Route::post(
            '/invitations/{token}',
            [InvitationAcceptController::class, 'store']
        )->name('invitations.complete');
});

    Route::get('/health', function () {
        return response()->json([
            'status' => 'ok',
        ]);
    })->name('health');

    Route::post('/stripe/webhook', [
        StripeWebhookController::class,
        'handle',
    ])->name('stripe.webhook');

    Route::get(
        '/sign/{token}',
        [SigningController::class, 'show']
    )->name('signing.show');

    Route::post(
        '/sign/{token}/otp/verify',
        [SigningController::class, 'verifyOtp']
    )->name('signing.otp.verify');

    Route::post(
        '/sign/{token}/otp/resend',
        [SigningController::class, 'resendOtp']
    )->name('signing.otp.resend');

    Route::post(
        '/sign/{token}/finish',
        [SigningController::class, 'finish']
    )->name('sign.finish');

    Route::get(
        '/sign/{token}/completed',
        [SigningController::class, 'completed']
    )->name('sign.completed');

Route::middleware('auth', 'verified')->group(function () {
    Route::get('/dashboard', function () {
        return Inertia::render('Dashboard');
    });
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
    Route::get('/dashboard/statistics', [DashboardController::class, 'statistics'])->name('dashboard.statistics');
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
    Route::get('/settings/organization', [OrganizationSettingsController::class, 'edit'])
        ->name('settings.organization');
    Route::patch(
        '/settings/organization',
        [OrganizationSettingsController::class, 'update']
    )->name('settings.organization.update');
    Route::get(
        '/members',
        [MembersController::class, 'index']
    )->name('members.index');

    Route::patch(
        '/members/{user}/role',
        [MembersController::class, 'updateRole']
    )->name('members.role.update');

    Route::post(
        '/invitations',
        [InvitationController::class, 'store']
    )->name('invitations.store');
    Route::post(
        '/invitations/{invitation}/resend',
        [InvitationController::class, 'resend']
    )->name('invitations.resend');

    Route::get(
        '/documents',
        [DocumentController::class, 'index']
    )->name('documents.index');

    Route::get(
        '/documents/{document}/prepare',
        [DocumentController::class, 'prepare']
    )->name('documents.prepare');


    Route::post(
        '/documents',
        [DocumentController::class, 'store']
    )->name('documents.store');

    Route::get(
        '/documents/{document}',
        [DocumentController::class, 'show']
    )->name('documents.show');

    Route::post(
        '/documents/{document}/signers',
        [DocumentSignerController::class, 'store']
    )->name('documents.signers.store');

    Route::delete(
        '/documents/signers/{signer}',
        [DocumentSignerController::class, 'destroy']
    )->name('documents.signers.destroy');

    Route::post(
        '/documents/{document}/signers/reorder',
        [DocumentSignerController::class, 'reorder']
    )->name('documents.signers.reorder');

    Route::post(
        '/documents/{document}/send',
        [DocumentController::class, 'send']
    )->name('documents.send');

    Route::get(
        '/documents/{document}/preview',
        [DocumentController::class, 'preview']
    )->name('documents.preview');

    Route::get(
        '/documents/{document}/download',
        [DocumentController::class, 'download']
    )->name('documents.download');

    Route::post(
        '/documents/{document}/signature-fields',
        [DocumentSignatureFieldController::class, 'store']
    )->name('documents.signature-fields.store');

    Route::delete(
        '/signature-fields/{signatureField}',
        [DocumentSignatureFieldController::class, 'destroy']
    )->name('documents.signature-fields.destroy');

    Route::patch(
        '/signature-fields/{signatureField}',
        [DocumentSignatureFieldController::class, 'update']
    )->name('documents.signature-fields.update');

    Route::post(
        '/documents/{document}/prepare/finish',
        [DocumentController::class, 'finishPrepare']
    )->name('documents.prepare.finish');

    Route::delete(
        '/invitations/{invitation}',
        [InvitationController::class, 'destroy']
    )->name('invitations.destroy');


    Route::get('/billing', [BillingController::class, 'index'])
        ->name('billing.index');

    Route::post('/billing/topups', [
        BillingTopupController::class,
        'store',
    ])->name('billing.topups.store');
});

require __DIR__.'/auth.php';