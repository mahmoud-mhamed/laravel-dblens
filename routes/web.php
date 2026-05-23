<?php

use Illuminate\Support\Facades\Route;
use MahmoudMhamed\DbLens\Http\Controllers\AboutController;
use MahmoudMhamed\DbLens\Http\Controllers\AuthController;
use MahmoudMhamed\DbLens\Http\Controllers\DashboardController;
use MahmoudMhamed\DbLens\Http\Controllers\DatabaseController;
use MahmoudMhamed\DbLens\Http\Controllers\DbObjectsController;
use MahmoudMhamed\DbLens\Http\Controllers\ErController;
use MahmoudMhamed\DbLens\Http\Controllers\ExportController;
use MahmoudMhamed\DbLens\Http\Controllers\ImportController;
use MahmoudMhamed\DbLens\Http\Controllers\RowController;
use MahmoudMhamed\DbLens\Http\Controllers\SchemaController;
use MahmoudMhamed\DbLens\Http\Controllers\SqlController;
use MahmoudMhamed\DbLens\Http\Controllers\TableController;

// Connection slug pattern — keep URLs safe against path traversal / odd chars.
Route::pattern('connection', '[A-Za-z0-9_\-]+');

// Auth
Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
Route::post('/login', [AuthController::class, 'authenticate'])
    ->middleware('throttle:5,1')
    ->name('login.submit');
Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

Route::get('/', [DashboardController::class, 'index'])->name('dashboard');
Route::get('/about', [AboutController::class, 'show'])->name('about');

// Global search across tables of the active connection
Route::get('/{connection}/search', [DatabaseController::class, 'search'])->name('search');

// Export / Import (database level)
Route::get('/{connection}/export', [ExportController::class, 'database'])->name('database.export');
Route::post('/{connection}/export-custom', [ExportController::class, 'custom'])->name('database.export.custom');
Route::get('/{connection}/import', [ImportController::class, 'form'])->name('database.import.form');
Route::post('/{connection}/import', [ImportController::class, 'importSql'])->name('database.import');

// ER diagram
Route::get('/{connection}/er', [ErController::class, 'show'])->name('er.show');
Route::post('/{connection}/er/views', [ErController::class, 'saveView'])->name('er.view.save');
Route::delete('/{connection}/er/views/{id}', [ErController::class, 'deleteView'])->name('er.view.delete');

// Database objects (views/routines/triggers/events)
Route::get('/{connection}/objects', [DbObjectsController::class, 'index'])->name('objects.index');
Route::delete('/{connection}/objects/{kind}/{name}', [DbObjectsController::class, 'destroy'])
    ->where('kind', 'view|routine|trigger|event')
    ->name('objects.destroy');

// Database / connection level
Route::get('/{connection}', [DatabaseController::class, 'show'])->name('database.show');

// SQL editor
Route::get('/{connection}/sql', [SqlController::class, 'show'])->name('sql.show');
Route::post('/{connection}/sql', [SqlController::class, 'run'])->name('sql.run');

// Table level
Route::get('/{connection}/t/{table}', [TableController::class, 'browse'])->name('table.browse');
Route::get('/{connection}/t/{table}/structure', [TableController::class, 'structure'])->name('table.structure');
Route::get('/{connection}/t/{table}/tree', [TableController::class, 'tree'])->name('table.tree');
Route::get('/{connection}/t/{table}/info', [TableController::class, 'info'])->name('table.info');
Route::get('/{connection}/t/{table}/export', [ExportController::class, 'table'])->name('table.export');
Route::get('/{connection}/t/{table}/import', [ImportController::class, 'form'])->name('table.import.form');
Route::post('/{connection}/t/{table}/import-csv', [ImportController::class, 'importCsv'])->name('table.import.csv');
Route::delete('/{connection}/t/{table}/fk/{fk}', [TableController::class, 'dropForeignKey'])->name('table.fk.drop');

// Schema mutations
Route::get('/{connection}/create-table', [SchemaController::class, 'createTableForm'])->name('table.create.form');
Route::post('/{connection}/create-table', [SchemaController::class, 'createTable'])->name('table.create');

Route::post('/{connection}/t/{table}/columns', [SchemaController::class, 'addColumn'])->name('column.add');
Route::put('/{connection}/t/{table}/columns/{column}', [SchemaController::class, 'modifyColumn'])->name('column.modify');
Route::post('/{connection}/t/{table}/columns/{column}/rename', [SchemaController::class, 'renameColumn'])->name('column.rename');
Route::delete('/{connection}/t/{table}/columns/{column}', [SchemaController::class, 'dropColumn'])->name('column.drop');

Route::post('/{connection}/t/{table}/indexes', [SchemaController::class, 'addIndex'])->name('index.add');
Route::delete('/{connection}/t/{table}/indexes/{index}', [SchemaController::class, 'dropIndex'])->name('index.drop');

Route::post('/{connection}/t/{table}/foreign-keys', [SchemaController::class, 'addForeignKey'])->name('fk.add');

Route::post('/{connection}/t/{table}/truncate', [SchemaController::class, 'truncate'])->name('table.truncate');
Route::delete('/{connection}/t/{table}', [SchemaController::class, 'dropTable'])->name('table.drop');
Route::post('/{connection}/t/{table}/rename', [SchemaController::class, 'renameTable'])->name('table.rename');

// Row level
Route::get('/{connection}/t/{table}/create', [RowController::class, 'create'])->name('row.create');
Route::post('/{connection}/t/{table}/create', [RowController::class, 'store'])->name('row.store');
Route::post('/{connection}/t/{table}/bulk-delete', [RowController::class, 'bulkDestroy'])->name('row.bulk-destroy');
Route::patch('/{connection}/t/{table}/cell', [RowController::class, 'updateCell'])->name('row.cell.update');
Route::get('/{connection}/t/{table}/fk-options', [RowController::class, 'fkOptions'])->name('row.fk.options');

// IMPORTANT: register /edit FIRST so the greedy {rowKey} on row.show
// doesn't swallow the trailing "/edit" segment.
Route::get('/{connection}/t/{table}/r/{rowKey}/edit', [RowController::class, 'edit'])
    ->where('rowKey', '[^/]+')
    ->name('row.edit');
Route::put('/{connection}/t/{table}/r/{rowKey}', [RowController::class, 'update'])
    ->where('rowKey', '[^/]+')
    ->name('row.update');
Route::delete('/{connection}/t/{table}/r/{rowKey}', [RowController::class, 'destroy'])
    ->where('rowKey', '[^/]+')
    ->name('row.destroy');
Route::get('/{connection}/t/{table}/r/{rowKey}', [RowController::class, 'show'])
    ->where('rowKey', '[^/]+')
    ->name('row.show');
