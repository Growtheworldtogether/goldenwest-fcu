<?php

use App\Models\BrandSetting;
use App\Models\Media;
use App\Models\PageContent;
use App\Models\PageRevision;
use App\Services\Brand;
use App\Services\SiteContent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

beforeEach(function () {
    seedReference();
    Storage::fake('local');
    Brand::forget();
    SiteContent::$preview = false;
    SiteContent::$previewPage = null;
    \Illuminate\Support\Facades\Cache::flush();
});

function pngUpload(int $w = 120, int $h = 120, string $name = 'logo.png'): UploadedFile
{
    $im = imagecreatetruecolor($w, $h);
    imagefill($im, 0, 0, imagecolorallocate($im, 20, 64, 58));
    $path = tempnam(sys_get_temp_dir(), 'img').'.png';
    imagepng($im, $path);

    return new UploadedFile($path, $name, 'image/png', null, true);
}

function brandPayload(array $over = []): array
{
    return array_merge([
        'name' => 'Golden West Federal Credit Union', 'short_name' => 'Golden West', 'descriptor' => 'Federal Credit Union', 'tagline' => 'Banking built around you',
        'brand_color' => '#14403a', 'highlight_color' => '#f5bd02', 'support_email' => 'care@goldenwestfcu.example',
        'support_phone' => '800-555-0147', 'support_hours' => 'Mon-Fri 7am - 8pm PT', 'sender_name' => 'Golden West Federal Credit Union',
    ], $over);
}

it('keeps the credit union name out of every template', function () {
    $hits = collect(\Illuminate\Support\Facades\File::allFiles(resource_path('views')))
        ->filter(fn ($f) => str_contains($f->getContents(), 'Golden West'))->map->getRelativePathname()->all();
    expect($hits)->toBe([]);
});

it('only lets staff with the site.edit permission reach branding and content', function () {
    $this->get('/admin/branding')->assertRedirect('/login');
    $this->actingAs(makeStaff('Manager'))->get('/admin/branding')->assertForbidden();
    $this->actingAs(makeStaff('Manager'))->get('/admin/site')->assertForbidden();
    $this->actingAs(makeStaff('Super Admin'))->get('/admin/branding')->assertOk()->assertSee('Logo and icon');
    $this->actingAs(makeStaff('Super Admin'))->get('/admin/site')->assertOk()->assertSee('Site content');
});

it('lets a Super Admin grant branding to Managers', function () {
    $super = makeStaff('Super Admin');
    $this->actingAs($super)->put('/admin/settings', ['managers_edit_site' => '1'])->assertRedirect();
    $manager = makeStaff('Manager', name: 'Daniel Brooks');
    $this->actingAs($manager)->get('/admin/branding')->assertOk();
    $this->actingAs($super)->put('/admin/settings', [])->assertRedirect();
    app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
    $this->actingAs($manager->fresh())->get('/admin/branding')->assertForbidden();
});

it('changes the name, phone and colours everywhere after saving branding', function () {
    $super = makeStaff('Super Admin');
    $this->actingAs($super)->put('/admin/branding', brandPayload(['name' => 'Harbor Light Credit Union', 'short_name' => 'Harbor Light', 'support_phone' => '888-555-0123', 'brand_color' => '#12355b']))->assertSessionHasNoErrors();
    auth()->logout();
    $home = $this->get('/')->assertOk();
    $home->assertSee('Harbor Light Credit Union')->assertSee('888-555-0123')->assertSee('--navy:#12355b', false)->assertDontSee('Golden West');
    $this->get('/about')->assertSee('Harbor Light')->assertDontSee('Golden West');
    $this->get('/login')->assertSee('<title>Log in | Harbor Light Credit Union', false);
    expect(config('app.name'))->not->toBeNull();
    $this->assertDatabaseHas('audit_logs', ['action' => 'brand.updated']);
});

it('refuses colour pairs that are hard to read', function () {
    $super = makeStaff('Super Admin');
    $this->actingAs($super)->put('/admin/branding', brandPayload(['brand_color' => '#f1f1a0']))->assertSessionHasErrors('brand_color');
    $this->actingAs($super)->put('/admin/branding', brandPayload(['brand_color' => '#14403a', 'highlight_color' => '#1b4a43']))->assertSessionHasErrors('brand_color');
    expect(BrandSetting::count())->toBe(0);
});

