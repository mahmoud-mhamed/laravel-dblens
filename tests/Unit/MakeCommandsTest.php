<?php

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;

beforeEach(function () {
    Schema::connection('sqlite')->create('orders', function ($t) {
        $t->id();
        $t->string('email');
        $t->string('status');
        $t->decimal('total', 10, 2);
        $t->timestamps();
        $t->softDeletes();
    });
    $this->out = sys_get_temp_dir() . '/dblens_make_' . uniqid();
    File::makeDirectory($this->out, 0755, true);
});

afterEach(function () {
    Schema::connection('sqlite')->dropIfExists('orders');
    File::deleteDirectory($this->out);
});

it('make-model generates a model with fillable, casts and SoftDeletes', function () {
    $this->artisan('dblens:make-model', ['table' => 'orders', '--path' => $this->out])->assertExitCode(0);

    $code = File::get($this->out . '/Order.php');
    expect($code)->toContain('class Order extends Model')
        ->toContain('use Illuminate\Database\Eloquent\SoftDeletes;')
        ->toContain('use HasFactory, SoftDeletes;')
        ->toContain("'total' => 'decimal:2'")
        ->toContain("protected \$fillable = ['email', 'status', 'total']");
});

it('make-factory generates Faker definitions for the fillable columns', function () {
    $this->artisan('dblens:make-factory', ['table' => 'orders', '--path' => $this->out])->assertExitCode(0);

    $code = File::get($this->out . '/OrderFactory.php');
    expect($code)->toContain('class OrderFactory extends Factory')
        ->toContain("'email' => fake()->safeEmail()")
        ->toContain("'total' => fake()->randomFloat(2, 0, 1000)");
});

it('make-seeder generates a seeder that calls the factory', function () {
    $this->artisan('dblens:make-seeder', ['table' => 'orders', '--count' => 25, '--path' => $this->out])->assertExitCode(0);

    $code = File::get($this->out . '/OrderSeeder.php');
    expect($code)->toContain('class OrderSeeder extends Seeder')
        ->toContain('Order::factory()->count(25)->create();');
});
