<?php

namespace App\Services;

use App\Models\Production;
use App\Models\Recipe;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class BatchCodeGenerator
{
    public static function generate(Recipe $recipe): string
    {
        return DB::transaction(function () use ($recipe) {
            $dateStr = Carbon::now()->format("ymd");
            $prefix = strtoupper($recipe->item_code) . "-" . $dateStr . "-";

            $latest = Production::where("batch_code", "LIKE", $prefix . "%")
                ->lockForUpdate()
                ->orderBy("id", "desc")
                ->first();

            $sequence = 1;
            if ($latest) {
                $parts = explode("-", $latest->batch_code);
                $lastSeq = (int) end($parts);
                $sequence = $lastSeq + 1;
            }

            return $prefix . str_pad((string) $sequence, 2, "0", STR_PAD_LEFT);
        });
    }

    public static function generateDONumber(): string
    {
        return DB::transaction(function () {
            $dateStr = Carbon::now()->format("ymd");
            $prefix = "DO-" . $dateStr . "-";

            $latest = \App\Models\Dispatch::where("do_number", "LIKE", $prefix . "%")
                ->lockForUpdate()
                ->orderBy("id", "desc")
                ->first();

            $sequence = 1;
            if ($latest) {
                $parts = explode("-", $latest->do_number);
                $lastSeq = (int) end($parts);
                $sequence = $lastSeq + 1;
            }

            return $prefix . str_pad((string) $sequence, 2, "0", STR_PAD_LEFT);
        });
    }
}
