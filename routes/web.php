<?php

use App\Http\Controllers\InquiryController;
use App\Http\Controllers\FileController;
use App\Http\Controllers\MediaController;
use App\Http\Controllers\Admin;
use App\Http\Controllers\Portal;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/setup/{step?}', [\App\Http\Controllers\SetupController::class, 'show'])->name('setup');
Route::post('/setup/{step}', [\App\Http\Controllers\SetupController::class, 'save'])->name('setup.save');

Route::view('/', 'home')->name('home');

foreach ([
    'accounts', 'loans', 'business', 'digital-banking', 'security', 'help',
    'about', 'community', 'careers', 'contact', 'appointments', 'join',
    'privacy', 'terms', 'disclosures', 'accessibility',
] as $page) {
    Route::view('/'.$page, 'pages.'.$page)->name($page);
}

Route::get('/rates', fn () => view('pages.rates', ['rates' => \App\Services\SiteContent::resolve(config('site.rates'))]))->name('rates');

Route::get('/learn', fn () => view('pages.learn', ['articles' => \App\Services\SiteContent::resolve(config('articles'))]))->name('learn');
Route::get('/learn/{slug}', function (string $slug) {
    $articles = collect(\App\Services\SiteContent::resolve(config('articles')));
    $article = $articles->firstWhere('slug', $slug);
    abort_unless($article, 404);

    return view('pages.article', [
        'article' => $article,
        'more' => $articles->where('slug', '!=', $slug)->take(3),
    ]);
})->name('learn.show');

Route::get('/sitemap', fn () => view('pages.sitemap', [
    'groups' => collect(\App\Services\SiteContent::resolve(config('site.pages')))->groupBy(0),
]))->name('sitemap');

Route::get('/search', function (Request $request) {
    $q = trim((string) $request->query('q', ''));
    $results = collect();

    if (mb_strlen($q) >= 2) {
        $terms = preg_split('/\s+/', mb_strtolower($q));
        $pages = collect(\App\Services\SiteContent::resolve(config('site.pages')))->map(fn ($p) => [
            'title' => $p[1], 'url' => $p[2], 'area' => $p[0], 'hay' => mb_strtolower($p[1].' '.$p[3]),
        ]);
        $articles = collect(\App\Services\SiteContent::resolve(config('articles')))->map(fn ($a) => [
            'title' => $a['title'], 'url' => '/learn/'.$a['slug'], 'area' => $a['cat'], 'hay' => mb_strtolower($a['title'].' '.$a['intro']),
        ]);
        $results = $pages->concat($articles)->filter(
            fn ($r) => collect($terms)->every(fn ($t) => str_contains($r['hay'], $t))
        )->values();
    }

    return view('pages.search', ['q' => $q, 'results' => $results]);
})->name('search');

// Uploaded images and the favicon. Files live in private storage and are served here, so no storage link is needed.
Route::get('/media/{filename}', [MediaController::class, 'show'])->where('filename', '[A-Za-z0-9]{40}\.(png|jpg|webp)')->name('media.show');
Route::get('/brand/favicon', [MediaController::class, 'favicon'])->name('brand.favicon');

Route::post('/contact', [InquiryController::class, 'contact'])->middleware('throttle:6,1')->name('contact.send');
Route::post('/appointments', [InquiryController::class, 'appointment'])->middleware('throttle:6,1')->name('appointments.send');

// Shown once, right after joining. Needs a signed-in member but not a confirmed email.
Route::get('/welcome', Portal\WelcomeController::class)->middleware(['auth', 'member.area'])->name('welcome');

