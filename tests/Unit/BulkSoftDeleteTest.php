<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use MahmoudMhamed\DbLens\Services\RowEditor;

beforeEach(function () {
    Schema::connection('sqlite')->create('posts', function ($t) {
        $t->id();
        $t->string('title');
        $t->softDeletes();
    });
    DB::connection('sqlite')->table('posts')->insert([
        ['id' => 1, 'title' => 'one'],
        ['id' => 2, 'title' => 'two'],
        ['id' => 3, 'title' => 'three'],
    ]);
});

afterEach(fn () => Schema::connection('sqlite')->dropIfExists('posts'));

$posts = fn () => DB::connection('sqlite')->table('posts');

it('bulkSoftDelete sets deleted_at instead of removing rows', function () use ($posts) {
    $n = app(RowEditor::class)->bulkSoftDelete('sqlite', 'posts', [['id' => 1], ['id' => 2]]);

    expect($n)->toBe(2);
    expect($posts()->count())->toBe(3); // nothing physically removed
    expect($posts()->whereNotNull('deleted_at')->pluck('id')->all())
        ->toEqualCanonicalizing([1, 2]);
    expect($posts()->where('id', 3)->value('deleted_at'))->toBeNull();
});

it('bulkDelete physically removes the selected rows', function () use ($posts) {
    $n = app(RowEditor::class)->bulkDelete('sqlite', 'posts', [['id' => 1]]);

    expect($n)->toBe(1);
    expect($posts()->count())->toBe(2);
    expect($posts()->where('id', 1)->exists())->toBeFalse();
});
