<?php

uses(Illuminate\Foundation\Testing\RefreshDatabase::class);

$pages = [
    '/', '/accounts', '/loans', '/rates', '/business', '/digital-banking', '/security', '/help',
    '/about', '/community', '/careers', '/contact', '/appointments', '/join', '/learn',
    '/privacy', '/terms', '/disclosures', '/accessibility', '/sitemap', '/search', '/login',
    '/learn/read-your-statement', '/learn/fake-bank-texts', '/learn/held-transaction', '/learn/goal-savings', '/learn/used-car-loan',
];

it('renders every public page', function (string $url) {
    $this->get($url)->assertOk()->assertSee('Golden West');
})->with($pages);

it('keeps project wording out of the visible interface', function (string $url) {
    $text = strtolower(strip_tags($this->get($url)->getContent()));
    $text = str_replace('independent portfolio project', '', $text);
    foreach (['fictional', 'demo ', 'placeholder', 'lorem', 'test transaction', 'user 1'] as $bad) {
        expect($text)->not->toContain($bad);
    }
})->with($pages);

it('returns 404 for an unknown article', function () {
    $this->get('/learn/not-a-real-article')->assertNotFound();
});

it('searches the site', function () {
    $this->get('/search?q=auto')->assertOk()->assertSee('Loans');
});

it('stores contact, appointment and join requests', function () {
    $this->post('/contact', ['name' => 'Maria Alvarez', 'email' => 'maria.alvarez@example.com', 'topic' => 'Accounts', 'message' => 'Question about checking.'])->assertRedirect();
    $this->post('/appointments', ['name' => 'James Carter', 'email' => 'james.carter@example.com', 'phone' => '510-555-0123', 'topic' => 'Business banking', 'location' => 'Oakland', 'preferred_when' => 'Weekdays after 3pm'])->assertRedirect();
    $this->post('/join', ['name' => 'Linda Nguyen', 'email' => 'linda.nguyen@example.com', 'topic' => 'Checking'])->assertRedirect();
    expect(\App\Models\Inquiry::count())->toBe(3);
});

it('validates the contact form', function () {
    $this->post('/contact', [])->assertSessionHasErrors(['name', 'email', 'topic', 'message']);
});
