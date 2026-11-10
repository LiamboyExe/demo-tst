<?php

use App\Http\Controllers\AccountController;
use App\Http\Controllers\BranchController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\MemberAccountController;
use App\Http\Controllers\MemberActivationController;
use App\Http\Controllers\MemberController;
use App\Http\Controllers\NfcCardController;
use App\Http\Controllers\PublicProfileController;
use Illuminate\Auth\Events\Verified;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    $user = auth()->user();

    if ($user === null) {
        return redirect()->route('login');
    }

    // Member accounts have no dashboard; their NFC profile is their page.
    if ($user->isMember() && $user->member !== null) {
        return redirect()->route('profile.show', $user->member);
    }

    return redirect()->route('dashboard');
})->name('home');

Route::get('p/{member:profile_slug}', [PublicProfileController::class, 'show'])->name('profile.show');

// First-time account activation from the member's own NFC profile.
Route::prefix('p/{member:profile_slug}/activate')->group(function () {
    Route::get('/', [MemberActivationController::class, 'show'])->name('activation.show');
    Route::post('/', [MemberActivationController::class, 'verify'])->middleware('throttle:6,1')->name('activation.verify');
    Route::get('/account', [MemberActivationController::class, 'create'])->name('activation.create');
    Route::post('/account', [MemberActivationController::class, 'store'])->middleware('throttle:6,1')->name('activation.store');
});

Route::middleware(['auth'])->group(function () {
    Route::post('email/instant-verify', function (Request $request) {
        $user = $request->user();
        if ($user && ! $user->hasVerifiedEmail()) {
            $user->markEmailAsVerified();
            event(new Verified($user));
        }

        return redirect()->intended(route('dashboard'));
    })->name('verification.instant');
});

Route::middleware(['auth', 'admin'])->group(function () {
    Route::get('dashboard', DashboardController::class)->name('dashboard');
    Route::get('account', AccountController::class)->name('account');

    Route::resource('members', MemberController::class)->except(['create', 'show', 'edit']);
    Route::post('members/{member}/account', [MemberAccountController::class, 'store'])->name('members.account.store');
    Route::get('members/{member}/account/credentials', [MemberAccountController::class, 'credentials'])->name('members.account.credentials');
    Route::delete('members/{member}/account', [MemberAccountController::class, 'destroy'])->name('members.account.destroy');
    Route::resource('branches', BranchController::class)->except(['create', 'show', 'edit']);

    Route::get('nfc-cards', [NfcCardController::class, 'index'])->name('nfc-cards.index');
    Route::post('nfc-cards', [NfcCardController::class, 'store'])->name('nfc-cards.store');
    Route::patch('nfc-cards/{nfcCard}', [NfcCardController::class, 'update'])->name('nfc-cards.update');
    Route::delete('nfc-cards/{nfcCard}', [NfcCardController::class, 'destroy'])->name('nfc-cards.destroy');
});

require __DIR__.'/settings.php';
