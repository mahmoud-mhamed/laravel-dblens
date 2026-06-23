<?php

use MahmoudMhamed\DbLens\Support\ColumnFaker;

$faker = Faker\Factory::create();

it('only ever produces an allowed value for an enum column', function () use ($faker) {
    $col = ['name' => 'status', 'type' => "enum('draft','published','archived')"];

    expect(ColumnFaker::kind($col))->toBe('enum');
    for ($i = 0; $i < 50; $i++) {
        expect(ColumnFaker::value($faker, $col))->toBeIn(['draft', 'published', 'archived']);
    }
    expect(ColumnFaker::expression($col))->toContain('randomElement');
});

it('never overflows an integer subtype', function () use ($faker) {
    $tiny = ['name' => 'escalation_level', 'type' => 'tinyint(3)'];
    $small = ['name' => 'level', 'type' => 'smallint'];

    expect(ColumnFaker::intMax('tinyint(3)'))->toBe(127);
    expect(ColumnFaker::intMax('tinyint unsigned'))->toBe(255);
    expect(ColumnFaker::intMax('smallint'))->toBe(32767);

    for ($i = 0; $i < 100; $i++) {
        expect(ColumnFaker::value($faker, $tiny))->toBeLessThanOrEqual(127);
        expect(ColumnFaker::value($faker, $small))->toBeLessThanOrEqual(32767);
    }
});

it('respects decimal precision so values fit decimal(p,s)', function () use ($faker) {
    $col = ['name' => 'rate', 'type' => 'decimal(4,2)']; // max 99.99

    expect(ColumnFaker::decimalMax('decimal(4,2)'))->toBe(99.0);
    for ($i = 0; $i < 50; $i++) {
        expect(ColumnFaker::value($faker, $col))->toBeLessThanOrEqual(99.0);
    }
});
