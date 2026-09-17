<x-layouts.app title="Settings">
    <div class="page-head">
        <div>
            <h1>Settings</h1>
            <p class="sub">Manage your account and access for {{ app(\App\Support\CurrentOutlet::class)->get()->name }}.</p>
        </div>
    </div>

    <div class="settings-grid">
        <a class="settings-item" href="{{ route('account.security') }}">
            <span class="settings-icon" aria-hidden="true">●</span>
            <span><strong>Account security</strong><small>Change your login password.</small></span>
            <b aria-hidden="true">→</b>
        </a>
        @if(app(\App\Support\CurrentOutlet::class)->allows('team.manage'))
            <a class="settings-item" href="{{ route('team.index') }}">
                <span class="settings-icon" aria-hidden="true">◆</span>
                <span><strong>Team & access</strong><small>Create accounts and assign outlet roles.</small></span>
                <b aria-hidden="true">→</b>
            </a>
        @endif
        @if(app(\App\Support\CurrentOutlet::class)->allows('catalogue.view'))
            <a class="settings-item" href="{{ route('mappings.index') }}">
                <span class="settings-icon" aria-hidden="true">↔</span>
                <span><strong>Product mappings</strong><small>Manage saved POS and excise product identities.</small></span>
                <b aria-hidden="true">→</b>
            </a>
        @endif
    </div>

    <style>
        .settings-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:14px;max-width:940px}.settings-item{display:grid;grid-template-columns:38px 1fr auto;gap:14px;align-items:center;min-height:104px;padding:20px;border:1px solid var(--line);background:#fff;color:var(--ink);text-decoration:none}.settings-item:hover{border-color:#fb923c;background:#fffaf5}.settings-item strong,.settings-item small{display:block}.settings-item strong{font-size:15px}.settings-item small{margin-top:4px;color:var(--muted);font-size:12px}.settings-item b{color:#c2410c;font-size:20px}.settings-icon{display:grid;place-items:center;width:38px;height:38px;border:1px solid #fed7aa;background:#fff7ed;color:#ea580c;font-size:13px}@media(max-width:700px){.settings-grid{grid-template-columns:1fr}.settings-item{min-height:92px;padding:16px}}
    </style>
</x-layouts.app>
