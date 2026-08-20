# Anti-pattern: tenant identity from the request payload

Captured from the Activity slice review (2026-08-21). Severity when found: 🔴 critical.

## Symptom ❌

```php
// Form Request
'tenant_id' => ['required', 'string'],           // ❌ client decides the tenant

// Controller / use case
$tenant = TenantId::fromString($request->input('tenant_id')); // ❌ forgeable
```

Any caller can set `tenant_id: "tenant-victim"` and write into another tenant's
partition. Reads may be scoped correctly and the hole still exists on writes.

## Fix ✅

```php
// tenant_id is NOT in the Form Request rules at all.

final class Controller {
    public function __construct(private TenantContext $tenantContext) {}
    public function __invoke(Request $request) {
        $this->useCase->execute(
            $request->toInput(),
            new ExecutionContext($this->tenantContext->current()), // ✅ server-authoritative
        );
    }
}
```

`TenantContext` resolves the tenant from the auth guard (or a fixed dev tenant
until auth lands). The payload can never influence it.

## The Rule

**Tenant identity is server-authoritative, always.** It comes from the
authenticated context, never from the request body. Every repository query is
additionally scoped by that tenant.
