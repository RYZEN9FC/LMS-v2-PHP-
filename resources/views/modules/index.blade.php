<x-layouts.guest title="Choose workspace">
    <h1>Choose management area</h1>
    <p>{{ $outlet->name }} · Select what you want to manage.</p>
    <div class="module-grid">
        <form method="post" action="{{ route('modules.select') }}">@csrf<input type="hidden" name="module" value="liquor"><button class="module-card"><strong>Liquor management</strong><span>Brands, excise stock, cocktails, POS consumption and reports.</span><b>Open liquor →</b></button></form>
        <form method="post" action="{{ route('modules.select') }}">@csrf<input type="hidden" name="module" value="food"><button class="module-card food"><strong>Food management</strong><span>Ingredients, simple stock entry, dish recipes, margins and wastage.</span><b>Open food →</b></button></form>
    </div>
    <form method="post" action="{{ route('logout') }}" style="margin-top:20px">@csrf<button class="auth-button" style="width:100%;background:#fff;color:#52525b;border-color:#d4d4d8">Sign out</button></form>
    <style>
        .auth-shell{width:min(760px,100%)}.module-grid{display:grid;grid-template-columns:1fr 1fr;gap:14px}.module-card{width:100%;min-height:190px;padding:22px;text-align:left;border:1px solid #d4d4d8;background:#fff;cursor:pointer;display:flex;flex-direction:column;align-items:flex-start}.module-card:hover,.module-card:focus-visible{border-color:#f97316;outline:2px solid #fed7aa}.module-card strong{font-size:20px;letter-spacing:-.035em}.module-card span{margin-top:10px;color:#6b7280;font-size:13px;line-height:1.55}.module-card b{margin-top:auto;padding-top:24px;color:#c2410c;font-size:12px;text-transform:uppercase}.module-card.food{background:#f7fff8;border-color:#bbf7d0}.module-card.food b{color:#15803d}@media(max-width:620px){.module-grid{grid-template-columns:1fr}.module-card{min-height:160px}}
    </style>
</x-layouts.guest>
