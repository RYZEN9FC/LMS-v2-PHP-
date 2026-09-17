<x-layouts.app title="Account security">
    <div class="page-head">
        <div>
            <a class="settings-back" href="{{ route('settings.index') }}">← Settings</a>
            <h1>Account security</h1>
            <p class="sub">Update the password for {{ auth()->user()->email }}.</p>
        </div>
    </div>

    <section class="card security-card">
        <div class="pad"><h2>Change password</h2></div>
        <form method="post" action="{{ route('account.password') }}" class="security-form">
            @csrf @method('put')
            <label>Current password<input name="current_password" type="password" required autocomplete="current-password"></label>
            <label>New password<input name="password" type="password" minlength="8" required autocomplete="new-password"><span>Use at least 8 characters.</span></label>
            <label>Confirm new password<input name="password_confirmation" type="password" required autocomplete="new-password"></label>
            @if($errors->any())<div class="security-error" role="alert">{{ $errors->first() }}</div>@endif
            <button class="btn" type="submit">Update password</button>
        </form>
    </section>

    <style>
        .security-card{max-width:620px}.security-form{display:grid;gap:16px;padding:22px 24px}.security-form label{display:grid;gap:6px}.security-form input{width:100%}.security-form label span{color:var(--muted);font-size:11px;font-weight:400;letter-spacing:0;text-transform:none}.security-error{padding:11px 12px;border-left:3px solid #dc2626;background:#fef2f2;color:#991b1b}.security-form .btn{justify-self:start}.settings-back{display:inline-block;margin-bottom:9px;color:#c2410c;text-decoration:none;font-size:12px;font-weight:650}@media(max-width:700px){.security-form{padding:18px 16px}.security-form .btn{width:100%}}
    </style>
</x-layouts.app>
