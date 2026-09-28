<?php

namespace App\Http\Controllers;

use App\Models\Production;
use App\Models\Recipe;
use App\Services\MaterialReconciliationEngine;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Exception;

class ProductionController extends Controller
{
    protected $reconciliationEngine;

    public function __construct(MaterialReconciliationEngine $reconciliationEngine)
    {
        $this->reconciliationEngine = $reconciliationEngine;
    }

    public function index(Request $request)
    {
        $query = Production::with(["recipe", "starter", "completer"]);

        if ($request->filled("status")) {
            $query->where("status", $request->status);
        }

        if ($request->filled("search")) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where("batch_code", "LIKE", "%{$search}%")
                  ->orWhereHas("recipe", function ($rq) use ($search) {
                      $rq->where("name", "LIKE", "%{$search}%");
                  });
            });
        }

        $productions = $query->orderBy("created_at", "desc")->paginate(15);
        $recipes = Recipe::orderBy("name")->get();

        return view("productions.index", compact("productions", "recipes"));
    }

    public function create()
    {
        $recipes = Recipe::with("recipeItems.rawMaterial")->orderBy("name")->get();
        return view("productions.create", compact("recipes"));
    }

    public function store(Request $request)
    {
        $request->validate([
            "recipe_id" => "required|exists:recipes,id",
            "planned_output_qty" => "required|integer|min:1",
        ]);

        $recipe = Recipe::findOrFail($request->recipe_id);

        try {
            $production = $this->reconciliationEngine->startBatch(
                $recipe,
                (int) $request->planned_output_qty,
                Auth::user()
            );

            return redirect()->route("productions.show", $production->id)
                ->with("success", "Batch Produksi {$production->batch_code} berhasil dimulai! Bahan baku otomatis dikurangi.");
        } catch (Exception $e) {
            return back()->withInput()->withErrors(["error" => $e->getMessage()]);
        }
    }

    public function show(Production $production)
    {
        $production->load(["recipe.recipeItems.rawMaterial", "starter", "completer"]);
        return view("productions.show", compact("production"));
    }

    public function complete(Request $request, Production $production)
    {
        $request->validate([
            "reported_output_qty" => "required|integer|min:0",
            "reported_waste_qty" => "nullable|integer|min:0",
            "waste_reason" => "nullable|string|max:255",
            "waste_photo" => "nullable|image|max:5120",
        ]);

        if ($production->status !== "IN_PROGRESS") {
            return back()->withErrors(["error" => "Batch ini sudah diselesaikan sebelumnya."]);
        }

        $wastePhotoPath = null;
        if ($request->hasFile("waste_photo")) {
            $wastePhotoPath = $request->file("waste_photo")->store("waste_photos", "public");
        }

        $reportedOutput = (int) $request->reported_output_qty;
        $reportedWaste = (int) ($request->reported_waste_qty ?? 0);

        $production = $this->reconciliationEngine->completeBatch(
            $production,
            $reportedOutput,
            $reportedWaste,
            $request->waste_reason,
            $wastePhotoPath,
            Auth::user()
        );

        $message = "Batch {$production->batch_code} selesai diselesaikan.";
        if ($production->isDisputed()) {
            return redirect()->route("productions.show", $production->id)
                ->with("warning", "{$message} Terdapat selisih hasil > 3%! Status ditandai sebagai DISPUTED untuk diaudit Owner.");
        }

        return redirect()->route("productions.show", $production->id)
            ->with("success", "{$message} Hasil produksi tercatat ke stok barang jadi.");
    }
}
