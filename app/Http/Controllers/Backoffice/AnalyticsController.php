<?php

namespace App\Http\Controllers\Backoffice;

use App\Http\Controllers\Controller;
use App\Models\Property;
use App\Models\Unit;
use App\Models\Tenant;
use App\Models\Lease;
use App\Models\Payment;
use App\Models\Expense;
use App\Models\MaintenanceRequest;
use App\Models\RentRoll;
use App\Models\Document;
use App\Models\Message;
use App\Support\Tenancy\BusinessContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Analytics & dashboard metrics endpoint.
 *
 * Returns JSON data for the dashboard tiles and charts.  All queries are
 * automatically filtered by the active business via the `BelongsToBusiness`
 * global scope, so no explicit `business_id` WHERE clauses are needed in the
 * controller – the middleware (`ResolveActiveBusiness` → `SubstituteBindings`)
 * already guarantees we only read the signed‑in business's rows.
 *
 * The endpoint is called by the Blade dashboard view via an AJAX request
 * and returns a single JSON object with all tile values.
 */
class AnalyticsController extends Controller
{
    public function index(): JsonResponse
    {
        $business = app(BusinessContext::class)->business();

        // ------------------------------------------------------------------
        // Property & unit counts
        // ------------------------------------------------------------------
        $totalProperties = Property::count();
        $totalUnits      = Unit::count();
        $occupiedUnits   = Unit::where('status', 'occupied')->count();
        $vacantUnits     = Unit::where('status', 'vacant')->count();

        // ------------------------------------------------------------------
        // Lease & rent roll metrics
        // ------------------------------------------------------------------
        $activeLeases    = Lease::active()->count();
        $totalMonthlyRent = RentRoll::active()
            ->sum('monthly_rent');

        // ------------------------------------------------------------------
        // Tenant counts
        // ------------------------------------------------------------------
        $totalTenants    = Tenant::count();

        // ------------------------------------------------------------------
        // Payment / revenue metrics (last 12 months simplified)
        // ------------------------------------------------------------------
        $paymentsThisYear = Payment::whereYear('created_at', now->year())
            ->sum('amount');

        // ------------------------------------------------------------------
        // Expense metrics
        // ------------------------------------------------------------------
        $totalExpenses   = Expense::sum('amount');

        // ------------------------------------------------------------------
        // Maintenance backlog
        // ------------------------------------------------------------------
        $openMaintenance = MaintenanceRequest::where('status', 'open')->count();
        $overdueMaintenance = MaintenanceRequest::where('status', 'open')
            ->where('due_date', '<', now())
            ->count();

        // ------------------------------------------------------------------
        // Document count (uploaded files)
        // ------------------------------------------------------------------
        $totalDocuments = Document::count();

        // ------------------------------------------------------------------
        // Message count (unread)
        // ------------------------------------------------------------------
        $unreadMessages = Message::unread()->count();

        $data = [
            'properties' => [
                'total'        => $totalProperties,
                'occupied_units' => $occupiedUnits,
                'vacant_units' => $vacantUnits,
                'total_units'     => $totalUnits,
            ],
            'leases' => [
                'active'          => $activeLeases,
                'total_monthly_rent' => number_format($totalMonthlyRent ?? 0, 2),
            ],
            'tenants' => [
                'total' => $totalTenants,
            ],
            'revenue' => [
                'payments_this_year' => number_format($paymentsThisYear ?? 0, 2),
                'total_expenses'     => number_format($totalExpenses ?? 0, 2),
                'net_cash_flow'      => number_format(
                    ($paymentsThisYear ?? 0) - ($totalExpenses ?? 0),
                    2
                ),
            ],
            'maintenance' => [
                'open'          => $openMaintenance,
                'overdue'       => $overdueMaintenance,
            ],
            'documents' => [
                'total' => $totalDocuments,
            ],
            'messages' => [
                'unread' => $unreadMessages,
            ],
        ];

        return response()->json($data);
    }
}