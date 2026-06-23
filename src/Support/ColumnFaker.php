<?php

namespace MahmoudMhamed\DbLens\Support;

use Faker\Generator;

/**
 * Maps a DB column (name + type) to a Faker "kind", then emits either a Faker
 * PHP expression string (for `dblens:make-factory`) or an actual value (for
 * the in-UI data generator). Both consumers share ::kind() so the heuristics
 * stay in one place.
 */
class ColumnFaker
{
    public static function kind(array $column): string
    {
        $name = strtolower((string) ($column['name'] ?? ''));
        $type = strtolower((string) ($column['type'] ?? ''));

        // Name-based heuristics (more specific than type).
        if (str_contains($name, 'email')) return 'email';
        if ($name === 'password' || str_ends_with($name, '_password')) return 'password';
        if (str_contains($name, 'first_name')) return 'firstName';
        if (str_contains($name, 'last_name')) return 'lastName';
        if ($name === 'name' || str_ends_with($name, '_name') || str_contains($name, 'username')) return 'name';
        if (str_contains($name, 'phone') || str_contains($name, 'mobile')) return 'phone';
        if (str_contains($name, 'uuid')) return 'uuid';
        if (str_contains($name, 'url') || str_contains($name, 'website') || str_contains($name, 'link')) return 'url';
        if (str_contains($name, 'slug')) return 'slug';
        if ($name === 'title' || str_ends_with($name, '_title')) return 'title';
        if (in_array($name, ['description', 'bio', 'content', 'body', 'notes', 'comment', 'message'], true)) return 'paragraph';
        if (str_contains($name, 'address')) return 'address';
        if ($name === 'city') return 'city';
        if ($name === 'country') return 'country';
        if (str_contains($name, 'company')) return 'company';
        if (str_contains($name, 'color') || str_contains($name, 'colour')) return 'color';
        if (str_contains($name, 'price') || str_contains($name, 'amount') || str_contains($name, 'total') || str_contains($name, 'cost')) return 'price';
        if ($name === 'age') return 'age';

        // Type-based.
        if (preg_match('/^enum\s*\((.+)\)$/i', $type)) return 'enum';
        if (preg_match('/tinyint\(1\)/', $type) || str_starts_with($type, 'bool')) return 'bool';
        if (preg_match('/^(tinyint|smallint|mediumint|bigint|int|integer)/', $type)) return 'int';
        if (preg_match('/^(decimal|numeric|float|double|real)/', $type)) return 'decimal';
        if (str_starts_with($type, 'json')) return 'json';
        if (str_starts_with($type, 'datetime') || str_starts_with($type, 'timestamp')) return 'datetime';
        if (str_starts_with($type, 'date')) return 'date';
        if (str_starts_with($type, 'time')) return 'time';
        if (str_starts_with($type, 'year')) return 'year';
        if (preg_match('/(text|blob)/', $type)) return 'paragraph';
        if (preg_match('/^(varchar|char|string|nvarchar|nchar)/', $type)) return 'sentence';

        return 'word';
    }

    /** Allowed values parsed from an enum('a','b') type string. */
    public static function enumValues(string $type): array
    {
        if (! preg_match('/^enum\s*\((.+)\)$/i', trim($type), $m)) return [];
        $out = [];
        if (preg_match_all("/'((?:[^'\\\\]|\\\\.)*)'/", $m[1], $mm)) {
            foreach ($mm[1] as $v) $out[] = stripcslashes($v);
        }
        return $out;
    }