it('stores a logo with a generated name, serves it, and refuses SVG and oversized files', function () {
    $super = makeStaff('Super Admin');
    $this->actingAs($super)->put('/admin/branding', brandPayload() + ['logo_light' => pngUpload(300, 80, '../../evil name.png')])->assertSessionHasNoErrors();
    $media = Media::first();
    expect($media->filename)->toMatch('/^[A-Za-z0-9]{40}\.png$/')->and($media->original_name)->not->toContain('/');
    Storage::disk('local')->assertExists('media/'.$media->filename);
    $this->get('/')->assertSee('class="brand-logo"', false)->assertSee('/media/'.$media->filename);
    $this->get('/media/'.$media->filename)->assertOk()->assertHeader('Content-Type', 'image/png')->assertHeader('X-Content-Type-Options', 'nosniff');

    $svg = UploadedFile::fake()->createWithContent('logo.svg', '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>');
    $this->actingAs($super)->put('/admin/branding', brandPayload() + ['logo_dark' => $svg])->assertSessionHasErrors('logo_dark');
    $fakePng = UploadedFile::fake()->createWithContent('x.png', '<?php echo 1; ?>');
    $this->actingAs($super)->put('/admin/branding', brandPayload() + ['logo_dark' => $fakePng])->assertSessionHasErrors('logo_dark');
    $this->actingAs($super)->put('/admin/branding', brandPayload() + ['favicon' => pngUpload(100, 40)])->assertSessionHasErrors('favicon');
    expect(Media::count())->toBe(1);
});

it('serves a favicon even before one is uploaded', function () {
    $this->get('/brand/favicon')->assertOk()->assertHeader('Content-Type', 'image/svg+xml');
});

it('shows the original text until something is published, and keeps drafts private', function () {
    $this->get('/about')->assertSee('Owned by the people we serve');
    $super = makeStaff('Super Admin');
    $page = [
        'heading' => 'Your credit union since 1958', 'intro' => 'Members own us.',
        'story' => '<p>Our <strong>new</strong> story.</p>',
        'cta_title' => 'Join today', 'cta_text' => '', 'cta_button' => ['label' => 'Join now', 'url' => '/join'],
    ];
    $this->actingAs($super)->put('/admin/site/about/draft', ['then' => 'draft', 'fields' => $page])->assertSessionHasNoErrors();
    auth()->logout();
    $this->get('/about')->assertSee('Owned by the people we serve')->assertDontSee('Your credit union since 1958');
    $this->actingAs($super)->get('/admin/site/about/preview')->assertSee('Your credit union since 1958')->assertSee('Preview of unpublished changes');
    SiteContent::$preview = false; SiteContent::$previewPage = null;
    $this->actingAs($super)->post('/admin/site/about/publish')->assertRedirect();
    auth()->logout();
    $this->get('/about')->assertSee('Your credit union since 1958')->assertSee('Our <strong>new</strong> story.', false)->assertSee('Join now');
    expect(PageContent::where('page', 'about')->first()->version)->toBe(1)->and(PageRevision::count())->toBe(1);
    $this->assertDatabaseHas('audit_logs', ['action' => 'content.published']);
});

it('rolls back to an earlier version and resets to the original text', function () {
    $super = makeStaff('Super Admin');
    $base = ['intro' => 'Intro.', 'story' => '<p>Story.</p>', 'cta_title' => 'Join', 'cta_text' => '', 'cta_button' => ['label' => 'Go', 'url' => '/join']];
    foreach (['First headline', 'Second headline'] as $h) {
        $this->actingAs($super)->put('/admin/site/about/draft', ['then' => 'publish', 'fields' => $base + ['heading' => $h]])->assertSessionHasNoErrors();
    }
    $this->get('/about')->assertSee('Second headline');
    $first = PageRevision::where('version', 1)->first();
    $this->actingAs($super)->post("/admin/site/about/rollback/{$first->id}")->assertRedirect();
    $this->get('/about')->assertSee('First headline')->assertDontSee('Second headline');
    expect(PageRevision::count())->toBe(3);
    $this->actingAs($super)->post('/admin/site/about/reset')->assertRedirect();
    $this->get('/about')->assertSee('Owned by the people we serve')->assertDontSee('First headline');
});

