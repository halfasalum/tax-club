<?php

use App\Http\Controllers\Web\EventController;
use App\Http\Controllers\Web\LearnController;
use App\Http\Controllers\Web\PageController;
use App\Http\Controllers\Web\SitemapController;
use App\Livewire\Admin\Dashboard as AdminDashboard;
use App\Livewire\Admin\Institutions\Form as InstitutionForm;
use App\Livewire\Admin\Institutions\Import as InstitutionImport;
use App\Livewire\Admin\Institutions\Index as InstitutionIndex;
use App\Livewire\Admin\Institutions\Show as InstitutionShow;
use App\Livewire\Admin\Registrations as AdminRegistrations;
use App\Livewire\Auth\ForgotPassword;
use App\Livewire\Auth\Login;
use App\Livewire\Auth\Register;
use App\Livewire\Member\Dashboard as MemberDashboard;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Public website (server-rendered, indexable)
|--------------------------------------------------------------------------
| Kiswahili at the root, English under /en. Each route is registered for
| both locales so pages can declare hreflang alternates.
*/

$publicRoutes = function (): void {
    Route::get('/', [PageController::class, 'home'])->name('home');
    Route::get('/about', [PageController::class, 'about'])->name('about');
    Route::get('/learn', [LearnController::class, 'index'])->name('learn.index');
    Route::get('/learn/{slug}', [LearnController::class, 'show'])->name('learn.show');
    Route::get('/events', [EventController::class, 'index'])->name('events.index');
    Route::get('/events/{slug}', [EventController::class, 'show'])->name('events.show');
};

Route::middleware('locale:sw')->name('sw.')->group($publicRoutes);
Route::middleware('locale:en')->prefix('en')->name('en.')->group($publicRoutes);

Route::get('/sitemap.xml', [SitemapController::class, 'sitemap'])->name('sitemap');
Route::get('/robots.txt', [SitemapController::class, 'robots'])->name('robots');

/*
|--------------------------------------------------------------------------
| Accounts
|--------------------------------------------------------------------------
*/

Route::middleware('guest')->group(function (): void {
    Route::get('/login', Login::class)->name('login');
    Route::get('/register', Register::class)->name('register');
    Route::get('/forgot-password', ForgotPassword::class)->name('password.request');
});

Route::post('/logout', function (Request $request) {
    Auth::guard('web')->logout();
    $request->session()->invalidate();
    $request->session()->regenerateToken();

    return redirect()->route(app()->getLocale().'.home');
})->middleware('auth')->name('logout');

/*
|--------------------------------------------------------------------------
| Member portal and Administration Portal (not indexed)
|--------------------------------------------------------------------------
*/

Route::middleware('auth')->group(function (): void {
    Route::get('/app', MemberDashboard::class)->name('member.dashboard');

    Route::middleware('admin')->prefix('admin')->name('admin.')->group(function (): void {
        Route::get('/', AdminDashboard::class)->name('dashboard');
        Route::get('/registrations', AdminRegistrations::class)
            ->middleware('permission:view_registrations')
            ->name('registrations');

        Route::prefix('institutions')->name('institutions.')->group(function (): void {
            Route::get('/', InstitutionIndex::class)->middleware('permission:view_institutions')->name('index');
            Route::get('/new', InstitutionForm::class)->middleware('permission:add_institution')->name('create');
            Route::get('/import', InstitutionImport::class)->middleware('permission:import_institutions')->name('import');
            Route::get('/import/template.csv', function () {
                $rows = [
                    ['name', 'category', 'region', 'district', 'contact_name', 'contact_phone', 'contact_email'],
                    ['Azania Secondary School', 'secondary', 'Dar es Salaam', 'Ilala', 'Head of School', '0712345678', 'azania@example.go.tz'],
                ];

                return response()->streamDownload(function () use ($rows): void {
                    $out = fopen('php://output', 'w');
                    foreach ($rows as $row) {
                        fputcsv($out, $row);
                    }
                    fclose($out);
                }, 'institutions-template.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
            })->middleware('permission:import_institutions')->name('template');
            Route::get('/{institution}', InstitutionShow::class)->middleware('permission:view_institutions')->name('show');
            Route::get('/{institution}/edit', InstitutionForm::class)->middleware('permission:edit_institution')->name('edit');
        });
    });
});
