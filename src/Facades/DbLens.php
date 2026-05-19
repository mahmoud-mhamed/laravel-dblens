<?php

namespace MahmoudMhamed\DbLens\Facades;

use Illuminate\Support\Facades\Facade;
use MahmoudMhamed\DbLens\Services\SchemaInspector;

class DbLens extends Facade
{
    protected static function getFacadeAccessor()
    {
        return SchemaInspector::class;
    }
}
