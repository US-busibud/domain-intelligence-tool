<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Scan;
use App\Data\Services\DomainIntelligence\DomainNormalizerService;
use App\Data\Services\DomainIntelligence\BrandExtractorService;
use App\Data\Services\DomainIntelligence\CandidateGeneratorService;
use Exception;

class ScanController extends Controller
{
    public function __construct(
        protected DomainNormalizerService $normalizer,
        protected BrandExtractorService $extractor,
        protected CandidateGeneratorService $generator
    ) {}

    public function processScan(Request $request)
    {
        // 1. Validate incoming request
        $request->validate([
            'domain' => 'required|string'
        ]);

        $inputDomain = $request->input('domain');

        try {
            // 2. Execute Core Logic
            $normalizedDomain = $this->normalizer->normalize($inputDomain);
            $brand = $this->extractor->extract($normalizedDomain);
            $candidates = $this->generator->generate($brand);

            // 3. Save Master Scan Record
            $scan = Scan::create([
                'original_input' => $inputDomain,
                'normalized_domain' => $normalizedDomain,
                'brand_name' => $brand,
            ]);

            // 4. Save Candidate Variations
            $candidateRecords = array_map(function($domain) {
                return ['variation_domain' => $domain, 'security_score' => null];
            }, $candidates);
            
            $scan->candidates()->createMany($candidateRecords);

            // 5. Return JSON API Response
            return response()->json([
                'success' => true,
                'data' => $scan->load('candidates')
            ]);

        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 422);
        }
    }
}