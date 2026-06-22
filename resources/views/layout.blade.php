<!DOCTYPE html>
<html lang="en" dir="ltr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>DbLens — {{ $database ?? 'Database' }}{{ isset($table) ? ' · '.$table : '' }}</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <style>
        [x-cloak]{display:none!important}
        .truncate-cell{max-width:280px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
        .mono{font-family:ui-monospace,SFMono-Regular,Menlo,Monaco,Consolas,monospace}

        /* Thin custom scrollbars */
        .scroll-thin{scrollbar-width:thin;scrollbar-color:rgba(148,163,184,.35) transparent}
        .scroll-thin::-webkit-scrollbar{width:8px;height:8px}
        .scroll-thin::-webkit-scrollbar-track{background:transparent}
        .scroll-thin::-webkit-scrollbar-thumb{background:rgba(148,163,184,.35);border-radius:8px;border:2px solid transparent;background-clip:content-box}
        .scroll-thin::-webkit-scrollbar-thumb:hover{background:rgba(148,163,184,.6);background-clip:content-box;border:2px solid transparent}

        /* Dark sidebar variant */
        .scroll-thin-dark{scrollbar-width:thin;scrollbar-color:rgba(100,116,139,.5) transparent}
        .scroll-thin-dark::-webkit-scrollbar{width:8px;height:8px}
        .scroll-thin-dark::-webkit-scrollbar-track{background:transparent}
        .scroll-thin-dark::-webkit-scrollbar-thumb{background:rgba(100,116,139,.5);border-radius:8px;border:2px solid transparent;background-clip:content-box}
        .scroll-thin-dark::-webkit-scrollbar-thumb:hover{background:rgba(148,163,184,.8);background-clip:content-box;border:2px solid transparent}
    </style>
</head>
<body class="bg-slate-100 text-slate-800">
<div class="flex items-start">
    @include('dblens::partials.sidebar')

    <main class="flex-1 min-w-0">
        <div class="sticky top-0 z-30">
            @include('dblens::partials.topbar')
        </div>

        @if (session('dblens.success'))
            <div class="m-4 px-4 py-2 bg-green-100 border border-green-300 text-green-800 rounded">
                {{ session('dblens.success') }}
            </div>
        @endif
        @if (session('dblens.error'))
            <div class="m-4 px-4 py-2 bg-red-100 border border-red-300 text-red-800 rounded">
                {{ session('dblens.error') }}
            </div>
        @endif

        <div class="p-4">
            @yield('content')
        </div>
    </main>
</div>

{{-- ─── Global confirm modal ──────────────────────────────────── --}}
<div x-data="dbLensConfirm()"
     @open-confirm.window="open($event.detail)"
     x-show="visible" x-cloak
     class="fixed inset-0 bg-black/50 z-[100] flex items-center justify-center p-4"
     @keydown.escape.window="cancel()">
    <div @click.outside="cancel()" class="bg-white rounded-lg shadow-2xl w-full max-w-md">
        <div class="px-5 py-3 border-b flex items-center gap-2">
            <span x-text="danger ? '⚠️' : '❓'" class="text-xl"></span>
            <h3 class="font-semibold" x-text="title"></h3>
        </div>
        <div class="px-5 py-4">
            <p class="text-sm text-slate-700 whitespace-pre-wrap" x-text="message"></p>
            <template x-if="typedConfirmation">
                <div class="mt-3">
                    <p class="text-xs text-slate-500">Type <span class="mono font-bold text-red-600" x-text="typedConfirmation"></span> to confirm:</p>
                    <input type="text" x-model="typed" x-ref="typedInput"
                           @keydown.enter.prevent.stop="canConfirm() && proceed()"
                           class="mt-1 w-full px-3 py-2 border border-slate-300 rounded text-sm mono focus:outline-none focus:border-red-500">
                </div>
            </template>
            <template x-if="softDeleteUrl">
                <label class="mt-3 flex items-center gap-2 p-2 border border-amber-200 bg-amber-50 rounded cursor-pointer text-sm">
                    <input type="checkbox" x-model="softDelete" class="accent-amber-600">
                    <span class="text-amber-700">🪶 <strong>Soft delete</strong> instead — set <span class="mono">deleted_at = NOW()</span> (keeps the row)</span>
                </label>
            </template>
            <template x-if="softField">
                <label class="mt-3 flex items-center gap-2 p-2 border border-amber-200 bg-amber-50 rounded cursor-pointer text-sm">
                    <input type="checkbox" x-model="softFieldOn" class="accent-amber-600">
                    <span class="text-amber-700" x-html="softLabel"></span>
                </label>
            </template>
            <template x-if="choices">
                <div class="mt-3 space-y-1.5" x-show="!isSoft()">
                    <template x-for="opt in choices" :key="opt.value">
                        <label class="flex items-start gap-2 p-2 border rounded cursor-pointer text-sm transition"
                               :class="choiceValue === opt.value ? 'border-sky-400 bg-sky-50' : 'border-slate-200 hover:border-slate-300'">
                            <input type="radio" :value="opt.value" x-model="choiceValue" class="mt-0.5 accent-sky-600">
                            <span class="min-w-0">
                                <span class="font-semibold text-slate-700" x-text="opt.label"></span>
                                <span class="block text-xs text-slate-500" x-text="opt.desc"></span>
                            </span>
                        </label>
                    </template>
                </div>
            </template>
        </div>
        <div class="px-5 py-3 border-t bg-slate-50 flex justify-end gap-2">
            <button type="button" @click.stop="cancel()" class="px-4 py-2 text-sm text-slate-600 hover:text-slate-800">Cancel</button>
            <button type="button" @click.stop="proceed()" :disabled="!canConfirm()"
                    :class="isSoft() ? 'bg-amber-600 hover:bg-amber-700 disabled:bg-amber-300' : (danger ? 'bg-red-600 hover:bg-red-700 disabled:bg-red-300' : 'bg-sky-600 hover:bg-sky-700 disabled:bg-sky-300')"
                    class="px-4 py-2 text-white rounded text-sm font-semibold disabled:cursor-not-allowed"
                    x-text="isSoft() ? 'Soft delete' : confirmText"></button>
        </div>
    </div>
</div>

<script>
function dbLensConfirm() {
    return {
        visible: false,
        title: '',
        message: '',
        confirmText: 'Confirm',
        danger: true,
        typedConfirmation: null,
        typed: '',
        pending: null,
        softDeleteUrl: null,
        softDeleteRowKey: null,
        softDelete: true,
        softField: null,
        softFieldOn: true,
        softLabel: '',
        choices: null,
        choiceValue: null,
        isSoft() {
            return (this.softDeleteUrl && this.softDelete) || (this.softField && this.softFieldOn);
        },
        open(opts) {
            this.title = opts.title || 'Are you sure?';
            this.message = opts.message || '';
            this.confirmText = opts.confirmText || 'Confirm';
            this.danger = opts.danger !== false;
            this.typedConfirmation = opts.typedConfirmation || null;
            this.typed = '';
            this.pending = opts.onConfirm || null;
            this.softDeleteUrl = opts.softDeleteUrl || null;
            this.softDeleteRowKey = opts.softDeleteRowKey || null;
            this.softDelete = !! this.softDeleteUrl; // default to soft when available
            this.softField = opts.softField || null;
            this.softFieldOn = !! this.softField && opts.softFieldDefault !== false;
            this.softLabel = opts.softLabel || '🪶 <strong>Soft delete</strong> instead — set <span class="mono">deleted_at = NOW()</span> (keeps the rows)';
            this.choices = (opts.choices && opts.choices.length) ? opts.choices : null;
            this.choiceValue = this.choices ? (opts.choiceDefault || this.choices[0].value) : null;
            this.visible = true;
            if (this.typedConfirmation) {
                this.$nextTick(() => this.$refs.typedInput?.focus());
            }
        },
        cancel() {
            this.visible = false;
            this.pending = null;
            this.typed = '';
            this.softDeleteUrl = null;
            this.softField = null;
            this.choices = null;
            // Notify ancestors (e.g. the bulk-form wrapper) that the user
            // backed out, so they can clear any "Loading…" overlay they set.
            window.dispatchEvent(new CustomEvent('dblens-confirm-cancelled'));
        },
        canConfirm() {
            return ! this.typedConfirmation || this.typed === this.typedConfirmation;
        },
        async proceed() {
            if (! this.canConfirm()) return;
            if (this.softDeleteUrl && this.softDelete) {
                const url = this.softDeleteUrl, rk = this.softDeleteRowKey;
                this.visible = false;
                this.pending = null;
                this.softDeleteUrl = null;
                try {
                    const now = new Date().toISOString().slice(0, 19).replace('T', ' ');
                    const res = await fetch(url, {
                        method: 'PATCH',
                        headers: {
                            'Content-Type': 'application/json',
                            'Accept': 'application/json',
                            'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]')?.content || '',
                        },
                        credentials: 'same-origin',
                        body: JSON.stringify({ row_key: rk, column: 'deleted_at', value: now }),
                    });
                    if (! res.ok) {
                        const d = await res.json().catch(() => ({}));
                        alert('Soft delete failed: ' + (d.message || ('HTTP ' + res.status)));
                        return;
                    }
                    window.location.reload();
                } catch (e) {
                    alert('Soft delete failed: ' + e.message);
                }
                return;
            }
            const fn = this.pending;
            const choice = this.choiceValue;
            const soft = this.softField ? this.softFieldOn : undefined;
            this.visible = false;
            this.pending = null;
            this.typed = '';
            this.softDeleteUrl = null;
            this.softField = null;
            this.choices = null;
            if (fn) fn({ choice, soft });
        },
    }
}

