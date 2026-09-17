<x-layouts.app title="Team & access">
    <div class="page-head">
        <div>
            <a class="settings-back" href="{{ route('settings.index') }}">← Settings</a>
            <h1>Team & access</h1>
            <p class="sub">Manage who can access {{ $outlet->name }} and what they can do.</p>
        </div>
    </div>

    <section class="card" style="margin-bottom:20px">
        <div class="pad">
            <h2>Add team member</h2>
            <p class="sub">Create an account with a temporary password. Every member needs a different email; your own login is already listed below.</p>
        </div>
        <form method="post" action="{{ route('team.store') }}" class="team-form">
            @csrf
            <label>Name<input name="name" value="{{ old('name') }}" required autocomplete="name"></label>
            <label>Email<input name="email" type="email" value="{{ old('email') }}" required autocomplete="email"></label>
            <label>Role<select name="role" required>@foreach(['owner' => 'Owner', 'manager' => 'Manager', 'operator' => 'Operator', 'viewer' => 'Viewer'] as $value => $label)<option value="{{ $value }}" @selected(old('role') === $value)>{{ $label }}</option>@endforeach</select></label>
            <label>Temporary password<input name="password" type="password" minlength="8" required autocomplete="new-password"></label>
            <label>Confirm password<input name="password_confirmation" type="password" minlength="8" required autocomplete="new-password"></label>
            <button class="btn" type="submit">Create member</button>
        </form>
        @if($errors->any())<div class="form-errors" role="alert">{{ $errors->first() }}</div>@endif
    </section>

    <section class="card">
        <div class="pad"><h2>{{ $outlet->name }} members</h2></div>
        <div class="team-list">
            @foreach($members as $member)
                <form method="post" action="{{ route('team.update', $member->id) }}" class="team-member">
                    @csrf @method('put')
                    <div class="team-identity"><strong>{{ $member->name }}</strong><span>{{ $member->email }}</span>@if($member->is(auth()->user()))<em>Your account</em>@endif</div>
                    <label>Role<select name="role" @disabled($member->is(auth()->user()))>@foreach(['owner' => 'Owner', 'manager' => 'Manager', 'operator' => 'Operator', 'viewer' => 'Viewer'] as $value => $label)<option value="{{ $value }}" @selected($member->pivot->role === $value)>{{ $label }}</option>@endforeach</select></label>
                    <label class="access-toggle"><input type="checkbox" name="is_active" value="1" @checked($member->pivot->is_active) @disabled($member->is(auth()->user()))> Active access</label>
                    <button class="btn secondary" type="submit" @disabled($member->is(auth()->user()))>Save access</button>
                </form>
            @endforeach
        </div>
    </section>

    <style>
        .team-form{display:grid;grid-template-columns:repeat(5,minmax(140px,1fr)) auto;gap:14px;align-items:end;padding:20px 24px}.team-form label,.team-member label{display:grid;gap:6px}.team-form input,.team-form select{width:100%}.form-errors{margin:0 24px 20px;padding:11px 12px;border-left:3px solid #dc2626;background:#fef2f2;color:#991b1b}.team-list{display:grid}.team-member{display:grid;grid-template-columns:minmax(220px,1fr) 170px 150px auto;gap:16px;align-items:center;padding:16px 24px;border-bottom:1px solid var(--line)}.team-member:last-child{border-bottom:0}.team-identity{min-width:0}.team-identity strong,.team-identity span{display:block;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}.team-identity span{color:var(--muted);font-size:12px}.team-identity em{display:inline-block;margin-top:5px;color:#c2410c;font-size:10px;font-style:normal;font-weight:700;text-transform:uppercase}.access-toggle{display:flex!important;grid-template-columns:18px 1fr!important;align-items:center;text-transform:none;letter-spacing:0}.access-toggle input{width:16px;height:16px;min-height:16px;padding:0;accent-color:#ea580c}
        .settings-back{display:inline-block;margin-bottom:9px;color:#c2410c;text-decoration:none;font-size:12px;font-weight:650}
        @media(max-width:1100px){.team-form{grid-template-columns:repeat(2,minmax(0,1fr))}.team-form .btn{width:100%}}
        @media(max-width:700px){.team-form{grid-template-columns:1fr;padding:18px 16px}.team-member{grid-template-columns:1fr;padding:18px 16px}.team-member .btn{width:100%}}
    </style>
</x-layouts.app>
