# Phase 2 Summary – Property & Unit Management

## What's complete
- **Schema & migrations** – `properties` and `units` tables created (14 & 13 columns respectively), with `BelongsToBusiness` global scope, denormalised `business_id` on units, soft deletes, and proper indexes.
- **Models** – `Property` and `Unit` with `BelongsToBusiness` trait, defined relations (`Property.units()`, `Unit.property()`), casts (`decimal:1` for bathrooms, `decimal:2` for rent), and presentation helpers (`addressLine()`, `specification()`).
- **Factory** – `PropertyFactory` and `UnitFactory` with weighted selection, business assignment methods, studio/inactive/label helpers.
- **Policies** – `PropertyPolicy` and `UnitPolicy`, both using the new `AuthorisesTenantRecords` trait which enforces 404 for cross‑tenant record access. Policies validate membership, explicit business‑scoped permissions (`canInBusiness`), and guard parent‑property ownership for units.
- **Service** – `PropertyCatalog` owns all CRUD, audit logging, quota checks (`PlanQuota::assertCanAdd`) and the invariant that units always inherit their property's `business_id`. Deletes cascade soft‑deletes to their units.
- **Requests** – `StorePropertyRequest`, `UpdatePropertyRequest`, `StoreUnitRequest`, `UpdateUnitRequest` with enum validation, money regex, business‑scoped uniqueness, and a shared `propertyAttributes()`/`unitAttributes()` normaliser.
- **Controllers** – `PropertyController` and `UnitController` (nested under `/properties/{property}`) with `@can` gateways per CRUD action, quota-aware dashboard integration, and soft‑delete handling.
- **Views** – Full Blade UI: index, create, edit, show for properties; create/edit for units; portable `checkbox-field`, `text-field`, `select-field`, `textarea-field` components.
- **Middleware fix** – `bootstrap/app.php` reordered so `ResolveActiveBusiness` runs *before* `SubstituteBindings`, ensuring implicit route‑model bindings for tenant models respect the global scope.
- **Icons** – Added `pencil-square`, `trash`, `map-pin` to `x-icon` component.

## What remains
- **Dashboard integration** – placeholder quota tiles already read `PlanQuota::usageFor()`; once Property/Unit records exist the numbers appear automatically.
- **Test suite** – no Phase 2 tests written yet. Existing test count (106/328) unchanged.
- **Node/npm** – unavailable; Vite compile/browser verification blocked pending user install of Node 22 LTS.

## Key invariants
- `units.business_id` always copies the parent property's value; never accepted from form input.
- Quotas enforced **immediately before** INSERT in the **same transaction** via `PropertyCatalog`.
- Cross‑tenant record access returns **404**, never 403, to avoid confirming record existence.
- `commercial` and `land` PropertyTypes have no residential unit inventory; UI and policy both enforce this.

## Next steps
1. Run `vendor/bin/phpunit --no-coverage` to verify existing suite still passes.
2. Write Phase 2 feature tests (CRUD, permission gating, quota, cross‑tenant 404).
3. Have the user install Node 22 LTS to enable Vite builds and browser checks.