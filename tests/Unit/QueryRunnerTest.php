<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use MahmoudMhamed\DbLens\Services\ConnectionManager;
use MahmoudMhamed\DbLens\Services\QueryRunner;

beforeEach(function () {
    Schema::connection('sqlite')->create('posts', function ($t) {
        $t->id();
        $t->string('title');
        $t->string('body')->nullable();
    });
    DB::connection('sqlite')->table('posts')->insert([
        ['title' => 'first', 'body' => 'hello world'],
        ['title' => 'second', 'body' => 'hello pest'],
        ['title' => 'third', 'body' => null],
    ]);
});

afterEach(fn () => Schema::connection('sqlite')->dropIfExists('posts'));

it('browses rows with pagination', function () {
    $runner = app(QueryRunner::class);
    $result = $runner->browse('sqlite', 'posts', ['per_page' => 2, 'page' => 1]);

    expect($result['total'])->toBe(3);
    expect($result['rows'])->toHaveCount(2);
    expect($result['approximate'])->toBeFalse();
});

it('applies filters via buildWhere', function () {
    $runner = app(QueryRunner::class);
    $result = $runner->browse('sqlite', 'posts', [
        'filters' => [['column' => 'title', 'op' => '=', 'value' => 'first']],
    ]);
    expect($result['total'])->toBe(1);
    expect($result['rows'][0]['title'])->toBe('first');
});

it('search hits text columns', function () {
    $runner = app(QueryRunner::class);
    $result = $runner->browse('sqlite', 'posts', ['search' => 'hello']);
    expect($result['total'])->toBe(2);
});

it('rejects unknown filter columns', function () {
    $runner = app(QueryRunner::class);
    $result = $runner->browse('sqlite', 'posts', [
        'filters' => [['column' => 'nonexistent', 'op' => '=', 'value' => 'x']],
    ]);
    expect($result['total'])->toBe(3);
});

it('EXPLAIN runs without executing the query', function () {
    $runner = app(QueryRunner::class);
    $result = $runner->explain('sqlite', 'SELECT * FROM posts WHERE id = 1');
    expect($result['type'])->toBe('read');
    expect($result['rows'])->not->toBeEmpty();
});
