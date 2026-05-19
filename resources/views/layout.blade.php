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
                           @keydown.enter.prevent="canConfirm() && confirm()"
                           class="mt-1 w-full px-3 py-2 border border-slate-300 rounded text-sm mono focus:outline-none focus:border-red-500">
                </div>
            </template>
        </div>
        <div class="px-5 py-3 border-t bg-slate-50 flex justify-end gap-2">
            <button type="button" @click="cancel()" class="px-4 py-2 text-sm text-slate-600 hover:text-slate-800">Cancel</button>
            <button type="button" @click="confirm()" :disabled="!canConfirm()"
                    :class="danger ? 'bg-red-600 hover:bg-red-700 disabled:bg-red-300' : 'bg-sky-600 hover:bg-sky-700 disabled:bg-sky-300'"
                    class="px-4 py-2 text-white rounded text-sm font-semibold disabled:cursor-not-allowed"
                    x-text="confirmText"></button>
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
        open(opts) {
            this.title = opts.title || 'Are you sure?';
            this.message = opts.message || '';
            this.confirmText = opts.confirmText || 'Confirm';
            this.danger = opts.danger !== false;
            this.typedConfirmation = opts.typedConfirmation || null;
            this.typed = '';
            this.pending = opts.onConfirm || null;
            this.visible = true;
            if (this.typedConfirmation) {
                this.$nextTick(() => this.$refs.typedInput?.focus());
            }
        },
        cancel() {
            this.visible = false;
            this.pending = null;
            this.typed = '';
        },
        canConfirm() {
            return ! this.typedConfirmation || this.typed === this.typedConfirmation;
        },
        confirm() {
            if (! this.canConfirm()) return;
            const fn = this.pending;
            this.visible = false;
            this.pending = null;
            this.typed = '';
            if (fn) fn();
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
document.addEventListener('submit', function (e) {
    const form = e.target;
    if (! (form instanceof HTMLFormElement)) return;
    if (! form.dataset.confirm) return;
    if (form.dataset.confirmed === '1') return;
    e.preventDefault();
    window.dispatchEvent(new CustomEvent('open-confirm', { detail: {
        title: form.dataset.confirmTitle || 'Confirm',
        message: form.dataset.confirm,
        confirmText: form.dataset.confirmText || 'Confirm',
        danger: form.dataset.confirmDanger !== 'false',
        typedConfirmation: form.dataset.confirmType || null,
        onConfirm: () => { form.dataset.confirmed = '1'; form.submit(); }
    }}));
}, true);
</script>
</body>
</html>
