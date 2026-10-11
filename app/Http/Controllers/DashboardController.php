<?php

namespace App\Http\Controllers;

use App\Services\DashboardService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __invoke(Request $request, DashboardService $dashboard): Response
    {
        $canSeeAnalytics = $request->user()->can('reports.view');

        if (! $request->user()->can('products.view')) {
            return Inertia::render('dashboard', ['restricted' => true, 'metrics' => null, 'lowStock' => [], 'recentMovements' => [], 'charts' => null]);
        }

        return Inertia::render('dashboard', [
            'restricted' => false,
            'metrics' => $dashboard->metrics(),
            'lowStock' => $dashboard->lowStockList(),
            'recentMovements' => $request->user()->can('movements.view') ? $dashboard->recentMovements() : [],
            'charts' => $canSeeAnalytics ? Inertia::defer(fn () => $dashboard->charts()) : null,
        ]);
    }
}
