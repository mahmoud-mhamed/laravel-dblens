<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use MahmoudMhamed\DbLens\Services\RowEditor;

beforeEach(function () {
    Schema::connection('sqlite')->create('users', function ($t) {
        $t->id();
        $t->string('name');
        $t->string('email');
        $t->integer('age')->nullable();
    });
    Schema::connection('sqlite')->create('posts', function ($t) {
        $t->id();
        $t->foreignId('user_id')->constrained('users');
        $t->string('title', 20);
    });
});

afterEach(function () {
    Schema::connection('sqlite')->dropIfExists('posts');
    Schema::connection('sqlite')->dropIfExists('users');
});

it('generates the requested number of fake rows', function () {
    $n = app(RowEditor::class)->generateFake('sqlite', 'users', 5);

    expect($n)->toBe(5);
    expect(DB::connection('sqlite')->table('users')->count())->toBe(5);
    // emails were name-guessed to look like emails
    foreach (DB::connection('sqlite')->table('users')->pluck('email') as $email) {
        expect($email)->toContain('@');
    }
});

it('fills foreign-key columns with existing referenced ids and respects column length', function () {
    DB::connection('sqlite')->table('users')->insert(['id' => 1, 'name' => 'a', 'email' => 'a@b.c']);

    $n = app(RowEditor::class)->generateFake('sqlite', 'posts', 4);

    expect($n)->toBe(4);
    expect(DB::connection('sqlite')->table('posts')->count())->toBe(4);
    // only user 1 exists, so every post must reference it
    expect(DB::connection('sqlite')->table('posts')->pluck('user_id')->unique()->all())->toBe([1]);
});
