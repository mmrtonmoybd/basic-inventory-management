<?php

namespace App\Http\Controllers;

use App\Services\Bangla;
use App\Services\ActivityLogger;
use App\Services\ReconciliationService;
use Inertia\Inertia;
use Inertia\Response;

class ReconciliationController extends Controller
{
    public function __invoke(ReconciliationService $reconciliation, ActivityLogger $activity): Response
    {
        $issues = $reconciliation->discrepancies();

        $activity->log('ledger.reconciled', $issues->isEmpty()
            ? 'হিসাব মিলানো সফল: সব ব্যালান্স খতিয়ানের সাথে মিলেছে'
            : "হিসাব মিলানোতে ".Bangla::number($issues->count())."টি অমিল পাওয়া গেছে");

        return Inertia::render('reconciliation/index', [
            'summary' => $reconciliation->summary(),
            'issues' => $issues->values(),
            'checkedAt' => now()->toIso8601String(),
        ]);
    }
}
