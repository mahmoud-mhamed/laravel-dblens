@extends('dblens::layout')

@section('content')
<div class="max-w-3xl mx-auto">
    <div class="bg-red-50 border border-red-200 rounded-lg p-6">
        <div class="flex items-start gap-3">
            <div class="text-3xl">⚠️</div>
            <div class="flex-1 min-w-0">
                <h1 class="text-lg font-bold text-red-800">Database connection failed</h1>
                <p class="text-sm text-red-700 mt-1">
                    DbLens could not reach the <span class="mono font-semibold">{{ $connection ?? '?' }}</span> connection.
                </p>
            </div>
        </div>

        <div class="mt-5 bg-white rounded border border-red-200 p-3 text-xs mono text-red-700 break-words">
            {{ $message }}
            @if ($code !== '' && $code !== 0)
                <span class="text-red-400">(code {{ $code }})</span>
            @endif
        </div>

        @if (! empty($config))
            <details class="mt-4">
                <summary class="cursor-pointer text-xs text-slate-600">Show connection config</summary>
                <pre class="mt-2 p-3 bg-slate-900 text-slate-100 text-xs rounded overflow-x-auto mono">{{ json_encode(collect($config)->except(['password'])->all(), JSON_PRETTY_PRINT) }}</pre>
            </details>
        @endif

        <div class="mt-5 text-xs text-slate-600 space-y-1">
            <p><strong>Common causes:</strong></p>
            <ul class="list-disc list-inside space-y-0.5 text-slate-500">
                <li>The connection's <code class="mono">driver</code> in <code class="mono">config/database.php</code> doesn't match the actual server (e.g. <code class="mono">pgsql</code> pointing at a MySQL port).</li>
                <li>Wrong host / port — the server isn't running or isn't listening on this port.</li>
                <li>Bad credentials, missing database, or SSL is required by the server but not enabled in the client.</li>
            </ul>
        </div>

        <div class="mt-5 flex gap-2">
            @foreach ($connections as $c)
                <a href="{{ route('dblens.database.show', ['connection' => $c]) }}"
                   class="px-3 py-1.5 text-xs rounded border {{ $c === $connection ? 'border-red-300 bg-red-100 text-red-700' : 'border-slate-300 bg-white hover:bg-slate-50 text-slate-700' }}">
                    {{ $c }}
                </a>
            @endforeach
        </div>
    </div>
</div>
@endsection
