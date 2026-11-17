<?php

use App\Http\Controllers\InquiryController;
use App\Http\Controllers\Admin;
use App\Http\Controllers\Portal;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::view('/', 'home')->name('home');

foreach ([
    'accounts', 'loans', 'business', 'digital-banking', 'security', 'help',
    'about', 'community', 'careers', 'contact', 'appointments', 'join',
    'privacy', 'terms', 'disclosures', 'accessibility',
] as $page) {
    Route::view('/'.$page, 'pages.'.$page)->name($page);
}

Route::get('/rates', fn () => view('pages.rates', ['rates' => config('site.rates')]))->name('rates');

Route::get('/learn', fn () => view('pages.learn', ['articles' => config('articles')]))->name('learn');
Route::get('/learn/{slug}', function (string $slug) {
    $articles = collect(config('articles'));
    $article = $articles->firstWhere('slug', $slug);
    abort_unless($article, 404);

    return view('pages.article', [
        'article' => $article,
        'more' => $articles->where('slug', '!=', $slug)->take(3),
    ]);
})->name('learn.show');

Route::get('/sitemap', fn () => view('pages.sitemap', [
    'groups' => collect(config('site.pages'))->groupBy(0),
]))->name('sitemap');

Route::get('/search', function (Request $request) {
    $q = trim((string) $request->query('q', ''));
    $results = collect();

    if (mb_strlen($q) >= 2) {
        $terms = preg_split('/\s+/', mb_strtolower($q));
        $pages = collect(config('site.pages'))->map(fn ($p) => [
            'title' => $p[1], 'url' => $p[2], 'area' => $p[0], 'hay' => mb_strtolower($p[1].' '.$p[3]),
        ]);
        $articles = collect(config('articles'))->map(fn ($a) => [
            'title' => $a['title'], 'url' => '/learn/'.$a['slug'], 'area' => $a['cat'], 'hay' => mb_strtolower($a['title'].' '.$a['intro']),
        ]);
        $results = $pages->concat($articles)->filter(
            fn ($r) => collect($terms)->every(fn ($t) => str_contains($r['hay'], $t))
        )->values();
    }

    return view('pages.search', ['q' => $q, 'results' => $results]);
})->name('search');

Route::post('/contact', [InquiryController::class, 'contact'])->middleware('throttle:6,1')->name('contact.send');
Route::post('/appointments', [InquiryController::class, 'appointment'])->middleware('throttle:6,1')->name('appointments.send');

// Online banking. Members must be signed in and have a verified email.
Route::middleware(['auth', 'verified', 'member.area'])->group(function () {
    Route::get('/dashboard', Portal\DashboardController::class)->name('dashboard');
    Route::get('/activity', [Portal\ActivityController::class, 'index'])->name('activity');
    Route::get('/activity/export', [Portal\ActivityController::class, 'export'])->name('activity.export');

    Route::get('/accounts/open', [Portal\AccountController::class, 'create'])->name('accounts.create');
    Route::post('/accounts/open', [Portal\AccountController::class, 'store'])->name('accounts.store');
    Route::get('/accounts/{account}', [Portal\ActivityController::class, 'account'])->whereNumber('account')->name('accounts.show');
    Route::get('/accounts/{account}/export', [Portal\ActivityController::class, 'export'])->whereNumber('account')->name('accounts.export');

    Route::get('/transfers/new', [Portal\TransferController::class, 'create'])->name('transfers.create');
    Route::post('/transfers', [Portal\TransferController::class, 'store'])->middleware('throttle:20,1')->name('transfers.store');
    Route::get('/transactions/{transaction:reference}', [Portal\TransactionController::class, 'show'])->name('transactions.show');

    Route::get('/security-settings', Portal\SecurityController::class)->name('security.settings');
    Route::get('/profile', [Portal\ProfileController::class, 'edit'])->name('profile');
    Route::put('/profile/notifications', [Portal\ProfileController::class, 'preferences'])->name('profile.notifications');
});

// Ends a "log in as member" session. The signed-in user is the member at this point, so it sits outside the staff group.
Route::post('/exit-member', [Admin\ImpersonationController::class, 'stop'])->middleware('auth')->name('impersonate.stop');

// Staff console. Needs a staff title, two-step verification on, and a short idle timeout.
Route::middleware(['auth', 'staff'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/', Admin\DashboardController::class)->name('home');
    Route::get('/security', Portal\SecurityController::class)->name('security');
    Route::get('/members', [Admin\MemberController::class, 'index'])->name('members.index');
    Route::get('/members/{user}', [Admin\MemberController::class, 'show'])->whereNumber('user')->name('members.show');
    Route::post('/members/{user}/locks', [Admin\RestrictionController::class, 'store'])->whereNumber('user')->name('locks.store');
    Route::post('/locks/{restriction}/unlock', [Admin\RestrictionController::class, 'unlock'])->whereNumber('restriction')->name('locks.unlock');
    Route::post('/members/{user}/adjustments', [Admin\AdjustmentController::class, 'store'])->whereNumber('user')->name('adjustments.store');
    Route::post('/members/{user}/act-as', [Admin\ImpersonationController::class, 'start'])->whereNumber('user')->name('impersonate.start');
    Route::get('/queue', [Admin\QueueController::class, 'index'])->name('queue.index');
    Route::get('/queue/{transaction:reference}', [Admin\QueueController::class, 'show'])->name('queue.show');
    Route::post('/queue/{transaction:reference}/{action}', [Admin\QueueController::class, 'act'])->where('action', 'approve|reject|hold|release|reverse')->name('queue.act');
    Route::get('/audit', Admin\AuditController::class)->name('audit');
    Route::get('/inquiries', [Admin\InquiryController::class, 'index'])->name('inquiries.index');
    Route::post('/inquiries/{inquiry}/handle', [Admin\InquiryController::class, 'handle'])->whereNumber('inquiry')->name('inquiries.handle');
    Route::get('/settings', [Admin\SettingsController::class, 'edit'])->name('settings');
    Route::put('/settings', [Admin\SettingsController::class, 'update'])->name('settings.update');
});
