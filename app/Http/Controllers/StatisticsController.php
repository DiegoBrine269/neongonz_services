<?php

namespace App\Http\Controllers;

use App\Models\Centre;
use App\Models\Invoice;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class StatisticsController extends Controller
{
    public function summary(){
        $invoices = Invoice::select('status', DB::raw('COUNT(*) as count'))
                        ->groupBy('status')
                        ->get();

        $totalInvoices = Invoice::count();

        return response()->json([
            'total_invoices' => $totalInvoices,
            'invoices_by_status' => $invoices
        ]);
    }

    public function paymentPending() {
        $distribution = Centre::withSum(['invoices' => function ($q) {
                $q->whereNotIn('status', ['complemento', 'finalizada'])
                ->where('is_budget', false);
            }], 'total')
            ->withCount(['invoices' => function ($q) {
                $q->whereNotIn('status', ['complemento', 'finalizada'])
                ->where('is_budget', false);
            }])
            ->get(['id', 'name'])
            ->filter(fn($centre) => $centre->invoices_sum_total > 0)
            ->values()
            ->map(function ($centre) {
                $centre->invoices_sum_total = (float) $centre->invoices_sum_total;
                return $centre;
            });

        $totalPending = $distribution->sum('invoices_sum_total');
        
        return response()->json([
            'total_pending' => $totalPending,
            'distribution' => $distribution
        ]);
    }

    public function incomes(Request $request) {
        $request->validate([
            'period' => 'nullable|integer|min:1',
        ]);

        $from = match($request->period) {
            '7' => now()->subWeek(),
            '30' => now()->subMonth(),
            '90' => now()->subMonths(3),
            '180' => now()->subMonths(6),
            '365' => now()->subYear(),
            default => now()->subMonth(),
        };

    $incomeTotal = Invoice::where('created_at', '>=', $from)
        ->whereIn('status', ['finalizada', 'complemento']) 
        ->where('is_budget', false)
        ->sum('total');

        return response()->json(['total' => $incomeTotal]);
    }
}
