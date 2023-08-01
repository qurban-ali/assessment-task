<?php

namespace App\Http\Controllers;

use App\Models\Merchant;
use App\Services\MerchantService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class MerchantController extends Controller
{
    public function __construct(
        protected MerchantService $merchantService
    ) {
    }

    /**
     * Useful order statistics for the merchant API.
     * 
     * @param Request $request Will include a from and to date
     * @return JsonResponse Should be in the form {count: total number of orders in range, commission_owed: amount of unpaid commissions for orders with an affiliate, revenue: sum order subtotals}
     */
    public function orderStats(Request $request): JsonResponse
    {
        $fromDate = $request->from;
        $toDate = $request->to;

        $fromDate = Carbon::parse($fromDate);
        $toDate = Carbon::parse($toDate);

        $merchant = Merchant::with('orders')->first();
        
        $orderStats = $this->merchantService->getOrderStatistics($merchant, $fromDate, $toDate);

        return response()->json($orderStats);
    }
}
