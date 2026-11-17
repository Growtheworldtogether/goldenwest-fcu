<?php

use App\Http\Controllers\InquiryController;
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
Route::post('/join', [InquiryController::class, 'join'])->middleware('throttle:6,1')->name('join.send');
