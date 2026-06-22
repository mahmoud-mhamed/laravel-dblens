<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use MahmoudMhamed\DbLens\Services\RowEditor;

beforeEach(function () {
    Schema::connection('sqlite')->create('widgets', function ($t) {
        $t->id();
        $t->string('name');
        $t->integer('qty')->default(0);
    });
    DB::connection('sqlite')->table('widgets')->insert([
        ['id' => 1, 'name' => 'gizmo', 'qty' => 7],
    ]);
});

afterEach(fn () => Schema::connection('sqlite')->dropIfExists('widgets'));

$widgets = fn () => DB::connection('sqlite')->table('widgets');

it('duplicate inserts a copy with a fresh primary key', function () use ($widgets) {
    $pk = app(RowEditor::class)->duplicate('sqlite', 'widgets', ['id' => 1]);

    expect($widgets()->count())->toBe(2);
    // new row has a different id but the same data
    expect((int) $pk['id'])->not->toBe(1);
    $copy = $widgets()->where('id', $pk['id'])->first();
    expect($copy->name)->toBe('gizmo');
    expect((int) $copy->qty)->toBe(7);
    // original untouched
    expect($widgets()->where('id', 1)->value('name'))->toBe('gizmo');
});
