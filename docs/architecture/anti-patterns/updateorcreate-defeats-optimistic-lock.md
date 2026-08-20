# Anti-pattern: `updateOrCreate` in a repository `save()`

Captured from the Activity slice review (2026-08-21). Severity when found: 🟠 important.

## Symptom ❌

```php
ActivityModel::query()->updateOrCreate(
    ['id' => $snapshot['id']],   // ❌ match key omits tenant_id AND version
    [...],
);
```

Two problems: the match half is not tenant-scoped, and a replay silently
**overwrites** an existing aggregate — blowing past optimistic locking, which
then becomes impossible to add without rewriting this line.

## Fix ✅

```php
// Insert-only path: let the unique constraint reject duplicates.
ActivityModel::query()->create([...]);

// Update path: explicit optimistic lock.
$affected = ActivityModel::query()
    ->where('id', $id)
    ->where('tenant_id', $tenant)
    ->where('version', $expectedVersion)
    ->update([...'version' => $expectedVersion + 1]);

if ($affected === 0) {
    throw new ConcurrencyException();
}
```

## The Rule

**A repository never upserts on `id` alone.** Inserts use `create()`; updates use
an explicit `where(id, tenant_id, version)->update()` guarded by an affected-rows
check that raises `ConcurrencyException`. Every query is tenant-scoped.
