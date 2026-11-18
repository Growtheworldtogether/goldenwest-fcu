<?php

use App\Services\Brand;
use App\Services\SiteContent;

if (! function_exists('brand')) {
    /** Credit union identity: brand('name'), brand('short_name'), brand('support_phone'), or brand() for everything. */
    function brand(?string $key = null): mixed
    {
        return $key === null ? Brand::all() : Brand::get($key);
    }
}

if (! function_exists('site_content')) {
    /** Published (or, while previewing, draft) page content: site_content('about.heading'). */
    function site_content(string $path, mixed $fallback = null): mixed
    {
        return SiteContent::value($path, $fallback);
    }
}

if (! function_exists('site_page')) {
    /** The content page key for the current request: the route name, or the page being previewed. */
    function site_page(): ?string
    {
        return SiteContent::currentPage();
    }
}

if (! function_exists('page_photo')) {
    /**
     * The banner photo for a public page. A file named public/images/pages/{page}.jpg wins; otherwise one of the
     * site photos stands in so the banner is never empty. Returns null for pages that have no banner photo.
     */
    function page_photo(?string $page): ?string
    {
        if (! $page) {
            return null;
        }
        if (is_file(public_path("images/pages/{$page}.jpg"))) {
            return asset("images/pages/{$page}.jpg");
        }
        $stand = [
            'accounts' => 'hero-1', 'business' => 'about', 'loans' => 'hero-3', 'digital-banking' => 'help', 'security' => 'learn-2', 'about' => 'about',
            'community' => 'branch', 'help' => 'help', 'rates' => 'learn-1', 'careers' => 'learn-3', 'contact' => 'branch', 'appointments' => 'branch', 'learn' => 'learn-3',
        ][$page] ?? null;

        return $stand && is_file(public_path("images/{$stand}.jpg")) ? asset("images/{$stand}.jpg") : null;
    }
}

if (! function_exists('body_photo')) {
    /** A photo inside a page, shown only once the file public/images/pages/{name}.jpg exists. */
    function body_photo(string $name): ?string
    {
        return is_file(public_path("images/pages/{$name}.jpg")) ? asset("images/pages/{$name}.jpg") : null;
    }
}
