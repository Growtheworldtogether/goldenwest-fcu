<?php

namespace App\Support;

use App\Models\BusinessProfile;
use Illuminate\Validation\Rule;

/** Validation for the business details, shared by the join form and the Add a business page. */
final class BusinessRules
{
    /** @param string $required 'required' on its own page, or 'required_if:open_business,1' on the join form. */
    public static function rules(string $required): array
    {
        $req = fn (array $more) => array_merge([$required, 'nullable'], $more);

        return [
            'business_name' => $req(['string', 'max:120']),
            'business_type' => $req([Rule::in(array_keys(BusinessProfile::ENTITY_TYPES))]),
            'business_ein' => $req(['regex:/^\d{2}-?\d{7}$/']),
            'business_industry' => $req(['string', 'max:80']),
            'business_started_year' => $req(['integer', 'min:1900', 'max:'.now()->year]),
            'business_address_line1' => $req(['string', 'max:160']),
            'business_city' => $req(['string', 'max:80']),
            'business_state' => $req(['string', 'size:2']),
            'business_postal_code' => $req(['regex:/^\d{5}(-\d{4})?$/']),
            'business_accounts' => $req([Rule::in(array_keys(BusinessProfile::ACCOUNT_CHOICES))]),
            'business_document' => $req(['file', 'mimes:png,jpg,jpeg,webp,pdf', 'max:8192']),
        ];
    }

    public static function messages(string $requiredKey): array
    {
        $m = [
            'business_name' => 'Enter the legal name of the business.', 'business_type' => 'Choose the type of business.', 'business_ein' => 'Enter the business EIN.',
            'business_industry' => 'Tell us what the business does.', 'business_started_year' => 'Enter the year the business started.',
            'business_address_line1' => 'Enter the business street address.', 'business_city' => 'Enter the business city.', 'business_state' => 'Enter the two-letter state.',
            'business_postal_code' => 'Enter a five-digit ZIP code.', 'business_accounts' => 'Choose which business accounts to open.', 'business_document' => 'Upload a document that shows the business name.',
        ];
        $out = [];
        foreach ($m as $field => $text) {
            $out[$field.'.'.$requiredKey] = $text;
        }

        return $out + [
            'business_ein.regex' => 'An EIN has nine digits, such as 12-3456789.', 'business_started_year.max' => 'The year cannot be in the future.',
            'business_postal_code.regex' => 'Enter a five-digit ZIP code.', 'business_document.mimes' => 'Use a PNG, JPG, WebP or PDF file.', 'business_document.max' => 'The file must be 8 MB or smaller.',
        ];
    }
}
