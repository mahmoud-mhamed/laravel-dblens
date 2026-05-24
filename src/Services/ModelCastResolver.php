<?php

namespace MahmoudMhamed\DbLens\Services;

use Illuminate\Database\Eloquent\Model;
use ReflectionClass;
use ReflectionEnum;

class ModelCastResolver
{
    /** @var array<string,array<string,string>>|null table => column => enumClass */
    protected ?array $map = null;

    /** @var array<string,array<string,true>>|null table => column => true (columns with `hashed` cast) */
    protected ?array $hashedMap = null;

    /** @var array<string,string>|null table => model FQCN */
    protected ?array $modelMap = null;

    /**
     * Get all detected enum casts keyed by table => column => enum class FQCN.
     */
    public function getEnumCasts(): array
    {
        if ($this->map !== null) {
            return $this->map;
        }
        $map = $this->scanModels();
        // merge manual overrides last so they win
        foreach ((array) config('dblens.casts', []) as $table => $cols) {
            foreach ((array) $cols as $col => $enumClass) {
                if (is_string($enumClass) && enum_exists($enumClass)) {
                    $map[$table][$col] = $enumClass;
                }
            }
        }
        return $this->map = $map;
    }

    /**
     * Allow callers to override / preload the table → model map. Useful for
     * apps whose models live outside the configured `models_path`.
     *
     * @param  array<string,string>  $map  table => model FQCN
     */
    public function setTableModelMap(array $map): void
    {
        $this->modelMap = array_filter($map, fn ($c) => is_string($c) && class_exists($c));
    }

    public function castFor(string $table, string $column): ?string
    {
        $map = $this->getEnumCasts();
        return $map[$table][$column] ?? null;
    }

    /**
     * Columns on a table that the Eloquent model declares as `hashed` cast.
     * Used to auto-bcrypt values written through the row editor.
     *
     * @return array<string,true>  column => true
     */
    public function hashedColumns(string $table): array
    {
        if ($this->hashedMap === null) {
            $this->scanModels();
        }
        return $this->hashedMap[$table] ?? [];
    }

    public function isHashed(string $table, string $column): bool
    {
        return isset($this->hashedColumns($table)[$column]);
    }

    /**
     * Return the Eloquent model FQCN bound to a given table, or null if none
     * was found in the configured models path. Result is cached for the life
     * of the request.
     */
    public function modelFor(string $table): ?string
    {
        if ($this->modelMap === null) {
            $this->scanModels();
        }
        return $this->modelMap[$table] ?? null;
    }

    /**
     * @return array<string,string>  table => model FQCN
     */
    public function tableModelMap(): array
    {
        if ($this->modelMap === null) {
            $this->scanModels();
        }
        return $this->modelMap ?? [];
    }

    /**
     * Return cases of an enum class as [{value, name, label}].
     *
     * @return array<int,array{value:mixed,name:string,label:string}>
     */
    public function enumCases(string $enumClass): array
    {
        if (! enum_exists($enumClass)) return [];
        $r = new ReflectionEnum($enumClass);
        $isBacked = $r->isBacked();
        $out = [];
        foreach ($enumClass::cases() as $case) {
            $value = $isBacked ? $case->value : $case->name;
            $label = $case->name;
            if (method_exists($case, 'label')) {
                try { $label = (string) $case->label(); } catch (\Throwable $e) {}
            }
            $out[] = [
                'value' => $value,
                'name' => $case->name,
                'label' => (string) $value === $label ? $label : ($value . ' — ' . $label),
            ];
        }
        return $out;
    }

    /**
     * @return array<string,array<string,string>>
     */
    protected function scanModels(): array
    {
        $this->modelMap = [];
        $this->hashedMap = [];
        $path = config('dblens.models_path');
        if (! $path || ! is_dir($path)) {
            return [];
        }
        $baseNs = rtrim((string) config('dblens.models_namespace', 'App\\Models'), '\\');

        $map = [];
        $iter = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($path, \FilesystemIterator::SKIP_DOTS));
        foreach ($iter as $file) {
            if (! $file->isFile() || $file->getExtension() !== 'php') continue;

            $rel = substr($file->getPathname(), strlen($path) + 1);
            $class = $baseNs . '\\' . str_replace(['/', '.php'], ['\\', ''], $rel);
            if (! class_exists($class)) continue;

            try {
                $ref = new ReflectionClass($class);
                if (! $ref->isSubclassOf(Model::class) || $ref->isAbstract()) continue;

                /** @var Model $instance */
                $instance = $ref->newInstanceWithoutConstructor();
                $table = $instance->getTable();

                // Pick the first concrete model we see per table. Two models
                // sharing a table (e.g. a base + a child) is rare; the first
                // wins and the rest are silently ignored.
                if (! isset($this->modelMap[$table])) {
                    $this->modelMap[$table] = $class;
                }

                $casts = $instance->getCasts();
                foreach ($casts as $col => $cast) {
                    // Strip the modifiers Laravel allows: "App\Foo:arg1,arg2"
                    $castClass = is_string($cast) ? explode(':', $cast)[0] : null;
                    if (! $castClass) continue;
                    if ($castClass === 'hashed') {
                        $this->hashedMap[$table][$col] = true;
                        continue;
                    }
                    if (! class_exists($castClass)) continue;
                    if (enum_exists($castClass)) {
                        $map[$table][$col] = $castClass;
                    }
                }
            } catch (\Throwable $e) {
                // skip un-instantiable / broken models
                continue;
            }
        }
        return $map;
    }
}
