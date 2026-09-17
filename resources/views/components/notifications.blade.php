<style>
    .notifications { position:fixed; right:24px; bottom:24px; width:min(390px,calc(100vw - 32px)); z-index:1000; display:grid; gap:10px; max-height:70vh; overflow:auto; pointer-events:none; }
    .notifications .toast { pointer-events:auto; display:flex; gap:12px; align-items:flex-start; padding:17px; margin:0; background:#fff; color:#171717; border:1px solid #e4e4e7; border-left:4px solid #ea580c; border-radius:0; box-shadow:none; animation:toast-in .18s ease-out; }
    .notifications .toast[data-type=error] { border-left-color:#c43e42; }
    .toast-icon { color:#ea580c; font-weight:700; font-size:18px; }
    [data-type=error] .toast-icon { color:#c43e42; }
    .toast-body { flex:1; min-width:0; }
    .toast-title { font-size:13px; font-weight:650; letter-spacing:-.02em; }
    .toast-message { font-size:12px; color:#526174; margin-top:4px; white-space:pre-line; overflow-wrap:anywhere; }
    .toast-close { border:0; background:transparent; color:#526174; font:20px/1 Inter,sans-serif; cursor:pointer; padding:0 3px; }
    .toast-close:focus-visible { outline:2px solid #ea580c; outline-offset:4px; }
    @keyframes toast-in { from { opacity:0; transform:translateY(12px); } to { opacity:1; transform:translateY(0); } }
    @media(prefers-reduced-motion:reduce) { .notifications .toast { animation:none; } }
    @media(max-width:760px) { .notifications { right:16px; bottom:16px; } }
</style>
<div class="notifications" aria-label="Notifications">
    @if(session('status'))
        <div class="toast" data-type="success" role="status"><span class="toast-icon" aria-hidden="true">✓</span><div class="toast-body"><div class="toast-title">Changes saved</div><div class="toast-message">{{ session('status') }}</div></div><button class="toast-close" type="button" aria-label="Dismiss notification">×</button></div>
    @endif
    @if(session('error') || $errors->any())
        <div class="toast" data-type="error" role="alert"><span class="toast-icon" aria-hidden="true">!</span><div class="toast-body"><div class="toast-title">Action could not be completed</div><div class="toast-message">{{ session('error') ?? implode("\n", $errors->all()) }}</div></div><button class="toast-close" type="button" aria-label="Dismiss notification">×</button></div>
    @endif
    @if(session('uploadNotice'))
        <div class="toast" data-type="success" role="status"><span class="toast-icon" aria-hidden="true">✓</span><div class="toast-body"><div class="toast-title">Upload read successfully</div><div class="toast-message">{{ session('uploadNotice') }}</div></div><button class="toast-close" type="button" aria-label="Dismiss notification">×</button></div>
    @endif
</div>
<script>
(() => {
    const container = document.querySelector('.notifications');
    function activate(toast) {
        let timer;
        const pause = () => clearTimeout(timer);
        const resume = () => {
            pause();
            if (toast.dataset.type !== 'error') timer = setTimeout(() => toast.remove(), 8000);
        };
        toast.querySelector('.toast-close').addEventListener('click', () => { pause(); toast.remove(); });
        toast.addEventListener('mouseenter', pause);
        toast.addEventListener('mouseleave', resume);
        toast.addEventListener('focusin', pause);
        toast.addEventListener('focusout', resume);
        resume();
    }
    window.notify = ({message, title, type = 'success'}) => {
        const toast = document.createElement('div');
        toast.className = 'toast';
        toast.dataset.type = type;
        toast.setAttribute('role', type === 'error' ? 'alert' : 'status');
        const icon = document.createElement('span');
        icon.className = 'toast-icon'; icon.setAttribute('aria-hidden', 'true');
        icon.textContent = type === 'error' ? '!' : '✓';
        const body = document.createElement('div'); body.className = 'toast-body';
        const heading = document.createElement('div'); heading.className = 'toast-title';
        heading.textContent = title || (type === 'error' ? 'Action could not be completed' : 'Changes saved');
        const detail = document.createElement('div'); detail.className = 'toast-message'; detail.textContent = message;
        body.append(heading, detail);
        const close = document.createElement('button'); close.className = 'toast-close';
        close.type = 'button'; close.setAttribute('aria-label', 'Dismiss notification'); close.textContent = '×';
        toast.append(icon, body, close); container.append(toast); activate(toast);
        return toast;
    };
    container.querySelectorAll('.toast').forEach(activate);
    const carriedNotice = sessionStorage.getItem('pegwise-notice');
    if (carriedNotice) {
        sessionStorage.removeItem('pegwise-notice');
        window.notify({title:'Stock updated', message:carriedNotice});
    }
    // Native required/min/max validation occurs before form submission.
    let invalidNotice;
    document.addEventListener('invalid', event => {
        if (invalidNotice?.isConnected) return;
        const label = event.target.closest('label');
        const field = label?.childNodes[0]?.textContent?.trim() || 'This field';
        invalidNotice = window.notify({type:'error', title:'Check the form', message:field + ': ' + event.target.validationMessage});
    }, true);
})();
</script>