// Online banking. Members must be signed in and have a verified email.
Route::middleware(['auth', 'verified', 'member.area', 'throttle:portal'])->group(function () {
    Route::get('/dashboard', Portal\DashboardController::class)->name('dashboard');
    Route::get('/activity', [Portal\ActivityController::class, 'index'])->name('activity');
    Route::get('/activity/export', [Portal\ActivityController::class, 'export'])->name('activity.export');

    Route::get('/my-business', [Portal\BusinessController::class, 'show'])->name('business.show');
    Route::post('/my-business', [Portal\BusinessController::class, 'store'])->middleware('throttle:5,1')->name('business.store');
    Route::post('/my-business/accounts', [Portal\BusinessController::class, 'open'])->middleware('throttle:10,1')->name('business.open');
    Route::get('/accounts/open', [Portal\AccountController::class, 'create'])->name('accounts.create');
    Route::post('/accounts/open', [Portal\AccountController::class, 'store'])->name('accounts.store');
    Route::get('/accounts/{account}', [Portal\ActivityController::class, 'account'])->whereNumber('account')->name('accounts.show');
    Route::get('/accounts/{account}/export', [Portal\ActivityController::class, 'export'])->whereNumber('account')->name('accounts.export');

    Route::get('/transfers/new', [Portal\TransferController::class, 'create'])->name('transfers.create');
    Route::post('/transfers', [Portal\TransferController::class, 'store'])->middleware('throttle:20,1')->name('transfers.store');
    Route::get('/transactions/{transaction:reference}', [Portal\TransactionController::class, 'show'])->name('transactions.show');

    Route::get('/deposits', [Portal\DepositController::class, 'index'])->name('deposits.index');
    Route::get('/deposits/check', [Portal\DepositController::class, 'createCheck'])->name('deposits.check');
    Route::post('/deposits/check', [Portal\DepositController::class, 'storeCheck'])->middleware('throttle:10,1')->name('deposits.check.store');
    Route::get('/deposits/from-another-bank', [Portal\DepositController::class, 'createAch'])->name('deposits.ach');
    Route::post('/deposits/from-another-bank', [Portal\DepositController::class, 'storeAch'])->middleware('throttle:10,1')->name('deposits.ach.store');

    Route::get('/payees', [Portal\PayeeController::class, 'index'])->name('payees.index');
    Route::post('/payees', [Portal\PayeeController::class, 'store'])->middleware('throttle:10,1')->name('payees.store');
    Route::delete('/payees/{payee}', [Portal\PayeeController::class, 'destroy'])->whereNumber('payee')->name('payees.destroy');
    Route::get('/transfers/external', [Portal\PayeeController::class, 'create'])->name('transfers.external');
    Route::post('/transfers/external', [Portal\PayeeController::class, 'send'])->middleware('throttle:10,1')->name('transfers.external.store');

    Route::middleware('feature:wires')->group(function () {
        Route::get('/wires', [Portal\WireController::class, 'index'])->name('wires.index');
        Route::get('/wires/new', [Portal\WireController::class, 'create'])->name('wires.create');
        Route::delete('/wires/recipients/{recipient}', [Portal\WireController::class, 'destroy'])->whereNumber('recipient')->name('wires.recipients.destroy');
        Route::post('/wires', [Portal\WireController::class, 'store'])->middleware('throttle:10,1')->name('wires.store');
    });

    Route::get('/verification', [Portal\KycController::class, 'show'])->name('kyc.show');
    Route::post('/verification', [Portal\KycController::class, 'store'])->middleware('throttle:10,1')->name('kyc.store');
    Route::post('/transactions/{transaction:reference}/contest', [Portal\TransactionController::class, 'contest'])->middleware('throttle:10,1')->name('transactions.contest');
    Route::get('/support', [Portal\TicketController::class, 'index'])->name('tickets.index');
    Route::get('/support/new', [Portal\TicketController::class, 'create'])->name('tickets.create');
    Route::post('/support', [Portal\TicketController::class, 'store'])->middleware('throttle:10,1')->name('tickets.store');
    Route::get('/tickets/{ticket:reference}', [Portal\TicketController::class, 'show'])->name('tickets.show');
    Route::post('/tickets/{ticket:reference}/reply', [Portal\TicketController::class, 'reply'])->middleware('throttle:20,1')->name('tickets.reply');
    Route::post('/tickets/{ticket:reference}/resolve', [Portal\TicketController::class, 'resolve'])->name('tickets.resolve');

    Route::get('/beneficiaries', [Portal\BeneficiaryController::class, 'index'])->name('beneficiaries.index');
    Route::get('/beneficiaries/{account}', [Portal\BeneficiaryController::class, 'edit'])->whereNumber('account')->name('beneficiaries.edit');
    Route::put('/beneficiaries/{account}', [Portal\BeneficiaryController::class, 'update'])->whereNumber('account')->name('beneficiaries.update');
    Route::get('/joint', [Portal\JointController::class, 'index'])->name('joint.index');
    Route::post('/joint/invite', [Portal\JointController::class, 'invite'])->middleware('throttle:10,1')->name('joint.invite');
    Route::post('/joint/removal', [Portal\JointController::class, 'requestRemoval'])->middleware('throttle:10,1')->name('joint.removal');
    Route::post('/joint/leave', [Portal\JointController::class, 'leave'])->name('joint.leave');
    Route::post('/joint/{req:reference}/respond', [Portal\JointController::class, 'respond'])->name('joint.respond');
    Route::post('/joint/{req:reference}/consent', [Portal\JointController::class, 'consent'])->name('joint.consent');
    Route::post('/joint/{req:reference}/cancel', [Portal\JointController::class, 'cancel'])->name('joint.cancel');
    Route::get('/goals', [Portal\GoalController::class, 'index'])->name('goals.index');
    Route::post('/goals', [Portal\GoalController::class, 'store'])->middleware('throttle:10,1')->name('goals.store');
    Route::post('/goals/{goal}/move', [Portal\GoalController::class, 'contribute'])->middleware('throttle:20,1')->whereNumber('goal')->name('goals.move');
    Route::post('/goals/{goal}/auto', [Portal\GoalController::class, 'auto'])->whereNumber('goal')->name('goals.auto');
    Route::post('/goals/{goal}/close', [Portal\GoalController::class, 'close'])->whereNumber('goal')->name('goals.close');
    Route::get('/bills', [Portal\BillController::class, 'index'])->name('bills.index');
    Route::post('/bills/billers', [Portal\BillController::class, 'storeBiller'])->middleware('throttle:10,1')->name('bills.billers.store');
    Route::delete('/bills/billers/{biller}', [Portal\BillController::class, 'destroyBiller'])->whereNumber('biller')->name('bills.billers.destroy');
    Route::post('/bills', [Portal\BillController::class, 'store'])->middleware('throttle:15,1')->name('bills.store');
    Route::post('/bills/{payment}/cancel', [Portal\BillController::class, 'cancel'])->whereNumber('payment')->name('bills.cancel');
    Route::get('/overdraft', [Portal\OverdraftController::class, 'index'])->name('overdraft.index');
    Route::put('/overdraft/{account}', [Portal\OverdraftController::class, 'update'])->whereNumber('account')->name('overdraft.update');
    Route::get('/insights', [Portal\InsightsController::class, 'index'])->name('insights.index');
    Route::post('/insights/budgets', [Portal\InsightsController::class, 'budget'])->name('insights.budget');
    Route::post('/insights/transactions/{transaction:reference}/category', [Portal\InsightsController::class, 'category'])->name('insights.category');
    Route::middleware('feature:crypto')->prefix('crypto')->name('crypto.')->group(function () {
        Route::get('/', [Portal\CryptoController::class, 'index'])->name('index');
        Route::get('/fund', [Portal\CryptoController::class, 'fund'])->name('fund');
        Route::post('/fund/address', [Portal\CryptoController::class, 'requestAddress'])->middleware('throttle:10,1')->name('address');
        Route::post('/fund', [Portal\CryptoController::class, 'declare'])->middleware('throttle:10,1')->name('declare');
        Route::get('/send', [Portal\CryptoController::class, 'create'])->name('send');
        Route::post('/send', [Portal\CryptoController::class, 'store'])->middleware('throttle:10,1')->name('send.store');
        Route::post('/transactions/{tx:reference}/cancel', [Portal\CryptoController::class, 'cancel'])->name('cancel');
    });
    Route::middleware('feature:loans')->prefix('borrow')->name('borrow.')->group(function () {
        Route::get('/', [Portal\LoanController::class, 'index'])->name('index');
        Route::get('/apply', [Portal\LoanController::class, 'create'])->name('create');
        Route::post('/apply', [Portal\LoanController::class, 'store'])->middleware('throttle:5,1')->name('store');
        Route::get('/applications/{application:reference}', [Portal\LoanController::class, 'application_show'])->name('application');
        Route::post('/applications/{application:reference}/reply', [Portal\LoanController::class, 'reply'])->name('reply');
        Route::post('/applications/{application:reference}/withdraw', [Portal\LoanController::class, 'withdraw'])->name('withdraw');
        Route::get('/loans/{loan:reference}', [Portal\LoanController::class, 'show'])->name('show');
        Route::post('/loans/{loan:reference}/draw', [Portal\LoanController::class, 'draw'])->middleware('throttle:10,1')->name('draw');
        Route::post('/loans/{loan:reference}/pay', [Portal\LoanController::class, 'pay'])->middleware('throttle:10,1')->name('pay');
        Route::post('/loans/{loan:reference}/autopay', [Portal\LoanController::class, 'autopay'])->name('autopay');
    });
    Route::get('/card', [Portal\CardController::class, 'show'])->name('card.show');
    Route::post('/card', [Portal\CardController::class, 'request'])->middleware('throttle:5,1')->name('card.request');
    Route::post('/card/{card}/reveal', [Portal\CardController::class, 'reveal'])->middleware('throttle:10,1')->whereNumber('card')->name('card.reveal');
    Route::post('/card/{card}/freeze', [Portal\CardController::class, 'freeze'])->whereNumber('card')->name('card.freeze');
    Route::post('/card/{card}/limits', [Portal\CardController::class, 'limits'])->whereNumber('card')->name('card.limits');
    Route::post('/card/{card}/cancel', [Portal\CardController::class, 'cancel'])->whereNumber('card')->name('card.cancel');
    Route::post('/card/{card}/replace', [Portal\CardController::class, 'replace'])->middleware('throttle:3,1')->whereNumber('card')->name('card.replace');
    Route::post('/card/{card}/physical', [Portal\CardController::class, 'orderPhysical'])->middleware('throttle:5,1')->whereNumber('card')->name('card.physical');
    Route::post('/card/physical/{order}/cancel', [Portal\CardController::class, 'cancelPhysical'])->whereNumber('order')->name('card.physical.cancel');
    Route::post('/announcements/{announcement}/dismiss', [Portal\AnnouncementController::class, 'dismiss'])->whereNumber('announcement')->name('announcements.dismiss');

    Route::get('/security-settings', Portal\SecurityController::class)->name('security.settings');
    Route::post('/security-settings/sign-out-others', [Portal\SecurityController::class, 'signOutOthers'])->middleware('throttle:5,1')->name('security.signout');
    Route::post('/security-settings/lock', [Portal\SecurityController::class, 'lockAccounts'])->middleware('throttle:5,1')->name('security.lock');
    Route::get('/profile', [Portal\ProfileController::class, 'edit'])->name('profile');
    Route::put('/profile/notifications', [Portal\ProfileController::class, 'preferences'])->name('profile.notifications');
});

