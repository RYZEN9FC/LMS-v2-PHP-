<x-layouts.guest title="Reset password">
    <h1>Reset password</h1><p>We’ll email a secure password-reset link to your account.</p>
    @if(session('status'))<div class="auth-status">{{ session('status') }}</div>@endif
    @if($errors->any())<div class="auth-errors">{{ $errors->first() }}</div>@endif
    <form method="post" action="{{ route('password.email') }}" class="auth-form">@csrf
        <label>Email<input type="email" name="email" value="{{ old('email') }}" autocomplete="email" inputmode="email" required autofocus></label>
        <button class="auth-button">Email reset link</button>
        <a class="auth-link" href="{{ route('login') }}">Back to sign in</a>
    </form>
</x-layouts.guest>
