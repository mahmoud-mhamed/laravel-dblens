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
</body>
</html>