    /** A Faker PHP expression string (emitted into a generated factory). */
    public static function expression(array $column): string
    {
        $type = (string) ($column['type'] ?? '');
        return match (self::kind($column)) {
            'email'     => 'fake()->safeEmail()',
            'password'  => "bcrypt('password')",
            'firstName' => 'fake()->firstName()',
            'lastName'  => 'fake()->lastName()',
            'name'      => 'fake()->name()',
            'phone'     => 'fake()->phoneNumber()',
            'uuid'      => 'fake()->uuid()',
            'url'       => 'fake()->url()',
            'slug'      => 'fake()->slug()',
            'title'     => 'fake()->sentence(3)',
            'paragraph' => 'fake()->paragraph()',
            'address'   => 'fake()->address()',
            'city'      => 'fake()->city()',
            'country'   => 'fake()->country()',
            'company'   => 'fake()->company()',
            'color'     => 'fake()->safeColorName()',
            'price', 'decimal' => 'fake()->randomFloat('.self::decimalScale($type).', 0, '.self::decimalMax($type).')',
            'age'       => 'fake()->numberBetween(18, 80)',
            'bool'      => 'fake()->boolean()',
            'int'       => 'fake()->numberBetween(1, '.min(1000, self::intMax($type)).')',
            'json'      => "json_encode(['key' => fake()->word()])",
            'datetime', 'date' => "fake()->dateTimeBetween('-1 year')->format('Y-m-d H:i:s')",
            'time'      => 'fake()->time()',
            'year'      => 'fake()->year()',
            'enum'      => self::enumExpression($type),
            'sentence'  => 'fake()->sentence()',
            default     => 'fake()->word()',
        };
    }

    protected static function enumExpression(string $type): string
    {
        $vals = self::enumValues($type);
        if (empty($vals)) return 'fake()->word()';
        $list = implode(', ', array_map(fn ($v) => "'".addslashes($v)."'", $vals));
        return "fake()->randomElement([{$list}])";
    }

    /** An actual value for a column (used by the in-UI generator). */
    public static function value(Generator $faker, array $column): mixed
    {
        $type = (string) ($column['type'] ?? '');
        return match (self::kind($column)) {
            'email'     => $faker->safeEmail(),
            'password'  => bcrypt('password'),
            'firstName' => $faker->firstName(),
            'lastName'  => $faker->lastName(),
            'name'      => $faker->name(),
            'phone'     => $faker->phoneNumber(),
            'uuid'      => $faker->uuid(),
            'url'       => $faker->url(),
            'slug'      => $faker->slug(),
            'title'     => $faker->sentence(3),
            'paragraph' => $faker->paragraph(),
            'address'   => $faker->address(),
            'city'      => $faker->city(),
            'country'   => $faker->country(),
            'company'   => $faker->company(),
            'color'     => $faker->safeColorName(),
            'price', 'decimal' => $faker->randomFloat(self::decimalScale($type), 0, self::decimalMax($type)),
            'age'       => $faker->numberBetween(18, 80),
            'bool'      => $faker->boolean() ? 1 : 0,
            'int'       => $faker->numberBetween(1, min(1000, self::intMax($type))),
            'json'      => json_encode(['key' => $faker->word()]),
            'datetime', 'date' => $faker->dateTimeBetween('-1 year')->format('Y-m-d H:i:s'),
            'time'      => $faker->time(),
            'year'      => (string) $faker->year(),
            'enum'      => self::enumValue($faker, $type),
            'sentence'  => $faker->sentence(),
            default     => $faker->word(),
        };
    }

    protected static function enumValue(Generator $faker, string $type): mixed
    {
        $vals = self::enumValues($type);
        return empty($vals) ? $faker->word() : $faker->randomElement($vals);
    }

    /** Max value that fits the integer subtype, so generated data never overflows. */
    public static function intMax(string $type): int
    {
        $t = strtolower($type);
        $unsigned = str_contains($t, 'unsigned');
        if (str_starts_with($t, 'tinyint'))   return $unsigned ? 255 : 127;
        if (str_starts_with($t, 'smallint'))  return $unsigned ? 65535 : 32767;
        if (str_starts_with($t, 'mediumint')) return $unsigned ? 16777215 : 8388607;

        return 1000000; // int / integer / bigint — capped for sane fake data
    }

    /** Decimal scale (digits after the point), e.g. decimal(10,2) → 2. */
    public static function decimalScale(string $type): int
    {
        return preg_match('/\(\s*\d+\s*,\s*(\d+)\s*\)/', $type, $m) ? (int) $m[1] : 2;
    }

    /** Largest value that fits decimal(p,s): 10^(p-s) - 1, capped. */
    public static function decimalMax(string $type): float
    {
        if (preg_match('/\(\s*(\d+)\s*,\s*(\d+)\s*\)/', $type, $m)) {
            $intDigits = max(1, (int) $m[1] - (int) $m[2]);

            return (float) min((10 ** $intDigits) - 1, 1000000);
        }

        return 1000.0;
    }
}

