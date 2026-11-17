<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\BrandSetting;
use App\Services\Audit;
use App\Services\Brand;
use App\Services\MediaStore;
use App\Support\Color;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class BrandingController extends Controller
{
    public function edit()
    {
        Gate::authorize('staff.editSite');

        return view('admin.branding', ['b' => Brand::all()]);
    }

    public function update(Request $request, MediaStore $store)
    {
        Gate::authorize('staff.editSite');
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'short_name' => ['required', 'string', 'max:60'],
            'descriptor' => ['nullable', 'string', 'max:60'],
            'tagline' => ['nullable', 'string', 'max:120'],
            'brand_color' => ['required', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'highlight_color' => ['required', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'support_email' => ['required', 'email', 'max:160'],
            'support_phone' => ['required', 'regex:/^[0-9+().\- ]{7,20}$/'],
            'support_hours' => ['required', 'string', 'max:120'],
            'sender_name' => ['required', 'string', 'max:120'],
            'footer_text' => ['nullable', 'string', 'max:400'],
            'social' => ['nullable', 'array'],
            'social.*' => ['nullable', 'url:http,https', 'max:200'],
            'logo_light' => ['nullable', 'file'], 'logo_dark' => ['nullable', 'file'], 'favicon' => ['nullable', 'file'],
        ], [
            'brand_color.regex' => 'Enter the brand colour as a hex value such as #14403a.',
            'highlight_color.regex' => 'Enter the highlight colour as a hex value such as #f5bd02.',
            'support_phone.regex' => 'Enter a phone number using digits, spaces, dashes or parentheses.',
            'social.*.url' => 'Social links must start with http:// or https://.',
        ]);

        if ($problem = Brand::colorProblem($data['brand_color'], $data['highlight_color'])) {
            throw ValidationException::withMessages(['brand_color' => $problem]);
        }

        $row = BrandSetting::first() ?? new BrandSetting();
        $old = $row->exists ? $row->only(array_keys(Brand::DEFAULTS)) : Brand::all();

        $media = [];
        foreach (['logo_light' => 'logo_light_id', 'logo_dark' => 'logo_dark_id', 'favicon' => 'favicon_id'] as $input => $column) {
            if ($request->hasFile($input)) {
                $media[$column] = $store->store($request->file($input), $input, brand('name').' '.str_replace('_', ' ', $input), $request->user(), square: $input === 'favicon')->id;
            } elseif ($request->boolean('remove_'.$input)) {
                $media[$column] = null;
            }
        }

        $row->fill([
            'name' => trim($data['name']), 'short_name' => trim($data['short_name']),
            'descriptor' => $data['descriptor'] ?? null, 'tagline' => $data['tagline'] ?? null,
            'brand_color' => strtolower($data['brand_color']), 'highlight_color' => strtolower($data['highlight_color']),
            'support_email' => $data['support_email'], 'support_phone' => $data['support_phone'], 'support_hours' => $data['support_hours'],
            'sender_name' => $data['sender_name'], 'footer_text' => $data['footer_text'] ?? null,
            'social_links' => array_filter($data['social'] ?? []),
            'updated_by' => $request->user()->id,
        ] + $media)->save();

        Brand::forget();
        Audit::record('brand.updated', $row, $old, $row->only(array_keys(Brand::DEFAULTS)), $request->user()->id);

        return back()->with('status', 'Branding saved. It is live on every page now.');
    }
}