/**
 * Forms with data-confirm intercept submit and open the modal first.
 *  data-confirm        — message body (required)
 *  data-confirm-title  — modal title
 *  data-confirm-text   — confirm button label
 *  data-confirm-type   — word the user must type to enable confirm
 *  data-confirm-danger — "false" to use blue instead of red
 */
// Programmatic per-row delete. Avoids nesting a <form method=DELETE> inside
// the bulk-form (which would otherwise leak _method=DELETE to the outer form).
window.dbLensDeleteRow = function (opts) {
    const csrf = document.querySelector('meta[name=csrf-token]')?.content || '';
    const detail = {
        title: 'Delete row',
        message: 'Delete this row from [' + (opts.table || 'table') + ']?',
        confirmText: 'Delete',
        softDeleteUrl: opts.softDeleteUrl || null,
        softDeleteRowKey: opts.softDeleteRowKey || null,
        choices: opts.choices || null,
        choiceDefault: 'none',
        onConfirm: async (resp) => {
            try {
                const fd = new FormData();
                fd.append('_method', 'DELETE');
                fd.append('_token', csrf);
                fd.append('confirm', '1');
                if (resp && resp.choice) fd.append('fk_mode', resp.choice);
                const res = await fetch(opts.url, { method: 'POST', body: fd, credentials: 'same-origin' });
                if (! res.ok && res.status !== 302 && ! res.redirected) {
                    alert('Delete failed: HTTP ' + res.status);
                    return;
                }
                window.location.reload();
            } catch (e) {
                alert('Delete failed: ' + e.message);
            }
        },
    };
    // Defer so the originating click finishes bubbling before the modal opens.
    // Otherwise its own click.outside handler would close it immediately.
    setTimeout(() => window.dispatchEvent(new CustomEvent('open-confirm', { detail })), 0);
};