it('strips scripts and unsafe links from rich text and refuses unsafe button links', function () {
    $super = makeStaff('Super Admin');
    $evil = '<p onclick="x()">Hello <script>alert(1)</script><a href="javascript:alert(1)">bad</a> <a href="https://ok.example/page" target="_blank" onmouseover="y()">good</a><img src=x onerror=alert(1)><iframe src="//e"></iframe><ul><li>one</li></ul></p>';
    expect(SiteContent::sanitize($evil))->not->toContain('script')->not->toContain('javascript:')->not->toContain('onclick')->not->toContain('onmouseover')->not->toContain('<img')->not->toContain('iframe')
        ->toContain('href="https://ok.example/page"')->toContain('<li>one</li>')->toContain('rel="noopener"');
    $base = ['heading' => 'H', 'intro' => 'I', 'story' => $evil, 'cta_title' => 'Join', 'cta_text' => '', 'cta_button' => ['label' => 'Go', 'url' => '/join']];
    $this->actingAs($super)->put('/admin/site/about/draft', ['then' => 'publish', 'fields' => $base])->assertSessionHasNoErrors();
    auth()->logout();
    $this->get('/about')->assertDontSee('<script>alert(1)</script>', false)->assertDontSee('javascript:', false);
    $this->actingAs($super)->put('/admin/site/about/draft', ['then' => 'draft', 'fields' => ['cta_button' => ['label' => 'Go', 'url' => 'javascript:alert(1)']] + $base])->assertSessionHasErrors('fields.cta_button');
});

it('edits the home hero slides and uploads a slide photo with required alt text', function () {
    $super = makeStaff('Super Admin');
    $slides = [
        ['title' => 'Rates you can plan around', 'text' => 'Fixed rates and clear terms.', 'button' => ['label' => 'See rates', 'url' => '/rates'], 'image' => ['alt' => '']],
    ];
    $this->actingAs($super)->put('/admin/site/home/draft', ['then' => 'publish', 'fields' => ['slides' => $slides]])->assertSessionHasNoErrors();
    auth()->logout();
    $this->get('/')->assertSee('Rates you can plan around')->assertSee('See rates');

    $withFile = fn (string $alt) => $this->actingAs($super)->put('/admin/site/home/draft', [
        'then' => 'publish',
        'fields' => ['slides' => [array_replace_recursive($slides[0], ['image' => ['alt' => $alt, 'file' => pngUpload(1600, 900, 'hero.png')]])]],
    ]);
    $withFile('')->assertSessionHasErrors();
    expect(Media::count())->toBe(0);
    $withFile('Members at the Oakland branch')->assertSessionHasNoErrors();
    auth()->logout();
    $this->get('/')->assertSee('/media/', false);
    expect(Media::count())->toBe(1);
});

it('bumps the legal version when a legal page is published and keeps tokens in sync with branding', function () {
    $super = makeStaff('Super Admin');
    $this->get('/privacy')->assertSee('Golden West');
    $fields = ['heading' => 'Privacy', 'intro' => 'How we protect information.', 'updated' => 'December 1, 2026', 'body' => '<h2>What we collect</h2><p>Call {phone} about {short_name}.</p>'];
    $this->actingAs($super)->put('/admin/site/privacy/draft', ['then' => 'publish', 'fields' => $fields])->assertSessionHasNoErrors();
    auth()->logout();
    $this->get('/privacy')->assertSee('Last updated December 1, 2026')->assertSee('Call 800-555-0147 about Golden West.');
    expect(PageContent::where('page', 'privacy')->first()->version)->toBe(1);
});

it('fills brand tokens in articles, search and registration from the Branding page', function () {
    $this->actingAs(makeStaff('Super Admin'))->put('/admin/branding', brandPayload(['name' => 'Harbor Federal Credit Union', 'short_name' => 'Harbor', 'support_phone' => '800-555-0199']))->assertSessionHasNoErrors();
    auth()->logout();

    $this->get('/learn/fake-bank-texts')->assertOk()->assertSee('Harbor will never ask')->assertDontSee('Golden West')->assertDontSee('{short_name}');
    $this->get('/register')->assertOk()->assertSee('A family member is a Harbor member')->assertDontSee('{short_name}');
    $this->get('/search?q=join')->assertOk()->assertSee('Join Harbor');
});

it('keeps the company name out of config text', function () {
    foreach (['articles', 'site'] as $f) {
        expect(file_get_contents(config_path($f.'.php')))->not->toContain('Golden West');
    }
});
