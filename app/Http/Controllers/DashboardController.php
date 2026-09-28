<?php

namespace App\Http\Controllers;

use App\Models\Production;
use App\Models\RawMaterial;
use App\Services\FinancialReportService;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function index()
    {
        $metrics = FinancialReportService::getMetrics();

        $disputedBatches = Production::with(["recipe", "starter", "completer"])
            ->where("status", "DISPUTED")
            ->orderBy("updated_at", "desc")
            ->get();

        $recentBatches = Production::with(["recipe", "starter"])
            ->orderBy("created_at", "desc")
            ->take(8)
            ->get();

        return view("dashboard.index", compact("metrics", "disputedBatches", "recentBatches"));
    }
}
