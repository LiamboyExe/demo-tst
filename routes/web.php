<?php

use App\Http\Controllers\AccountController;
use App\Http\Controllers\BranchController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\MemberController;
use App\Http\Controllers\NfcCardController;
use App\Http\Controllers\PublicProfileController;
use Illuminate\Support\Facades\Route;

Route::get('/', fn () => redirect()->route(auth()->check() ? 'dashboard' : 'login'))->name('home');

Route::get('p/{member:profile_slug}', [PublicProfileController::class, 'show'])->name('profile.show');

use Illuminate\Auth\Events\Verified;
use Illuminate\Http\Request;

Route::middleware(['auth'])->group(function () {
    Route::post('email/instant-verify', function (Request $request) {
        $user = $request->user();
        if ($user && ! $user->hasVerifiedEmail()) {
            $user->markEmailAsVerified();
            event(new Verified($user));
        }

        return redirect()->intended(route('dashboard'));
    })->name('verification.instant');

    Route::get('dashboard', DashboardController::class)->name('dashboard');
    Route::get('account', AccountController::class)->name('account');

    Route::resource('members', MemberController::class)->except(['create', 'show', 'edit']);
    Route::resource('branches', BranchController::class)->except(['create', 'show', 'edit']);

    Route::get('nfc-cards', [NfcCardController::class, 'index'])->name('nfc-cards.index');
    Route::post('nfc-cards', [NfcCardController::class, 'store'])->name('nfc-cards.store');
    Route::patch('nfc-cards/{nfcCard}', [NfcCardController::class, 'update'])->name('nfc-cards.update');
    Route::delete('nfc-cards/{nfcCard}', [NfcCardController::class, 'destroy'])->name('nfc-cards.destroy');
});

require __DIR__.'/settings.php';
