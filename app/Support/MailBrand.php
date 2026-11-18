<?php

namespace App\Support;

use App\Services\Brand;

/** Colours and text for email, all taken from the branding settings so nothing about the credit union is fixed in a template. */
final class MailBrand
{
    /** @return array<string, mixed> */
    public static function palette(): array
    {
        $brand = (string) brand('brand_color');
        $highlight = (string) brand('highlight_color');
        $logo = Brand::logoUrl('dark');          // made for dark backgrounds
        $logoLight = $logo ? null : Brand::logoUrl('light');
        $headerBg = $logoLight ? '#ffffff' : $brand;

        return [
            'name' => brand('name'),
            'short' => brand('short_name'),
            'brand' => $brand,
            'highlight' => $highlight,
            'link' => brand('brand_link'),
            'on_brand' => Color::contrast('#ffffff', $brand) >= 4.5 ? '#ffffff' : '#111111',
            'on_highlight' => Color::contrast($brand, $highlight) >= 4.5 ? $brand : '#111111',
            'header_bg' => $headerBg,
            'header_text' => $headerBg === '#ffffff' ? $brand : (Color::contrast('#ffffff', $brand) >= 4.5 ? '#ffffff' : '#111111'),
            'logo' => $logo ?: $logoLight,
            'phone' => brand('support_phone'),
            'tel' => brand('tel'),
            'hours' => brand('support_hours'),
            'email' => brand('support_email'),
            'footer_text' => brand('footer_text'),
            'warning' => brand('short_name').' will never ask for your password or a one-time code by email, phone or text.',
        ];
    }

    /** Give the links and headings in rendered Markdown the brand colours (email clients ignore most stylesheets). */
    public static function paint(string $html): string
    {
        $p = self::palette();
        $html = preg_replace('/<a (?![^>]*style=)/', '<a style="color:'.$p['link'].';" ', $html);

        return preg_replace('/<h1>/', '<h1 style="color:'.$p['brand'].';">', $html);
    }
}
