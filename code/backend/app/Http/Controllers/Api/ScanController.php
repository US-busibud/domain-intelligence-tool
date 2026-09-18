<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Data\Services\DomainIntelligence\DomainDiscoveryService;
use Exception;

class ScanController extends Controller
{
    public function __construct(
        protected DomainDiscoveryService $discoveryService
    ) {}

    public function processScan(Request $request)
    {
        $request->validate([
            'domain' => 'required|string'
        ]);

        try {
            $scan = $this->discoveryService->discover($request->input('domain'));

            return response()->json([
                'success' => true,
                'data' => $scan
            ]);

        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 422);
        }
    }
}