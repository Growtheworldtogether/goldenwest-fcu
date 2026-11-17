@if (session('impersonation'))
    @php($hasCode = session('impersonation.admin_two_factor'))
    <label>{{ $hasCode ? 'Your staff two-step code' : 'Your staff password' }}<input name="admin_code" @if ($hasCode) inputmode="numeric" autocomplete="one-time-code" maxlength="6" placeholder="6-digit code from your authenticator app" @else type="password" autocomplete="current-password" @endif required>@error('admin_code')<span class="err">{{ $message }}</span>@enderror</label>
@endif
