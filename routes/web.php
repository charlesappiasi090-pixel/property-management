<?php

use App\Enums\PermissionName;
use App\Enums\Role;
use App\Http\Controllers\Auth\RegisteredUserController;
use App\Http\Controllers\Backoffice\BusinessController;
use App\Http\Controllers\Backoffice\DashboardController;
use App\Http\Controllers\Backoffice\PropertyController;
use App\Http\Controllers\Backoffice\StaffController;
use App\Http\Controllers\Backoffice\SubscriptionController;
use App\Http\Controllers\Backoffice\UnitController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\Onboarding\OnboardingController;
use App\Http\Controllers\Tenancy\SwitchBusinessController;
use App\Models\Plan;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Route organisation follows the product, not the controllers:
|
|   /            -> marketing / root
|   /register    -> public signup, then onboarding
|   /onboarding  -> create your business (auth, no business yet)
|   /app/*       -> the back-office dashboard shell
|   /portal/*    -> the tenant portal (Phase 14)
|
| Tenant scoping is NOT done per route. `ResolveActiveBusiness` resolves the
| business and the `BelongsToBusiness` global scope filters every query, so a
| route can never accidentally read another landlord's rows.
|
*/

/*
|--------------------------------------------------------------------------
| Root
|--------------------------------------------------------------------------
|
| `/` is the router for the three states a visitor can be in, so a bookmark
| or a post-signup redirect always lands somewhere real:
|
|   guest                -> marketing page
|   signed in, no tenant  -> onboarding (a brand-new signup)
|   signed in, in a tenant -> the back-office dashboard
|
| Previously this redirected to a route named `dashboard`, which does not
| exist in this application (the dashboard is `app.dashboard`) — every
| authenticated visit to `/` raised a RouteNotFoundException.
|
| `resolveDefaultBusiness()` re-validates the membership, so this cannot be
| used to reach a business the user does not belong to.
|
*/
Route::get('/', function () {
    if (! auth()->check()) {
        /*
         * The public plans are read here rather than inside the view, so the
         * marketing page shows whatever the catalogue actually contains. Prices,
         * quotas and trial lengths are seeded data - hard-coding them into Blade
         * would guarantee they drift the first time a plan changes.
         */
        return view('marketing.landing', [
            'plans' => Plan::query()->publiclyVisible()->orderBy('sort_order')->get(),
        ]);
    }

    $user = auth()->user();

    $business = $user->resolveDefaultBusiness();

    if ($business === null) {
        return redirect()->route('onboarding.create');
    }

    // A tenant-only account has no back-office to enter, so send it to the
    // portal. Without this branch `home` would redirect to `app.dashboard`,
    // whose `business` gate redirects straight back here — an infinite loop.
    //
    // The business is passed explicitly rather than left to the ambient Spatie
    // team id, so a user who is a tenant of one landlord and an owner of
    // another is judged on the workspace they are actually being sent into.
    return $user->isBackOfficeUser($business)
        ? redirect()->route('app.dashboard')
        : redirect()->route('portal.home');
})->name('home');

/*
|--------------------------------------------------------------------------
| Onboarding — authenticated but with NO business yet
|--------------------------------------------------------------------------
|
| The one place a Business is created by a user action. Everything after this
| assumes a business exists.
|
*/
Route::middleware(['auth'])->prefix('onboarding')->name('onboarding.')->group(function () {
    Route::get('/', [OnboardingController::class, 'create'])->name('create');
    Route::post('/', [OnboardingController::class, 'store'])->name('store');
});

