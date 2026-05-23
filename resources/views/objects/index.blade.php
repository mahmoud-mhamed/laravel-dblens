@extends('dblens::layout')

@section('content')
<div class="space-y-4">
    @foreach ([
        ['key' => 'views',    'title' => 'Views',     'items' => $views,    'kind' => 'view'],
        ['key' => 'routines', 'title' => 'Routines',  'items' => $routines, 'kind' => 'routine'],
        ['key' => 'triggers', 'title' => 'Triggers',  'items' => $triggers, 'kind' => 'trigger'],
        ['key' => 'events',   'title' => 'Events',    'items' => $events,   'kind' => 'event'],
    ] as $section)
        <div class="bg-white rounded shadow-sm border border-slate-200">
            <div class="px-4 py-3 border-b font-semibold flex items-center justify-between">
                <span>{{ $section['title'] }}</span>
                <span class="text-xs text-slate-400">{{ count($section['items']) }}</span>
            </div>
            @if (empty($section['items']))
                <div class="px-4 py-4 text-sm text-slate-400">No {{ strtolower($section['title']) }}.</div>
            @else
                <table class="w-full text-sm">
                    <thead class="bg-slate-50 text-slate-600 text-left text-xs">
                        <tr>
                            <th class="px-4 py-2">Name</th>
                            @if ($section['key'] === 'routines') <th class="px-4 py-2">Type</th> @endif
                            @if ($section['key'] === 'triggers') <th class="px-4 py-2">Table</th><th class="px-4 py-2">Event</th><th class="px-4 py-2">Timing</th> @endif
                            @if ($section['key'] === 'events')   <th class="px-4 py-2">Schedule</th><th class="px-4 py-2">Status</th> @endif
                            <th class="px-4 py-2">Definition</th>
                            @unless ($read_only) <th class="px-4 py-2"></th> @endunless
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($section['items'] as $item)
                            <tr class="border-t align-top" x-data="{ open: false }">
                                <td class="px-4 py-2 mono">{{ $item['name'] }}</td>
                                @if ($section['key'] === 'routines') <td class="px-4 py-2 text-xs">{{ $item['type'] }}</td> @endif
                                @if ($section['key'] === 'triggers') <td class="px-4 py-2 mono text-xs">{{ $item['table'] }}</td><td class="px-4 py-2 text-xs">{{ $item['event'] }}</td><td class="px-4 py-2 text-xs">{{ $item['timing'] }}</td> @endif
                                @if ($section['key'] === 'events')   <td class="px-4 py-2 text-xs">{{ $item['schedule'] ?? '—' }}</td><td class="px-4 py-2 text-xs">{{ $item['status'] ?? '' }}</td> @endif
                                <td class="px-4 py-2 text-xs">
                                    <button type="button" @click="open = !open" class="text-sky-600 hover:underline" x-text="open ? 'hide' : 'show'"></button>
                                    <pre x-show="open" x-cloak class="mt-2 p-2 bg-slate-50 border rounded text-xs whitespace-pre-wrap break-words mono max-h-80 overflow-auto">{{ $item['definition'] ?? '(empty)' }}</pre>
                                </td>
                                @unless ($read_only)
                                    <td class="px-4 py-2 text-right">
                                        <form method="POST" action="{{ route('dblens.objects.destroy', ['connection' => $connection, 'kind' => $section['kind'], 'name' => $item['name']]) }}"
                                              data-confirm-title="Drop {{ $section['kind'] }}"
                                              data-confirm="Drop {{ $section['kind'] }} [{{ $item['name'] }}]? This cannot be undone."
                                              data-confirm-text="Drop">
                                            @csrf
                                            @method('DELETE')
                                            <input type="hidden" name="confirm" value="1">
                                            @if ($section['key'] === 'routines')
                                                <input type="hidden" name="type" value="{{ $item['type'] }}">
                                            @endif
                                            <button class="text-xs text-red-600 hover:underline">drop</button>
                                        </form>
                                    </td>
                                @endunless
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
        </div>
    @endforeach
</div>
@endsection
