<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use MahmoudMhamed\DbLens\Services\RowEditor;

beforeEach(function () {
    Schema::connection('sqlite')->create('products', function ($t) {
        $t->id();
        $t->string('name');
        $t->string('status')->nullable();
    });
    DB::connection('sqlite')->table('products')->insert([
        ['id' => 1, 'name' => 'a', 'status' => 'draft'],
        ['id' => 2, 'name' => 'b', 'status' => 'draft'],
        ['id' => 3, 'name' => 'c', 'status' => 'archived'],
    ]);
});

afterEach(fn () => Schema::connection('sqlite')->dropIfExists('products'));

$products = fn () => DB::connection('sqlite')->table('products');

it('bulkUpdate sets one column to a value across the selected rows', function () use ($products) {
    $n = app(RowEditor::class)->bulkUpdate('sqlite', 'products', [['id' => 1], ['id' => 2]], 'status', 'published');

    expect($n)->toBe(2);
    expect($products()->where('id', 1)->value('status'))->toBe('published');
    expect($products()->where('id', 2)->value('status'))->toBe('published');
    expect($products()->where('id', 3)->value('status'))->toBe('archived'); // untouched
});

it('bulkUpdate coerces the __NULL__ sentinel to NULL on a nullable column', function () use ($products) {
    $n = app(RowEditor::class)->bulkUpdate('sqlite', 'products', [['id' => 3]], 'status', '__NULL__');

    expect($n)->toBe(1);
    expect($products()->where('id', 3)->value('status'))->toBeNull();
});
