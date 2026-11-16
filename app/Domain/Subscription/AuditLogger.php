<?php

namespace App\Domain\Subscription;

use App\Models\AuditLog;
use App\Support\Tenancy\TenantContext;
use Illuminate\Database\Eloquent\Model;

class AuditLogger
{
    /**
     * @param  array<string, mixed>|null  $before
     * @param  array<string, mixed>|null  $after
     */
    public function log(string $action, ?Model $model, ?array $before, ?array $after): void
    {
        $request = request();

        AuditLog::query()->create([
            'tenant_id' => app(TenantContext::class)->id() ?? $model?->getAttribute('tenant_id'),
            'user_id' => auth()->id(),
            'action' => $action,
            'auditable_type' => $model === null ? 'system' : $model::class,
            'auditable_id' => $model?->getKey(),
            'before' => $before === null ? null : $this->scrub($before),
            'after' => $after === null ? null : $this->scrub($after),
            'ip' => $request?->ip(),
            'user_agent' => $request ? mb_substr((string) $request->userAgent(), 0, 255) : null,
        ]);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function scrub(array $data): array
    {
        foreach ($data as $key => $value) {
            if (is_string($key) && str_contains(strtolower($key), 'password')) {
                unset($data[$key]);

                continue;
            }

            if (is_array($value)) {
                $data[$key] = $this->scrub($value);
            }
        }

        return $data;
    }
}