/*
|--------------------------------------------------------------------------
| Authenticated shell
|--------------------------------------------------------------------------
|
| `verified` is deliberately NOT applied globally. Breeze routes it
| individually and the decision of when to demand email verification belongs
| to the product, not the shell.
|
*/
Route::middleware(['auth'])->group(function () {

    /* ---------------------------------------------------------------- */
    /* Business switching — the multi-tenant entry point                  */
    /* ---------------------------------------------------------------- */
    Route::post('/business/switch', [SwitchBusinessController::class, 'store'])
        ->name('business.switch');

    /* ---------------------------------------------------------------- */
    /* Profile                                                            */
    /* ---------------------------------------------------------------- */
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    /* ---------------------------------------------------------------- */
    /* Back-office dashboard                                              */
    /*                                                                  */
    /* `business` is a custom gate: the user must be a member of the      */
    /* ACTIVE business AND hold a back-office role. A tenant-only         */
    /* account is bounced to the portal rather than shown a broken shell. */
    /* ---------------------------------------------------------------- */
    Route::prefix('app')->name('app.')->middleware(['business'])->group(function () {

        Route::get('/', [DashboardController::class, 'index'])->name('dashboard');

        /*
        |----------------------------------------------------------------------
        | Read/write split
        |----------------------------------------------------------------------
        |
        | `subscription.active` is attached to MUTATIONS, never to the group.
        | It inspects the HTTP method itself and passes every read, so putting
        | it on the group would be a no-op for GETs and only risk hiding the
        | intent. Attaching it per-mutation makes the rule reviewable at a
        | glance: every write below it is explicitly listed.
        |
        | Deliberately NOT gated:
        |   - the billing routes. They are the remediation path for a
        |     read-only account, so gating them would trap the user.
        |   - onboarding, which creates the business in the first place.
        |
        */
        $writable = ['subscription.active'];

        /* Business settings */
        Route::get('/settings/business', [BusinessController::class, 'edit'])
            ->name('settings.business.edit');
        Route::put('/settings/business', [BusinessController::class, 'update'])
            ->middleware(array_merge($writable, ['permission:'.PermissionName::SETTINGS_MANAGE->value]))
            ->name('settings.business.update');

        /* Staff */
        Route::get('/staff', [StaffController::class, 'index'])
            ->middleware('permission:'.PermissionName::STAFF_VIEW->value)
            ->name('staff.index');
        Route::get('/staff/create', [StaffController::class, 'create'])
            ->middleware('permission:'.PermissionName::STAFF_INVITE->value)
            ->name('staff.create');
        Route::post('/staff', [StaffController::class, 'store'])
            ->middleware(array_merge($writable, ['permission:'.PermissionName::STAFF_INVITE->value]))
            ->name('staff.store');
        Route::get('/staff/{user}/edit', [StaffController::class, 'edit'])
            ->middleware('permission:'.PermissionName::STAFF_UPDATE->value)
            ->name('staff.edit');
        Route::put('/staff/{user}', [StaffController::class, 'update'])
            ->middleware(array_merge($writable, ['permission:'.PermissionName::STAFF_UPDATE->value]))
            ->name('staff.update');
        Route::delete('/staff/{user}', [StaffController::class, 'destroy'])
            ->middleware(array_merge($writable, ['permission:'.PermissionName::STAFF_REMOVE->value]))
            ->name('staff.destroy');

        Route::post('/staff/{user}/assign-role', [StaffController::class, 'assignRole'])
            ->middleware('permission:'.PermissionName::STAFF_ASSIGN_ROLE->value)
            ->name('staff.assign_role');

        /* Subscription / billing */
        Route::get('/billing', [SubscriptionController::class, 'show'])
            ->name('subscription.show');
        Route::get('/billing/plans', [SubscriptionController::class, 'plans'])
            ->name('subscription.plans');
        Route::post('/billing/cancel', [SubscriptionController::class, 'cancel'])
            ->middleware('permission:'.PermissionName::SUBSCRIPTION_MANAGE->value)
            ->name('subscription.cancel');

        /* ---------------------------------------------------------------- */
        /* Properties                                                        */
        /*                                                                  */
        /* `permission:` is the coarse URL gate; the policies answer the      */
        /* precise question per record. Both are applied, and neither is a    */
        /* substitute for the `BelongsToBusiness` scope that hides another    */
        /* landlord's rows before either can run.                             */
        /*                                                                  */
        /* `{property}` is bound by name, so the model is resolved through    */
        /* the global scope: a property belonging to another business is not  */
        /* found at all and the response is a 404, not a 403.                  */
        /* ---------------------------------------------------------------- */
        Route::get('/properties', [PropertyController::class, 'index'])
            ->middleware('permission:'.PermissionName::PROPERTIES_VIEW->value)
            ->name('properties.index');

        /*
         * `/properties/create` is declared BEFORE `/properties/{property}` on
         * purpose. The reverse order makes `{property}` match the literal
         * string "create" and the binder 404s on every visit to the add form.
         * Static segments always have to precede the parameterised ones.
         */
        Route::get('/properties/create', [PropertyController::class, 'create'])
            ->middleware('permission:'.PermissionName::PROPERTIES_CREATE->value)
            ->name('properties.create');

        Route::post('/properties', [PropertyController::class, 'store'])
            ->middleware(array_merge($writable, ['permission:'.PermissionName::PROPERTIES_CREATE->value]))
            ->name('properties.store');

        Route::get('/properties/{property}', [PropertyController::class, 'show'])
            ->middleware('permission:'.PermissionName::PROPERTIES_VIEW->value)
            ->name('properties.show');

        Route::get('/properties/{property}/edit', [PropertyController::class, 'edit'])
            ->middleware('permission:'.PermissionName::PROPERTIES_UPDATE->value)
            ->name('properties.edit');

        Route::put('/properties/{property}', [PropertyController::class, 'update'])
            ->middleware(array_merge($writable, ['permission:'.PermissionName::PROPERTIES_UPDATE->value]))
            ->name('properties.update');

        Route::delete('/properties/{property}', [PropertyController::class, 'destroy'])
            ->middleware(array_merge($writable, ['permission:'.PermissionName::PROPERTIES_DELETE->value]))
            ->name('properties.destroy');

        /* ---------------------------------------------------------------- */
        /* Units, nested under their property                                 */
        /*                                                                  */
        /* No units index: a unit has no meaning without its address, so it  */
        /* is managed on the property page. Portfolio-wide unit reporting is  */
        /* the rent roll in Phase 7, not a second CRUD screen.                */
        /* ---------------------------------------------------------------- */
        /*
         * The GET form routes carry the SAME permission as the POST that
         * submits them, not a read permission. A user who can see units but not
         * add one has nothing to do with an empty "add unit" form, and the
         * policy check in the controller would 403 them anyway — matching the
         * permission here means the refusal happens at the middleware, before
         * a form is ever rendered.
         */
        Route::get('/properties/{property}/units/create', [UnitController::class, 'create'])
            ->middleware('permission:'.PermissionName::UNITS_CREATE->value)
            ->name('units.create');

        Route::post('/properties/{property}/units', [UnitController::class, 'store'])
            ->middleware(array_merge($writable, ['permission:'.PermissionName::UNITS_CREATE->value]))
            ->name('units.store');

        Route::get('/properties/{property}/units/{unit}/edit', [UnitController::class, 'edit'])
            ->middleware('permission:'.PermissionName::UNITS_UPDATE->value)
            ->name('units.edit');

        Route::put('/properties/{property}/units/{unit}', [UnitController::class, 'update'])
            ->middleware(array_merge($writable, ['permission:'.PermissionName::UNITS_UPDATE->value]))
            ->name('units.update');

        Route::delete('/properties/{property}/units/{unit}', [UnitController::class, 'destroy'])
            ->middleware(array_merge($writable, ['permission:'.PermissionName::UNITS_DELETE->value]))
            ->name('units.destroy');
    });

    /* ---------------------------------------------------------------- */
    /* Documents — uploaded files attached to any business‑scoped model   */
    /* ---------------------------------------------------------------- */
    Route::middleware('permission:'.PermissionName::DOCUMENTS_VIEW->value)
        ->name('documents.')->group(function () {
        // Property‑scoped documents
        Route::get('/properties/{property}/documents', [DocumentController::class, 'index'])
            ->name('property.index');
        Route::get('/properties/{property}/documents/create', [DocumentController::class, 'create'])
            ->name('property.create');
        Route::post('/properties/{property}/documents', [DocumentController::class, 'store'])
            ->name('property.store');
        Route::delete('/properties/{property}/documents/{document}', [DocumentController::class, 'destroy'])
            ->name('property.destroy');

        // Tenant‑scoped documents
        Route::get('/tenants/{tenant}/documents', [DocumentController::class, 'indexTenant'])
            ->name('tenant.index');
        Route::get('/tenants/{tenant}/documents/create', [DocumentController::class, 'createTenant'])
            ->name('tenant.create');
        Route::post('/tenants/{tenant}/documents', [DocumentController::class, 'storeTenant'])
            ->name('tenant.store');
        Route::delete('/tenants/{tenant}/documents/{document}', [DocumentController::class, 'destroyTenant'])
            ->name('tenant.destroy');

        // Lease‑scoped documents
        Route::get('/leases/{lease}/documents', [DocumentController::class, 'indexLease'])
            ->name('lease.index');
        Route::get('/leases/{lease}/documents/create', [DocumentController::class, 'createLease'])
            ->name('lease.create');
        Route::post('/leases/{lease}/documents', [DocumentController::class, 'storeLease'])
            ->name('lease.store');
        Route::delete('/leases/{lease}/documents/{document}', [DocumentController::class, 'destroyLease'])
            ->name('lease.destroy');

        // Payment‑scoped documents
        Route::get('/payments/{payment}/documents', [DocumentController::class, 'indexPayment'])
            ->name('payment.index');
        Route::get('/payments/{payment}/documents/create', [DocumentController::class, 'createPayment'])
            ->name('payment.create');
        Route::post('/payments/{payment}/documents', [DocumentController::class, 'storePayment'])
            ->name('payment.store');
        Route::delete('/payments/{payment}/documents/{document}', [DocumentController::class, 'destroyPayment'])
            ->name('payment.destroy');

        // Expense‑scoped documents
        Route::get('/expenses/{expense}/documents', [DocumentController::class, 'indexExpense'])
            ->name('expense.index');
        Route::get('/expenses/{expense}/documents/create', [DocumentController::class, 'createExpense'])
            ->name('expense.create');
        Route::post('/expenses/{expense}/documents', [DocumentController::class, 'storeExpense'])
            ->name('expense.store');
        Route::delete('/expenses/{expense}/documents/{document}', [DocumentController::class, 'destroyExpense'])
            ->name('expense.destroy');

        // Maintenance‑scoped documents
        Route::get('/maintenance/{maintenance}/documents', [DocumentController::class, 'indexMaintenance'])
            ->name('maintenance.index');
        Route::get('/maintenance/{maintenance}/documents/create', [DocumentController::class, 'createMaintenance'])
            ->name('maintenance.create');
        Route::post('/maintenance/{maintenance}/documents', [DocumentController::class, 'storeMaintenance'])
            ->name('maintenance.store');
        Route::delete('/maintenance/{maintenance}/documents/{document}', [DocumentController::class, 'destroyMaintenance'])
            ->name('maintenance.destroy');
    });

    /* ---------------------------------------------------------------- */
    /* Journal entries – simple general‑ledger                           */
    /* ---------------------------------------------------------------- */
    Route::middleware('permission:'.PermissionName::JOURNALS_VIEW->value)
        ->name('journals.')->group(function () {
        Route::get('/journals/create', [JournalController::class, 'create'])
            ->name('create');
        Route::post('/journals', [JournalController::class, 'store'])
            ->name('store');
        Route::get('/journals/{entry}', [JournalController::class, 'show'])
            ->name('show');
        Route::get('/journals/{entry}/edit', [JournalController::class, 'edit'])
            ->name('edit');
        Route::put('/journals/{entry}', [JournalController::class, 'update'])
            ->name('update');
        Route::delete('/journals/{entry}', [JournalController::class, 'destroy'])
            ->name('destroy');
    });

    /* ---------------------------------------------------------------- */
    /* Messages – staff‑to‑tenant communications                       */
    /* ---------------------------------------------------------------- */
    Route::middleware('permission:'.PermissionName::MESSAGES_VIEW->value)
        ->name('messages.')->group(function () {
        Route::get('/', [MessageController::class, 'index'])
            ->name('index');
        Route::get('/create', [MessageController::class, 'create'])
            ->name('create');
        Route::post('/', [MessageController::class, 'store'])
            ->name('store');
        Route::get('/{message}/read-toggle', [MessageController::class, 'readToggle'])
            ->name('read-toggle');
        Route::delete('/{message}', [MessageController::class, 'destroy'])
            ->name('destroy');
    });

    Route::middleware('permission:'.PermissionName::MESSAGES_THREAD->value)
        ->name('messages.thread.')->group(function () {
        Route::post('/{message}/reply', [MessageController::class, 'reply'])
            ->name('reply');
    });

    Route::middleware('permission:'.PermissionName::MESSAGES_ATTACH->value)
        ->name('messages.attach.')->group(function () {
        Route::post('/{message}/attach', [MessageController::class, 'attach'])
            ->name('attach');
        Route::delete('/{message}/attach/{attachment}', [MessageController::class, 'detach'])
            ->name('detach');
    });

    Route::middleware('permission:'.PermissionName::MESSAGES_NOTIFY->value)
        ->name('messages.notify.')->group(function () {
        Route::post('/preferences', [MessageController::class, 'updatePreferences'])
            ->name('preferences');
    });

    /* ---------------------------------------------------------------- */
    /* Analytics – dashboard metrics and reports                         */
    /* ---------------------------------------------------------------- */
    Route::middleware('permission:'.PermissionName::ANALYTICS_VIEW->value)
        ->name('analytics.')->group(function () {
        Route::get('/', [AnalyticsController::class, 'index'])
            ->name('index');
    });

    /* ---------------------------------------------------------------- */
    /* Lease renewal notices                                               */
    /* ---------------------------------------------------------------- */
    Route::middleware('permission:'.PermissionName::RENEWAL_NOTICES_VIEW->value)
        ->name('renewal_notices.')->group(function () {
        Route::get('/leases/{lease}/renewal-notices', [RenewalNoticeController::class, 'index'])
            ->name('index');
        Route::get('/leases/{lease}/renewal-notices/create', [RenewalNoticeController::class, 'create'])
            ->name('create');
        Route::post('/leases/{lease}/renewal-notices', [RenewalNoticeController::class, 'store'])
            ->name('store');
        Route::get('/renewal-notices/{notice}/read-toggle', [RenewalNoticeController::class, 'readToggle'])
            ->name('read-toggle');
        Route::delete('/renewal-notices/{notice}', [RenewalNoticeController::class, 'destroy'])
            ->name('destroy');
    });

    /* ---------------------------------------------------------------- */
    /* Tenant portal — Phase 14.                                          */
    /*                                                                  */
    /* The `tenant` gate and the group exist from day one. Until the     */
    /* portal itself is built there is exactly one thing a tenant-only  */
    /* account can do, and this page says so.                           */
    /*                                                                  */
    /* This route is also the loop-breaker: `EnsureBackOfficeAccess`     */
    /* sends a tenant-only account here, and `home` does too. Pointing   */
    /* either at `home` instead would bounce between `/` and            */
    /* `/app/dashboard` forever, because `home` sends a signed-in user  */
    /* with a business to the dashboard.                                 */
    /* ---------------------------------------------------------------- */
    Route::prefix('portal')->name('portal.')->middleware(['tenant'])->group(function () {
        Route::get('/', fn () => view('portal.pending'))->name('portal.home');
    });
});

/*
|--------------------------------------------------------------------------
| Auth
|--------------------------------------------------------------------------
*/
require __DIR__.'/auth.php';
