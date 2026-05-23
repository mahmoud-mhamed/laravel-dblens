<?php

namespace MahmoudMhamed\DbLens\Services;

use Illuminate\Support\Facades\Storage;

class ErViewStorage
{
    protected function diskName(): string
    {
        return (string) config('dblens.er.storage_disk', config('filesystems.default', 'local'));
    }

    protected function path(): string
    {
        $root = trim((string) config('dblens.er.storage_path', 'dblens'), '/');
        return ($root === '' ? '' : $root.'/').'er-views.json';
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    public function all(): array
    {
        $disk = Storage::disk($this->diskName());
        if (! $disk->exists($this->path())) return [];
        $raw = $disk->get($this->path()) ?: '[]';
        $data = json_decode($raw, true);
        return is_array($data) ? $data : [];
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    public function forConnection(string $connection): array
    {
        return array_values(array_filter($this->all(), fn ($v) => ($v['connection'] ?? null) === $connection));
    }

    public function find(string $id): ?array
    {
        foreach ($this->all() as $v) {
            if (($v['id'] ?? null) === $id) return $v;
        }
        return null;
    }

    public function save(array $view): array
    {
        $view['id'] ??= bin2hex(random_bytes(6));
        $view['created_at'] ??= now()->toIso8601String();
        $view['updated_at'] = now()->toIso8601String();

        $all = $this->all();
        $found = false;
        foreach ($all as &$existing) {
            if (($existing['id'] ?? null) === $view['id']) {
                $existing = $view;
                $found = true;
                break;
            }
        }
        unset($existing);
        if (! $found) $all[] = $view;

        $this->write($all);
        return $view;
    }

    public function delete(string $id): bool
    {
        $all = $this->all();
        $before = count($all);
        $all = array_values(array_filter($all, fn ($v) => ($v['id'] ?? null) !== $id));
        if (count($all) === $before) return false;
        $this->write($all);
        return true;
    }

    protected function write(array $data): void
    {
        Storage::disk($this->diskName())->put(
            $this->path(),
            json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
        );
    }
}