// Private files: check images and identity documents. The controller decides who may see each one.
Route::middleware('auth')->group(function () {
    Route::get('/security-check', [Portal\SecurityQuestionController::class, 'check'])->name('security.check');
    Route::post('/security-check', [Portal\SecurityQuestionController::class, 'verify'])->middleware('throttle:20,1')->name('security.check.verify');
    Route::post('/security-check/resend', [Portal\SecurityQuestionController::class, 'resend'])->middleware('throttle:5,1')->name('security.check.resend');
    Route::get('/security-questions/setup', [Portal\SecurityQuestionController::class, 'setup'])->name('security.questions.setup');
    Route::post('/security-questions/setup', [Portal\SecurityQuestionController::class, 'save'])->middleware('throttle:10,1')->name('security.questions.setup.save');
    Route::get('/security-questions', [Portal\SecurityQuestionController::class, 'setup'])->middleware('password.confirm')->name('security.questions.edit');
    Route::post('/security-questions', [Portal\SecurityQuestionController::class, 'save'])->middleware(['password.confirm', 'throttle:10,1'])->name('security.questions.update');
    Route::get('/files/check/{deposit}/{side}', [FileController::class, 'check'])->whereNumber('deposit')->name('files.check');
    Route::get('/files/ticket/{attachment}', [FileController::class, 'ticket'])->whereNumber('attachment')->name('files.ticket');
    Route::get('/files/kyc/{document}', [FileController::class, 'kyc'])->whereNumber('document')->name('files.kyc');
});

