<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Wedding;
use App\Services\AdminMealChoiceReportService;
use Illuminate\Http\JsonResponse;

class MealChoiceReportController extends Controller
{
    public function __invoke(AdminMealChoiceReportService $report): JsonResponse
    {
        $wedding = Wedding::currentSingleWedding();

        if ($wedding === null) {
            return response()->json(['message' => 'Wedding not found.'], 404);
        }

        return response()->json(['data' => $report->report($wedding)]);
    }
}
