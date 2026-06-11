<?php

use App\Http\Controllers\ProfileController;
use App\Http\Controllers\OrganizationSettingsController;
use App\Http\Controllers\MembersController;
use App\Http\Controllers\InvitationController;
use App\Http\Controllers\InvitationAcceptController;
use App\Http\Controllers\DocumentController;
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

Route::get('/', function () {
    return Inertia::render('Landing');
});

Route::get(
        '/invitations/{token}',
        [InvitationAcceptController::class, 'show']
    )->name('invitations.accept');
Route::post(
        '/invitations/{token}',
        [InvitationAcceptController::class, 'store']
    )->name('invitations.complete');
Route::delete(
        '/invitations/{invitation}',
        [InvitationController::class, 'destroy']
    )->name('invitations.destroy');

Route::get('/dashboard', function () {
    return Inertia::render('Dashboard');
})->middleware(['auth', 'verified'])->name('dashboard');

Route::middleware('auth')->group(function () {
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
});

require __DIR__.'/auth.php';
