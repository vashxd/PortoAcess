<?php

namespace App\Http\Controllers\Admin;

use App\Enums\AccessStatus;
use App\Enums\PaymentMethod;
use App\Http\Controllers\Controller;
use App\Models\AccessRecord;
use App\Models\Payment;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class DashboardController extends Controller
{
    public function index()
    {
        $today = now()->startOfDay();
        $weekStart = now()->startOfWeek();
        $monthStart = now()->startOfMonth();
        $prevMonthStart = now()->subMonthNoOverflow()->startOfMonth();
        $prevMonthEnd = now()->subMonthNoOverflow()->endOfMonth();

        $revenue = fn ($from, $to = null) => (float) Payment::where('paid_at', '>=', $from)
            ->when($to, fn ($q) => $q->where('paid_at', '<=', $to))
            ->where('method', '!=', PaymentMethod::Faturado->value)
            ->sum('amount');

        $billed = fn ($from, $to = null) => (float) Payment::where('paid_at', '>=', $from)
            ->when($to, fn ($q) => $q->where('paid_at', '<=', $to))
            ->where('method', PaymentMethod::Faturado->value)
            ->sum('amount');

        // Volume de veículos por dia (últimos 30 dias)
        $daily = AccessRecord::where('entered_at', '>=', now()->subDays(29)->startOfDay())
            ->where('status', '!=', AccessStatus::Cancelado)
            ->get(['entered_at', 'amount_due', 'discount_amount'])
            ->groupBy(fn ($r) => $r->entered_at->format('Y-m-d'))
            ->map(fn ($g, $day) => ['date' => $day, 'count' => $g->count()])
            ->values();

        // Distribuição por tipo de entrada e categoria (mês corrente)
        $byEntryType = AccessRecord::where('entered_at', '>=', $monthStart)
            ->where('status', '!=', AccessStatus::Cancelado)
            ->join('entry_types', 'entry_types.id', '=', 'access_records.entry_type_id')
            ->groupBy('entry_types.name')
            ->select('entry_types.name', DB::raw('count(*) as total'))
            ->pluck('total', 'name');

        $byCategory = AccessRecord::where('entered_at', '>=', $monthStart)
            ->where('status', '!=', AccessStatus::Cancelado)
            ->join('vehicle_categories', 'vehicle_categories.id', '=', 'access_records.vehicle_category_id')
            ->groupBy('vehicle_categories.name')
            ->select('vehicle_categories.name', DB::raw('count(*) as total'))
            ->pluck('total', 'name');

        // Receita por forma de pagamento (mês corrente)
        $byMethod = Payment::where('paid_at', '>=', $monthStart)
            ->groupBy('method')
            ->select('method', DB::raw('sum(amount) as total'))
            ->get()
            ->mapWithKeys(fn ($p) => [PaymentMethod::from($p->method instanceof PaymentMethod ? $p->method->value : $p->method)->label() => (float) $p->total]);

        $paidAccessesMonth = AccessRecord::where('entered_at', '>=', $monthStart)
            ->where('status', '!=', AccessStatus::Cancelado)
            ->where('amount_due', '>', 0)
            ->count();

        $revenueMonth = $revenue($monthStart) + $billed($monthStart);

        return Inertia::render('Admin/Dashboard', [
            'stats' => [
                'revenue_today' => $revenue($today) + $billed($today),
                'revenue_week' => $revenue($weekStart) + $billed($weekStart),
                'revenue_month' => $revenueMonth,
                'revenue_prev_month' => $revenue($prevMonthStart, $prevMonthEnd) + $billed($prevMonthStart, $prevMonthEnd),
                'billed_month' => $billed($monthStart),
                'vehicles_today' => AccessRecord::where('entered_at', '>=', $today)->where('status', '!=', AccessStatus::Cancelado)->count(),
                'vehicles_month' => AccessRecord::where('entered_at', '>=', $monthStart)->where('status', '!=', AccessStatus::Cancelado)->count(),
                'in_patio' => AccessRecord::where('status', AccessStatus::NoPatio)->count(),
                'avg_ticket' => $paidAccessesMonth > 0 ? round($revenueMonth / $paidAccessesMonth, 2) : 0,
            ],
            'daily' => $daily,
            'byEntryType' => $byEntryType,
            'byCategory' => $byCategory,
            'byMethod' => $byMethod,
        ]);
    }
}
