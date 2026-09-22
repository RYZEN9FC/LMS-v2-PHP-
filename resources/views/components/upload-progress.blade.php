<div class="upload-progress" data-upload-progress hidden aria-live="polite" aria-atomic="true">
    <div class="upload-progress-heading"><span data-upload-phase>Uploading file…</span><strong data-upload-percent>0%</strong></div>
    <div class="upload-progress-track" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="0"><span data-upload-progress-bar></span></div>
    <div class="upload-progress-detail" data-upload-detail>Waiting to read rows</div>
</div>
<style>
    .upload-progress { margin-top:16px; max-width:620px; padding:14px 16px; border:1px solid #fed7aa; background:#fffaf5; }
    .upload-progress[hidden] { display:none; }
    .upload-progress-heading { display:flex; align-items:center; justify-content:space-between; gap:16px; color:#7c2d12; font-size:13px; font-weight:650; }
    .upload-progress-heading strong { min-width:44px; text-align:right; font-variant-numeric:tabular-nums; }
    .upload-progress-track { height:10px; margin-top:10px; overflow:hidden; background:#ffedd5; }
    .upload-progress-track span { display:block; width:0; height:100%; background:#ea580c; transition:width .16s ease; }
    .upload-progress-detail { margin-top:8px; color:#6b7280; font-size:12px; font-variant-numeric:tabular-nums; }
    .upload-progress.has-error { border-color:#fecaca; background:#fff7f7; }
    .upload-progress.has-error .upload-progress-heading { color:#991b1b; }
    .upload-progress.has-error .upload-progress-track span { background:#dc2626; }
    @media (max-width:480px) { .upload-progress { padding:13px 14px; } }
</style>
<script>
(() => {
    const form = document.querySelector('[data-upload-progress-form]');
    if (!form || !window.XMLHttpRequest || !window.FormData) return;
    const panel = form.querySelector('[data-upload-progress]');
    const phase = panel.querySelector('[data-upload-phase]');
    const percent = panel.querySelector('[data-upload-percent]');
    const detail = panel.querySelector('[data-upload-detail]');
    const track = panel.querySelector('[role=progressbar]');
    const bar = panel.querySelector('[data-upload-progress-bar]');
    const submit = form.querySelector('[type=submit]');
    let completed = false;

    const update = (value, heading, message) => {
        const safeValue = Math.max(0, Math.min(100, Number(value) || 0));
        phase.textContent = heading;
        percent.textContent = `${safeValue}%`;
        detail.textContent = message;
        bar.style.width = `${safeValue}%`;
        track.setAttribute('aria-valuenow', String(safeValue));
    };
    const fail = message => {
        completed = true;
        update(0, 'Upload stopped', message);
        panel.classList.add('has-error');
        submit.disabled = false;
        window.notify?.({type:'error', title:'Action could not be completed', message});
    };

    form.addEventListener('submit', event => {
        event.preventDefault();
        if (!form.reportValidity()) return;
        completed = false;
        panel.hidden = false;
        panel.classList.remove('has-error');
        submit.disabled = true;
        update(0, 'Uploading file…', 'Waiting to read rows');

        const xhr = new XMLHttpRequest();
        let consumed = 0;
        let remainder = '';
        const handleEvent = payload => {
            if (payload.event === 'stage') {
                phase.textContent = payload.message || 'Reading document…';
                return;
            }
            if (payload.event === 'progress') {
                const total = Number(payload.total) || 0;
                const processed = Math.min(Number(payload.processed) || 0, total);
                update(payload.percent, 'Reading document rows…', `${processed.toLocaleString()} / ${total.toLocaleString()} rows read`);
                return;
            }
            if (payload.event === 'complete') {
                completed = true;
                update(100, 'Preview ready', detail.textContent.replace(/^0 \/ 0/, 'All'));
                sessionStorage.setItem('pegwise-upload-notice', payload.message || 'The upload was read successfully.');
                setTimeout(() => window.location.assign(payload.redirect), 250);
                return;
            }
            if (payload.event === 'error') fail(payload.message || 'The file could not be read.');
        };
        const consume = final => {
            const incoming = remainder + xhr.responseText.slice(consumed);
            consumed = xhr.responseText.length;
            const lines = incoming.split('\n');
            remainder = final ? '' : lines.pop();
            lines.filter(Boolean).forEach(line => {
                try { handleEvent(JSON.parse(line)); } catch (_) {}
            });
            if (final && remainder.trim()) {
                try { handleEvent(JSON.parse(remainder)); } catch (_) {}
            }
        };

        xhr.open('POST', form.dataset.progressUrl);
        xhr.setRequestHeader('Accept', 'application/x-ndjson, application/json');
        xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
        xhr.upload.addEventListener('progress', upload => {
            if (!upload.lengthComputable) return;
            const value = Math.round((upload.loaded / upload.total) * 100);
            update(value, 'Uploading file…', `${value}% of the file transferred · rows will be counted next`);
        });
        xhr.upload.addEventListener('load', () => update(100, 'File uploaded', 'Preparing document rows…'));
        xhr.addEventListener('progress', () => consume(false));
        xhr.addEventListener('load', () => {
            consume(true);
            if (completed) return;
            if (xhr.status >= 400) {
                try {
                    const body = JSON.parse(xhr.responseText);
                    const errors = Object.values(body.errors || {}).flat();
                    fail(errors[0] || body.message || 'The upload was rejected.');
                } catch (_) {
                    fail('The upload was rejected. Check the file and try again.');
                }
                return;
            }
            fail('The server response ended before the preview was ready. Please try again.');
        });
        xhr.addEventListener('error', () => fail('The connection was interrupted. No stock was changed.'));
        xhr.addEventListener('abort', () => fail('The upload was cancelled. No stock was changed.'));
        xhr.send(new FormData(form));
    });
})();
</script>
