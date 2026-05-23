<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use MahmoudMhamed\DbLens\Services\ConnectionManager;
use MahmoudMhamed\DbLens\Services\SchemaInspector;

beforeEach(function () {
    Schema::connection('sqlite')->create('items', function ($t) {
        $t->id();
        $t->string('name');
        $t->integer('qty')->nullable();
        $t->timestamps();
    });
});

afterEach(function () {
    Schema::connection('sqlite')->dropIfExists('items');
});

it('lists tables on the configured connection', function () {
    $schema = app(SchemaInspector::class);
    $names = array_map(fn ($t) => $t['name'], $schema->tables('sqlite'));
    expect($names)->toContain('items');
});

it('caches lookups within the request scope', function () {
    $schema = app(SchemaInspector::class);
    $schema->columns('sqlite', 'items'); // prime cache

    DB::connection('sqlite')->enableQueryLog();
    $schema->columns('sqlite', 'items');
    $schema->columns('sqlite', 'items');
    expect(DB::connection('sqlite')->getQueryLog())->toBeEmpty();
});

it('reports tableExists correctly', function () {
    $schema = app(SchemaInspector::class);
    expect($schema->tableExists('sqlite', 'items'))->toBeTrue();
    expect($schema->tableExists('sqlite', 'nope'))->toBeFalse();
});
