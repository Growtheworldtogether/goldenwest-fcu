<?php

namespace App\Http\Controllers;

use App\Models\Inquiry;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class InquiryController extends Controller
{
    public function contact(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:160'],
            'phone' => ['nullable', 'string', 'max:30'],
            'topic' => ['required', 'string', 'max:80'],
            'message' => ['required', 'string', 'max:3000'],
        ]);

        Inquiry::create($data + ['type' => 'contact']);

        return back()->with('sent', 'Thank you. A member of our Customer Care team will reply within one business day.');
    }

    public function appointment(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:160'],
            'phone' => ['required', 'string', 'max:30'],
            'topic' => ['required', 'string', 'max:80'],
            'location' => ['required', 'string', 'max:120'],
            'preferred_when' => ['required', 'string', 'max:120'],
            'message' => ['nullable', 'string', 'max:2000'],
        ]);

        Inquiry::create($data + ['type' => 'appointment']);

        return back()->with('sent', 'Your appointment request is in. We will call you to confirm a time within one business day.');
    }

    public function join(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:160'],
            'phone' => ['nullable', 'string', 'max:30'],
            'topic' => ['required', 'string', 'max:80'],
        ]);

        Inquiry::create($data + ['type' => 'join']);

        return back()->with('sent', 'Welcome to Golden West. We have your details and will email you the next steps to finish opening your account.');
    }
}