// Ends a "log in as member" session. The signed-in user is the member at this point, so it sits outside the staff group.
Route::post('/exit-member', [Admin\ImpersonationController::class, 'stop'])->middleware('auth')->name('impersonate.stop');

// Staff console. Needs a staff title, two-step verification on, and a short idle timeout.
Route::middleware(['auth', 'staff', 'throttle:staff'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/', Admin\DashboardController::class)->name('home');
    Route::get('/security', Portal\SecurityController::class)->middleware('password.confirm')->name('security');
    Route::get('/members', [Admin\MemberController::class, 'index'])->name('members.index');
    Route::get('/members/{user}', [Admin\MemberController::class, 'show'])->whereNumber('user')->name('members.show');
    Route::post('/members/{user}/locks', [Admin\RestrictionController::class, 'store'])->whereNumber('user')->name('locks.store');
    Route::post('/locks/{restriction}/unlock', [Admin\RestrictionController::class, 'unlock'])->whereNumber('restriction')->name('locks.unlock');
    Route::post('/members/{user}/adjustments', [Admin\AdjustmentController::class, 'store'])->whereNumber('user')->name('adjustments.store');
    Route::post('/members/{user}/history', [Admin\AccountHistoryController::class, 'store'])->whereNumber('user')->name('history.store');
    Route::post('/members/{user}/act-as', [Admin\ImpersonationController::class, 'start'])->whereNumber('user')->name('impersonate.start');
    Route::get('/queue', [Admin\QueueController::class, 'index'])->name('queue.index');
    Route::get('/queue/{transaction:reference}', [Admin\QueueController::class, 'show'])->name('queue.show');
    Route::post('/queue/{transaction:reference}/{action}', [Admin\QueueController::class, 'act'])->where('action', 'approve|reject|hold|release|reverse')->name('queue.act');
    Route::post('/members/{user}/cash-deposits', [Admin\DepositController::class, 'cash'])->whereNumber('user')->name('deposits.cash');
    Route::post('/members/{user}/payees/{payee}/waive', [Admin\DepositController::class, 'waive'])->whereNumber(['user', 'payee'])->name('payees.waive');
    Route::post('/members/{user}/features/{feature}', [Admin\FeatureController::class, 'toggle'])->whereNumber('user')->name('features.toggle');
    Route::get('/identity', [Admin\KycController::class, 'index'])->name('kyc.index');
    Route::get('/identity/{document}', [Admin\KycController::class, 'show'])->whereNumber('document')->name('kyc.show');
    Route::post('/identity/{document}/{action}', [Admin\KycController::class, 'decide'])->whereNumber('document')->where('action', 'approve|reject')->name('kyc.decide');
    Route::get('/audit', Admin\AuditController::class)->name('audit');
    Route::get('/loans', [Admin\LoanController::class, 'index'])->name('loans.index');
    Route::get('/loans/applications/{application:reference}', [Admin\LoanController::class, 'show'])->name('loans.show');
    Route::post('/loans/applications/{application:reference}', [Admin\LoanController::class, 'act'])->name('loans.act');
    Route::get('/loans/{loan:reference}', [Admin\LoanController::class, 'loan'])->name('loans.loan');
    Route::get('/cards', [Admin\CardController::class, 'index'])->name('cards.index');
    Route::post('/cards/{card}/decide', [Admin\CardController::class, 'decide'])->whereNumber('card')->name('cards.decide');
    Route::post('/cards/{card}/change', [Admin\CardController::class, 'change'])->whereNumber('card')->name('cards.change');
    Route::post('/cards/physical/{order}/decide', [Admin\CardController::class, 'decidePhysical'])->whereNumber('order')->name('cards.physical.decide');
    Route::get('/joint', [Admin\JointController::class, 'index'])->name('joint.index');
    Route::post('/joint/{req:reference}/decide', [Admin\JointController::class, 'decide'])->name('joint.decide');
    Route::post('/members/{user}/joint/remove', [Admin\JointController::class, 'remove'])->whereNumber('user')->name('joint.remove');
    Route::post('/members/{user}/remove', [Admin\MemberRemovalController::class, 'destroy'])->middleware(['password.confirm', 'throttle:5,1'])->whereNumber('user')->name('members.remove');
    Route::post('/members/{user}/deceased', [Admin\JointController::class, 'deceased'])->whereNumber('user')->name('deceased');
    Route::get('/crypto', [Admin\CryptoController::class, 'index'])->name('crypto.index');
    Route::post('/crypto/sends/{tx:reference}', [Admin\CryptoController::class, 'decideSend'])->name('crypto.send.decide');
    Route::post('/crypto/deposits/{tx:reference}', [Admin\CryptoController::class, 'decideDeposit'])->name('crypto.deposit.decide');
    Route::post('/crypto/rates', [Admin\CryptoController::class, 'refreshRates'])->name('crypto.rates');
    Route::put('/crypto/assets/{asset}', [Admin\CryptoController::class, 'asset'])->whereNumber('asset')->name('crypto.asset');
    Route::post('/members/{user}/crypto/address', [Admin\CryptoController::class, 'address'])->whereNumber('user')->name('crypto.address');
    Route::post('/members/{user}/crypto/funding', [Admin\CryptoController::class, 'funding'])->whereNumber('user')->name('crypto.funding');
    Route::post('/members/{user}/crypto/adjust', [Admin\CryptoController::class, 'adjust'])->whereNumber('user')->name('crypto.adjust');
    Route::get('/announcements', [Admin\AnnouncementController::class, 'index'])->name('announcements.index');
    Route::post('/announcements', [Admin\AnnouncementController::class, 'store'])->name('announcements.store');
    Route::put('/announcements/{announcement}', [Admin\AnnouncementController::class, 'update'])->whereNumber('announcement')->name('announcements.update');
    Route::delete('/announcements/{announcement}', [Admin\AnnouncementController::class, 'destroy'])->whereNumber('announcement')->name('announcements.destroy');
    Route::get('/tickets', [Admin\TicketController::class, 'index'])->name('tickets.index');
    Route::get('/tickets/{ticket:reference}', [Admin\TicketController::class, 'show'])->name('tickets.show');
    Route::post('/tickets/{ticket:reference}/reply', [Admin\TicketController::class, 'reply'])->name('tickets.reply');
    Route::put('/tickets/{ticket:reference}', [Admin\TicketController::class, 'update'])->name('tickets.update');
    Route::get('/inquiries', [Admin\InquiryController::class, 'index'])->name('inquiries.index');
    Route::post('/inquiries/{inquiry}/handle', [Admin\InquiryController::class, 'handle'])->whereNumber('inquiry')->name('inquiries.handle');
    Route::get('/branding', [Admin\BrandingController::class, 'edit'])->name('branding');
    Route::put('/branding', [Admin\BrandingController::class, 'update'])->name('branding.update');
    Route::get('/emails', [Admin\EmailPreviewController::class, 'index'])->name('emails');
    Route::get('/emails/preview/{key}', [Admin\EmailPreviewController::class, 'show'])->name('emails.show');
    Route::post('/emails/test', [Admin\EmailPreviewController::class, 'test'])->middleware('throttle:5,1')->name('emails.test');
    Route::get('/site', [Admin\SiteContentController::class, 'index'])->name('site.index');
    Route::get('/site/{page}', [Admin\SiteContentController::class, 'edit'])->name('site.edit');
    Route::put('/site/{page}/draft', [Admin\SiteContentController::class, 'saveDraft'])->name('site.draft');
    Route::get('/site/{page}/preview', [Admin\SiteContentController::class, 'preview'])->name('site.preview');
    Route::post('/site/{page}/publish', [Admin\SiteContentController::class, 'publish'])->name('site.publish');
    Route::delete('/site/{page}/draft', [Admin\SiteContentController::class, 'discard'])->name('site.discard');
    Route::post('/site/{page}/rollback/{revision}', [Admin\SiteContentController::class, 'rollback'])->whereNumber('revision')->name('site.rollback');
    Route::post('/site/{page}/reset', [Admin\SiteContentController::class, 'reset'])->name('site.reset');
    Route::get('/system', [Admin\SystemController::class, 'show'])->middleware('password.confirm')->name('system');
    Route::post('/system/check', [Admin\SystemController::class, 'check'])->middleware('throttle:10,1')->name('system.check');
    Route::post('/system/update', [Admin\SystemController::class, 'install'])->middleware(['password.confirm', 'throttle:5,1'])->name('system.update');
    Route::get('/system/progress', [Admin\SystemController::class, 'progress'])->name('system.progress');
    Route::post('/system/restore', [Admin\SystemController::class, 'restore'])->middleware(['password.confirm', 'throttle:5,1'])->name('system.restore');
    Route::post('/system/cancel', [Admin\SystemController::class, 'cancel'])->name('system.cancel');
    Route::get('/settings', [Admin\SettingsController::class, 'edit'])->name('settings');
    Route::put('/settings', [Admin\SettingsController::class, 'update'])->name('settings.update');
});
