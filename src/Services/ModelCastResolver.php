<?php

namespace MahmoudMhamed\DbLens\Services;

use Illuminate\Database\Eloquent\Model;
use ReflectionClass;
use ReflectionEnum;

class ModelCastResolver
{
    /** @var array<string,array<string,string>>|null table => column => enumClass */
    protected ?array $map = null;

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

    public function castFor(string $table, string $column): ?string
    {
        $map = $this->getEnumCasts();
        return $map[$table][$column] ?? null;
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
                $casts = $instance->getCasts();

                foreach ($casts as $col => $cast) {
                    // Strip the modifiers Laravel allows: "App\Foo:arg1,arg2"
                    $castClass = is_string($cast) ? explode(':', $cast)[0] : null;
                    if (! $castClass || ! class_exists($castClass)) continue;
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