// Programmatic restore: clears deleted_at via the cell-update endpoint after
// a confirm dialog. Used by per-row "↩ Restore" buttons.
window.dbLensRestoreRow = function (url, rowKey) {
    const detail = {
        title: 'Restore row',
        message: 'Restore this row by setting deleted_at = NULL?',
        confirmText: 'Restore',
        danger: false,
        onConfirm: async () => {
            try {
                const res = await fetch(url, {
                    method: 'PATCH',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]')?.content || '',
                    },
                    credentials: 'same-origin',
                    body: JSON.stringify({ row_key: rowKey, column: 'deleted_at', value: null }),
                });
                if (! res.ok) {
                    const d = await res.json().catch(() => ({}));
                    alert('Restore failed: ' + (d.message || ('HTTP ' + res.status)));
                    return;
                }
                window.location.reload();
            } catch (e) {
                alert('Restore failed: ' + e.message);
            }
        },
    };
    setTimeout(() => window.dispatchEvent(new CustomEvent('open-confirm', { detail })), 0);
};

document.addEventListener('submit', function (e) {
    const form = e.target;
    if (! (form instanceof HTMLFormElement)) return;
    if (! form.dataset.confirm) return;
    if (form.dataset.confirmed === '1') return;
    e.preventDefault();
    e.stopPropagation();
    e.stopImmediatePropagation();
    let choices = null;
    if (form.dataset.confirmChoices) {
        try { choices = JSON.parse(form.dataset.confirmChoices); } catch (_) { choices = null; }
    }
    const choiceName = form.dataset.confirmChoiceName || null;
    window.dispatchEvent(new CustomEvent('open-confirm', { detail: {
        title: form.dataset.confirmTitle || 'Confirm',
        message: form.dataset.confirm,
        confirmText: form.dataset.confirmText || 'Confirm',
        danger: form.dataset.confirmDanger !== 'false',
        typedConfirmation: form.dataset.confirmType || null,
        softDeleteUrl: form.dataset.confirmSoftDeleteUrl || null,
        softDeleteRowKey: form.dataset.confirmSoftDeleteRowKey || null,
        choices: choices,
        choiceDefault: form.dataset.confirmChoiceDefault || null,
        onConfirm: (res) => {
            if (choiceName && res && res.choice != null) {
                let inp = form.querySelector('input[data-confirm-choice="1"]');
                if (! inp) {
                    inp = document.createElement('input');
                    inp.type = 'hidden';
                    inp.dataset.confirmChoice = '1';
                    form.appendChild(inp);
                }
                inp.name = choiceName;
                inp.value = res.choice;
            }
            form.dataset.confirmed = '1';
            form.submit();
        }
    }}));
}, true);
</script>
</body>
</html>
