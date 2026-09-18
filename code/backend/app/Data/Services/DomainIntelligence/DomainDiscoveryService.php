<?php

namespace App\Data\Services\DomainIntelligence;

use App\Models\Scan;

/**
 * Service responsible for orchestrating the entire domain discovery process.
 * It coordinates normalization, generation, DNS verification, and HTTP liveness checks.
 */
class DomainDiscoveryService
{
    /**
     * Injecting all required sub-services through the constructor.
     */
    public function __construct(
        protected DomainNormalizerService $normalizer,
        protected BrandExtractorService $extractor,
        protected CandidateGeneratorService $generator,
        protected CertificateTransparencyService $crtService,
        protected DnsAnalysisService $dnsService,
        protected HttpLivenessService $httpService,
        // protected RedirectAnalysisService $redirectService,
        protected EmailInfrastructureService $emailService,
        protected WebsiteEvidenceService $evidenceService
    ) {}

    /**
     * Execute the main discovery pipeline for a given input domain.
     *
     * @param string $inputDomain The raw domain input from the user (e.g., 'https://acme.com')
     * @return Scan The complete scan record with dynamically attached candidate test results
     */
    public function discover(string $inputDomain): Scan
    {
        // Step 1: Clean the input (e.g., remove http://, www., and trailing slashes)
        $normalizedDomain = $this->normalizer->normalize($inputDomain);
        
        // Step 2: Extract the core brand name for generating variations
        $brand = $this->extractor->extract($normalizedDomain);
        
        // Step 3: Fetch known subdomains/domains from Certificate Transparency (CT) logs
        $realDomains = $this->crtService->fetchDomains($normalizedDomain);
        
        // Step 4: Generate hypothetical domain variations based on the brand name
        $generatedCandidates = $this->generator->generate($brand);
        
        // Step 5: Merge both lists and remove any duplicates to optimize scanning
        $allCandidates = array_unique(array_merge($realDomains, $generatedCandidates));
        
        // Step 6: Filter out dead domains early using lightweight DNS resolution
        // This prevents wasting time sending HTTP requests to non-existent servers
        $verifiedDomains = $this->dnsService->filterValidDomains($allCandidates);

        // Step 7: Ensure the original requested domain is included if it resolves correctly
        if (!in_array($normalizedDomain, $verifiedDomains) && $this->dnsService->verifyDomain($normalizedDomain)) {
            $verifiedDomains[] = $normalizedDomain;
        }

        // Step 8: Initialize the main Scan record in the database
        $scan = Scan::create([
            'original_input' => $inputDomain,
            'normalized_domain' => $normalizedDomain,
            'brand_name' => $brand,
        ]);

        $candidateRecords = [];
        $testResults = [];

        // Step 9: Perform deep analysis (HTTP/HTTPS & Redirects) on each verified domain
        foreach ($verifiedDomains as $domain) {
            
            // Perform HTTP Liveness check (fetches status code, page title, text, and links)
            $livenessData = $this->httpService->check($domain);
            
            // Initialize default redirect data assuming no redirect exists
            $redirectData = [
                'has_redirect' => $livenessData['has_redirect'] ?? false,
                'redirect_url' => $livenessData['redirect_url'] ?? null,
            ];

            // If the domain's web server is reachable, check if it redirects elsewhere
            // if ($livenessData['is_alive']) {
            //     $redirectData = $this->redirectService->analyze($livenessData['url']);
            // }

            // Run the Email Infrastructure Check
            $emailData = $this->emailService->analyze($domain);

            $candidateDataForEvidence = [
                'page_title'   => $livenessData['page_title'] ?? null,
                'page_text'    => $livenessData['page_text'] ?? null,
                'page_links'   => $livenessData['page_links'] ?? [],
                'redirects_to' => $redirectData['redirect_url'],
            ];
            $evidenceData = $this->evidenceService->analyze($brand, $normalizedDomain, $candidateDataForEvidence);

            // Prepare the basic candidate database record (excluding heavy HTTP data for now)
            $candidateRecords[] = [
                'variation_domain' => $domain,
                'security_score' => abs(crc32($domain) % 70) + 30, // Temporary placeholder scoring logic
            ];

            // Temporarily store the HTTP extraction results in memory 
            // to attach them to the JSON response later
            $testResults[] = [
                'is_alive'     => $livenessData['is_alive'],
                'http_status'  => $livenessData['status_code'],
                'redirects_to' => $redirectData['redirect_url'],
                'page_title'   => $livenessData['page_title'] ?? null,
                'page_text'    => $livenessData['page_text'] ?? null,
                'page_links'   => $livenessData['page_links'] ?? [],
                'has_mx'       => $emailData['has_mx'],
                'has_spf'      => $emailData['has_spf'],
                'has_dmarc'    => $emailData['has_dmarc'],
                'mentions_brand'       => $evidenceData['mentions_brand'],
                'links_to_primary'     => $evidenceData['links_to_primary'],
                'redirects_to_primary' => $evidenceData['redirects_to_primary'],
            ];
        }

        // Step 10: Bulk insert the candidates into the database for performance
        if (!empty($candidateRecords)) {
            $scan->candidates()->createMany($candidateRecords);
        }

        // Step 11: Reload the newly created candidate records from the database
        $scan->load('candidates');

        // Step 12: Attach the in-memory test results to the Eloquent models 
        // This ensures the data is included in the final API JSON response 
        // without permanently saving it to the database yet.
        foreach ($scan->candidates as $index => $candidate) {
            $candidate->is_alive     = $testResults[$index]['is_alive'];
            $candidate->http_status  = $testResults[$index]['http_status'];
            $candidate->redirects_to = $testResults[$index]['redirects_to'];
            $candidate->page_title   = $testResults[$index]['page_title'];
            $candidate->page_text    = $testResults[$index]['page_text'];
            $candidate->page_links   = $testResults[$index]['page_links'];
            $candidate->has_mx       = $testResults[$index]['has_mx'];
            $candidate->has_spf      = $testResults[$index]['has_spf'];
            $candidate->has_dmarc    = $testResults[$index]['has_dmarc'];
            $candidate->mentions_brand       = $testResults[$index]['mentions_brand'];
            $candidate->links_to_primary     = $testResults[$index]['links_to_primary'];
            $candidate->redirects_to_primary = $testResults[$index]['redirects_to_primary'];
        }

        return $scan;
    }
}