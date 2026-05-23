<?php

use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Schema;

beforeEach(function () {
    Gate::define('viewDbLens', fn ($user = null) => true);
    Schema::connection('sqlite')->create('widgets', fn ($t) => $t->id());
});

afterEach(fn () => Schema::connection('sqlite')->dropIfExists('widgets'));

it('dashboard responds', function () {
    $this->get('/dblens')->assertOk();
});

it('table browse responds', function () {
    $this->get('/dblens/sqlite/t/widgets')->assertOk();
});

it('db objects page responds', function () {
    $this->get('/dblens/sqlite/objects')->assertOk();
});
