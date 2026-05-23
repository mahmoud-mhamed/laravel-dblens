<?php

namespace MahmoudMhamed\DbLens\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use MahmoudMhamed\DbLens\Services\ConnectionManager;
use MahmoudMhamed\DbLens\Services\SchemaInspector;

class DbObjectsController extends Controller
{
    public function index(string $connection, ConnectionManager $cm, SchemaInspector $schema)
    {
        $cm->assertAllowed($connection);
        $driver = $cm->driver($connection);

        return view('dblens::objects.index', [
            'connection' => $connection,
            'connections' => $cm->available(),
            'database' => $cm->databaseName($connection),
            'tables' => $schema->tables($connection),
            'views' => $driver->views(),
            'routines' => $driver->routines(),
            'triggers' => $driver->triggers(),
            'events' => $driver->events(),
            'read_only' => (bool) config('dblens.read_only', false),
        ]);
    }

    public function destroy(string $connection, string $kind, string $name, Request $request, ConnectionManager $cm)
    {
        $cm->assertAllowed($connection);
        if (config('dblens.read_only', false)) abort(403, 'DbLens is in read-only mode.');
        if (config('dblens.confirm_destructive', true) && ! $request->boolean('confirm')) {
            abort(422, 'Confirmation required.');
        }
        $driver = $cm->driver($connection);
        match ($kind) {
            'view' => $driver->dropView($name),
            'routine' => $driver->dropRoutine($name, (string) $request->input('type', 'PROCEDURE')),
            'trigger' => $driver->dropTrigger($name),
            'event' => $driver->dropEvent($name),
            default => abort(404),
        };
        return redirect()
            ->route('dblens.objects.index', ['connection' => $connection])
            ->with('dblens.success', strtoupper($kind)." [{$name}] dropped.");
    }
}
