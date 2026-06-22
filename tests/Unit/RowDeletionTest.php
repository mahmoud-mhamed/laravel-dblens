<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use MahmoudMhamed\DbLens\Services\RowEditor;
use MahmoudMhamed\DbLens\Services\TableEditor;

/**
 * `authors` (1, 2) ← `books` (author_id FK). Author 1 has 2 books, author 2
 * has 1. Foreign keys are enforced (see TestCase::defineEnvironment), so a
 * plain delete of a referenced author must fail while disable_checks / cascade
 * succeed in their respective ways.
 */
beforeEach(function () {
    Schema::connection('sqlite')->create('authors', function ($t) {
        $t->id();
        $t->string('name');
    });
    Schema::connection('sqlite')->create('books', function ($t) {
        $t->id();
        $t->foreignId('author_id')->constrained('authors');
        $t->string('title');
    });

    DB::connection('sqlite')->table('authors')->insert([
        ['id' => 1, 'name' => 'A'],
        ['id' => 2, 'name' => 'B'],
    ]);
    DB::connection('sqlite')->table('books')->insert([
        ['id' => 1, 'author_id' => 1, 'title' => 'A1'],
        ['id' => 2, 'author_id' => 1, 'title' => 'A2'],
        ['id' => 3, 'author_id' => 2, 'title' => 'B1'],
    ]);
});

afterEach(function () {
    Schema::connection('sqlite')->dropIfExists('books');
    Schema::connection('sqlite')->dropIfExists('authors');
});

$authors = fn () => DB::connection('sqlite')->table('authors');
$books = fn () => DB::connection('sqlite')->table('books');

// ── single-row delete ────────────────────────────────────────────────────

it('plain single-row delete fails when the row is referenced', function () use ($authors) {
    $editor = app(RowEditor::class);

    expect(fn () => $editor->delete('sqlite', 'authors', ['id' => 1]))
        ->toThrow(Illuminate\Database\QueryException::class);
    expect($authors()->where('id', 1)->exists())->toBeTrue();
});

it('single-row delete with disable_checks removes the row and orphans children', function () use ($authors, $books) {
    $editor = app(RowEditor::class);

    $n = $editor->delete('sqlite', 'authors', ['id' => 1], 'disable_checks');

    expect($n)->toBe(1);
    expect($authors()->where('id', 1)->exists())->toBeFalse();
    expect($books()->where('author_id', 1)->count())->toBe(2); // left orphaned
});

it('single-row delete with cascade removes the row and the rows referencing it', function () use ($authors, $books) {
    $editor = app(RowEditor::class);

    $n = $editor->delete('sqlite', 'authors', ['id' => 1], 'cascade');

    expect($n)->toBe(1);
    expect($authors()->where('id', 1)->exists())->toBeFalse();
    expect($books()->where('author_id', 1)->count())->toBe(0);
    // author 2 and its book are untouched
    expect($authors()->where('id', 2)->exists())->toBeTrue();
    expect($books()->where('author_id', 2)->count())->toBe(1);
});

// ── bulk delete ──────────────────────────────────────────────────────────

it('bulk delete with cascade removes the selected rows and their references', function () use ($authors, $books) {
    $editor = app(RowEditor::class);

    $n = $editor->bulkDelete('sqlite', 'authors', [['id' => 1], ['id' => 2]], 'cascade');

    expect($n)->toBe(2);
    expect($authors()->count())->toBe(0);
    expect($books()->count())->toBe(0);
});

// ── delete-all (TableEditor) ─────────────────────────────────────────────

it('deleteAllRows plain empties a table with no referencing rows', function () use ($books) {
    $res = app(TableEditor::class)->deleteAllRows('sqlite', 'books', 'none');

    expect($res['affected'])->toBe(3);
    expect($res['related'])->toBe([]);
    expect($books()->count())->toBe(0);
});

it('deleteAllRows cascade empties the table and every table referencing it', function () use ($authors, $books) {
    $res = app(TableEditor::class)->deleteAllRows('sqlite', 'authors', 'cascade');

    expect($res['affected'])->toBe(2);
    expect($res['related'])->toHaveKey('books');
    expect($res['related']['books'])->toBe(3);
    expect($res['total'])->toBe(5);
    expect($authors()->count())->toBe(0);
    expect($books()->count())->toBe(0);
});

it('deleteAllRows disable_checks empties the table and orphans references', function () use ($authors, $books) {
    $res = app(TableEditor::class)->deleteAllRows('sqlite', 'authors', 'disable_checks');

    expect($res['affected'])->toBe(2);
    expect($authors()->count())->toBe(0);
    expect($books()->count())->toBe(3); // left orphaned
});
