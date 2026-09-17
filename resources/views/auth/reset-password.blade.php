<x-layouts.guest title="Choose password">
    <h1>Choose a new password</h1><p>Use at least 8 characters.</p>
    @if($errors->any())<div class="auth-errors">{{ $errors->first() }}</div>@endif
    <form method="post" action="{{ route('password.update') }}" class="auth-form">@csrf
        <input type="hidden" name="token" value="{{ $token }}">
        <label>Email<input type="email" name="email" value="{{ old('email', $email) }}" autocomplete="email" required></label>
        <label>New password<input type="password" name="password" minlength="8" autocomplete="new-password" required></label>
        <label>Confirm password<input type="password" name="password_confirmation" minlength="8" autocomplete="new-password" required></label>
        <button class="auth-button">Reset password</button>
    </form>
</x-layouts.guest>
