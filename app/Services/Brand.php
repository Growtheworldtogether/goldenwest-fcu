<?php

namespace App\Services;

use App\Models\BrandSetting;
use App\Models\Media;
use App\Support\Color;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;

/** The credit union's identity. Reads the brand_settings row (cached), falling back to built-in defaults. */
final class Brand
{
    public const DEFAULTS = [
        'name' => 'Golden West Federal Credit Union',
        'short_name' => 'Golden West',
        'descriptor' => 'Federal Credit Union',
        'tagline' => 'Banking built around you',
        'brand_color' => '#14403a',
        'highlight_color' => '#f5bd02',
        'support_email' => 'care@goldenwestfcu.example',
        'support_phone' => '800-555-0147',
        'support_hours' => 'Mon-Fri 7am - 8pm PT, Sat 8am - 5pm PT',
        'sender_name' => 'Golden West Federal Credit Union',
        'footer_text' => null,
        'social_links' => [],
        'logo_light_id' => null,
        'logo_dark_id' => null,
        'favicon_id' => null,
    ];

    private static ?array $memo = null;

    public static function all(): array
    {
        if (self::$memo !== null) {
            return self::$memo;
        }
        $row = Cache::rememberForever('brand.settings', function () {
            try {
                return Schema::hasTable('brand_settings') ? BrandSetting::first()?->toArray() : null;
            } catch (\Throwable) {
                return null;
            }
        });
        $data = array_merge(self::DEFAULTS, array_filter($row ?? [], fn ($v) => $v !== null && $v !== ''));
        $data['social_links'] = $row['social_links'] ?? [];
        $data['tel'] = preg_replace('/\D+/', '', $data['support_phone']);
        $data['brand_deep'] = Color::shade($data['brand_color'], -0.06);
        $data['brand_bar'] = Color::shade($data['brand_color'], -0.04);
        $data['brand_link'] = $data['brand_color'] === self::DEFAULTS['brand_color'] ? '#1c6a56' : Color::link($data['brand_color']);
        $data['highlight_hover'] = Color::shade($data['highlight_color'], -0.07);

        return self::$memo = $data;
    }

    public static function get(string $key): mixed
    {
        return self::all()[$key] ?? null;
    }

    public static function forget(): void
    {
        Cache::forget('brand.settings');
        self::$memo = null;
    }

    public static function mediaUrl(?int $id): ?string
    {
        if (! $id) {
            return null;
        }
        $media = Cache::remember("brand.media.$id", 3600, fn () => Media::find($id)?->only(['filename']));

        return $media ? route('media.show', $media['filename']) : null;
    }

    public static function logoUrl(string $variant = 'light'): ?string
    {
        $uploaded = self::mediaUrl(self::get($variant === 'dark' ? 'logo_dark_id' : 'logo_light_id'));
        if ($uploaded) {
            return $uploaded;
        }
        // No upload: use the logo files shipped with the site, when they are there.
        $file = 'images/logo-'.($variant === 'dark' ? 'dark' : 'light').'.png';

        return is_file(public_path($file)) ? asset($file) : null;
    }

    /** Colour variables for the page head. Only the brand-driven ones, so the stylesheet stays the default. */
    public static function cssVars(): string
    {
        $b = self::all();

        return ":root{--navy:{$b['brand_color']};--navy-deep:{$b['brand_deep']};--bar:{$b['brand_bar']};--blue:{$b['brand_link']};--gold:{$b['highlight_color']};--gold-hover:{$b['highlight_hover']};}";
    }

    /** Why a colour pair is refused, or null when it passes. */
    public static function colorProblem(string $brand, string $highlight): ?string
    {
        if (Color::contrast($brand, '#ffffff') < 4.5) {
            return 'The brand colour is too light: white text on it must reach a contrast ratio of 4.5 to 1.';
        }
        if (Color::contrast($highlight, $brand) < 4.5) {
            return 'The highlight colour does not stand out enough against the brand colour for button text (needs 4.5 to 1).';
        }

        return null;
    }
}
