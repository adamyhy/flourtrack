<?php

namespace App\Services;

use App\Models\Production;
use App\Models\Recipe;
use App\Models\StockMutation;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use Exception;

class MaterialReconciliationEngine
{
    public function startBatch(Recipe $recipe, int $plannedOutputQty, User $user): Production
    {
        return DB::transaction(function () use ($recipe, $plannedOutputQty, $user) {
            $scale = $plannedOutputQty / ($recipe->yield_qty > 0 ? $recipe->yield_qty : 1);
            $theoreticalYield = $plannedOutputQty;
            $batchCode = BatchCodeGenerator::generate($recipe);

            foreach ($recipe->recipeItems as $item) {
                $rawMaterial = $item->rawMaterial;
                $qtyNeeded = $item->qty_required * $scale;

                if ($rawMaterial->current_stock < $qtyNeeded) {
                    throw new Exception("Stok bahan {$rawMaterial->name} tidak mencukupi! Dibutuhkan: {$qtyNeeded} {$rawMaterial->unit}, Tersedia: {$rawMaterial->current_stock} {$rawMaterial->unit}.");
                }

                $rawMaterial->decrement("current_stock", $qtyNeeded);

                StockMutation::create([
                    "raw_material_id" => $rawMaterial->id,
                    "finished_recipe_id" => null,
                    "type" => "PRODUCTION_OUT",
                    "qty_in" => 0,
                    "qty_out" => $qtyNeeded,
                    "reference_id" => null,
                    "notes" => "Bahan terpakai untuk Batch {$batchCode}",
                    "created_by" => $user->id,
                    "created_at" => now(),
                ]);
            }

            return Production::create([
                "batch_code" => $batchCode,
                "recipe_id" => $recipe->id,
                "planned_output_qty" => $plannedOutputQty,
                "theoretical_yield_qty" => $theoreticalYield,
                "reported_output_qty" => 0,
                "reported_waste_qty" => 0,
                "status" => "IN_PROGRESS",
                "started_by" => $user->id,
            ]);
        });
    }

    public function completeBatch(
        Production $production,
        int $reportedOutput,
        int $reportedWaste,
        ?string $wasteReason,
        ?string $wastePhotoPath,
        User $user
    ): Production {
        return DB::transaction(function () use ($production, $reportedOutput, $reportedWaste, $wasteReason, $wastePhotoPath, $user) {
            $theoretical = $production->theoretical_yield_qty;
            $totalReported = $reportedOutput + $reportedWaste;

            $discrepancyRatio = ($theoretical > 0) ? ($theoretical - $totalReported) / $theoretical : 0;
            $isDisputed = ($discrepancyRatio > 0.03) || ($totalReported < $theoretical * 0.97);

            $status = $isDisputed ? "DISPUTED" : "COMPLETED";

            $producedAt = Carbon::now();
            $shelfLifeDays = $production->recipe->shelf_life_days ?? 3;
            $expiredAt = Carbon::now()->addDays($shelfLifeDays);

            $production->update([
                "reported_output_qty" => $reportedOutput,
                "reported_waste_qty" => $reportedWaste,
                "waste_reason" => $wasteReason,
                "waste_photo_path" => $wastePhotoPath,
                "status" => $status,
                "produced_at" => $producedAt,
                "expired_at" => $expiredAt,
                "completed_by" => $user->id,
            ]);

            if ($reportedOutput > 0) {
                StockMutation::create([
                    "raw_material_id" => null,
                    "finished_recipe_id" => $production->recipe_id,
                    "type" => "PRODUCTION_IN",
                    "qty_in" => $reportedOutput,
                    "qty_out" => 0,
                    "reference_id" => $production->id,
                    "notes" => "Hasil produksi Batch {$production->batch_code} ({$status})",
                    "created_by" => $user->id,
                    "created_at" => now(),
                ]);
            }

            if ($reportedWaste > 0) {
                StockMutation::create([
                    "raw_material_id" => null,
                    "finished_recipe_id" => $production->recipe_id,
                    "type" => "WASTE_LOSS",
                    "qty_in" => 0,
                    "qty_out" => $reportedWaste,
                    "reference_id" => $production->id,
                    "notes" => "Waste produksi Batch {$production->batch_code}: " . ($wasteReason ?? "Rusak/Gagal"),
                    "created_by" => $user->id,
                    "created_at" => now(),
                ]);
            }

            return $production;
        });
    }
}
