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
