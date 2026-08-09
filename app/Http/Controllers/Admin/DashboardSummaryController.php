<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\Admin\DashboardSummaryResource;
use App\Models\Wedding;
use App\Services\AdminDashboardSummaryService;
use Illuminate\Http\JsonResponse;

class DashboardSummaryController extends Controller
{
    public function __invoke(
        AdminDashboardSummaryService $summaryService,
    ): DashboardSummaryResource|JsonResponse {
        $wedding = Wedding::currentSingleWedding();

        if ($wedding === null) {
            return response()->json([
                'message' => 'Wedding not found.',
            ], 404);
        }

        return new DashboardSummaryResource(
            $summaryService->summarize($wedding)
        );
    }
}
