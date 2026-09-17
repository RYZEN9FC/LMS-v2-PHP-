<x-layouts.guest title="Sign in">
    <h1>Sign in</h1><p>Access your bar’s inventory workspace.</p>
    @if(session('status'))<div class="auth-status">{{ session('status') }}</div>@endif
    @if($errors->any())<div class="auth-errors">{{ $errors->first() }}</div>@endif
    <form method="post" action="{{ route('login.store') }}" class="auth-form">@csrf
        <label>Email<input type="email" name="email" value="{{ old('email') }}" autocomplete="email" inputmode="email" required autofocus></label>
        <label>Password<input type="password" name="password" autocomplete="current-password" required></label>
        <label class="auth-check"><input type="checkbox" name="remember" value="1">Keep me signed in</label>
        <button class="auth-button">Sign in</button>
        <a class="auth-link" href="{{ route('password.request') }}">Forgot your password?</a>
    </form>
</x-layouts.guest>
