<?php

namespace MahmoudMhamed\DbLens\Services;

/**
 * Thin wrapper around stancl/tenancy (v3+). Silently no-ops when the package
 * isn't installed. Used by DbLens to:
 *
 *   - detect whether a tenant is currently initialized (subdomain auto-init)
 *   - report the active tenant's id / name to the UI
 *
 * DbLens itself does NOT call `tenancy()->initialize()` in this mode — the
 * host application's middleware (InitializeTenancyByDomain etc.) has already
 * switched the default `tenant` DB connection by the time the DbLens route
 * runs. We just describe what's already in place.
 */
class TenancyManager
{
    public function isAvailable(): bool
    {
        return class_exists(\Stancl\Tenancy\Tenancy::class)
            || function_exists('tenant')
            || function_exists('tenancy');
    }

    public function isInitialized(): bool
    {
        if (! $this->isAvailable()) return false;
        try {
            if (function_exists('tenancy')) {
                return (bool) tenancy()->initialized;
            }
            if (function_exists('tenant')) {
                return tenant() !== null;
            }
        } catch (\Throwable $e) {
            return false;
        }
        return false;
    }

    /**
     * @return array{id:string,name:?string,domain:?string}|null
     */
    public function current(): ?array
    {
        if (! $this->isInitialized()) return null;
        try {
            $t = function_exists('tenant') ? tenant() : tenancy()->tenant;
            if (! $t) return null;
            $id = method_exists($t, 'getTenantKey') ? $t->getTenantKey() : ($t->id ?? null);
            $name = $t->name ?? $t->title ?? null;
            $domain = null;
            if (method_exists($t, 'domains')) {
                $first = $t->domains->first();
                $domain = $first->domain ?? null;
            }
            return [
                'id' => (string) $id,
                'name' => $name,
                'domain' => $domain,
            ];
        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * Name of the dynamic tenant connection (defaults to 'tenant').
     */
    public function connectionName(): string
    {
        return (string) (config('dblens.tenancy.connection_name') ?: 'tenant');
    }
}
