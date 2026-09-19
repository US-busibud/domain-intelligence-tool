<?php

namespace App\Data\Services\DomainIntelligence;

use App\Models\Scan;
use Illuminate\Support\Facades\Log;

class DomainDiscoveryService
{
    public function __construct(
        protected DomainNormalizerService $normalizer,
        protected BrandExtractorService $extractor,
        protected CandidateGeneratorService $generator,
        protected CertificateTransparencyService $crtService,
        protected DnsAnalysisService $dnsService,
        protected HttpLivenessService $httpService,
        protected EmailInfrastructureService $emailService,
        protected WebsiteEvidenceService $evidenceService,
        protected OwnershipScoringService $ownershipScoringService
    ) {}

    public function discover(string $inputDomain): Scan
    {
        $totalStart = microtime(true);
        $timings = [];

        $start = microtime(true);

        $normalizedDomain = $this->normalizer->normalize($inputDomain);

        $timings['normalize'] = round(
            microtime(true) - $start,
            3
        );

        $start = microtime(true);

        $brand = $this->extractor->extract($normalizedDomain);

        $timings['brand_extraction'] = round(
            microtime(true) - $start,
            3
        );

        $start = microtime(true);

        $realDomains = $this->crtService->fetchDomains(
            $normalizedDomain
        );

        $timings['certificate_transparency'] = round(
            microtime(true) - $start,
            3
        );

        $start = microtime(true);

        $generatedCandidates = $this->generator->generate($brand);

        $timings['candidate_generation'] = round(
            microtime(true) - $start,
            3
        );

        $start = microtime(true);

        $allCandidates = array_values(array_unique(array_merge(
            $realDomains,
            $generatedCandidates
        )));

        $allCandidates = array_values(array_filter(
            $allCandidates,
            fn ($domain) => $domain !== $normalizedDomain
        ));

        $timings['candidate_merge'] = round(
            microtime(true) - $start,
            3
        );

        $start = microtime(true);

        $dnsResults = $this->dnsService->analyzeMany(
            $allCandidates
        );

        $timings['dns_analysis'] = round(
            microtime(true) - $start,
            3
        );

        $verifiedDomains = [];

        foreach ($dnsResults as $domain => $dnsData) {
            if ($dnsData['has_dns']) {
                $verifiedDomains[] = $domain;
            }
        }

        $timings['candidate_count'] = count($allCandidates);
        $timings['verified_domain_count'] = count($verifiedDomains);

        $start = microtime(true);

        $scan = Scan::create([
            'original_input' => $inputDomain,
            'normalized_domain' => $normalizedDomain,
            'brand_name' => $brand,
        ]);

        $timings['scan_create'] = round(
            microtime(true) - $start,
            3
        );

        $candidateRecords = [];
        $testResults = [];

        $start = microtime(true);

        $livenessResults = $this->httpService->checkMany(
            $verifiedDomains
        );

        $timings['http_analysis'] = round(
            microtime(true) - $start,
            3
        );

        $primaryLivenessData = $livenessResults[$normalizedDomain] ?? [
            'is_alive' => false,
            'status_code' => null,
            'redirect_url' => null,
            'page_title' => null,
            'page_text' => null,
            'page_links' => [],
            'url' => null,
        ];

        $primaryPageLinks =
            $primaryLivenessData['page_links'] ?? [];

        $start = microtime(true);

        $emailResults = $this->emailService->analyzeMany(
            $verifiedDomains
        );

        $timings['email_analysis'] = round(
            microtime(true) - $start,
            3
        );

        $start = microtime(true);

        foreach ($verifiedDomains as $domain) {
            $livenessData = $livenessResults[$domain] ?? [
                'is_alive' => false,
                'status_code' => null,
                'redirect_url' => null,
                'page_title' => null,
                'page_text' => null,
                'page_links' => [],
                'url' => null,
            ];

            $redirectData = [
                'has_redirect' =>
                    $livenessData['has_redirect'] ?? false,
                'redirect_url' =>
                    $livenessData['redirect_url'] ?? null,
            ];

            $emailData = $emailResults[$domain] ?? [
                'has_mx' => false,
                'has_spf' => false,
                'has_dmarc' => false,
                'has_dkim' => false,
                'dkim_selectors' => [],
            ];

            $candidateDataForEvidence = [
                'candidate_domain' => $domain,

                'page_title' =>
                    $livenessData['page_title'] ?? null,

                'page_text' =>
                    $livenessData['page_text'] ?? null,

                'page_links' =>
                    $livenessData['page_links'] ?? [],

                'redirects_to' =>
                    $redirectData['redirect_url'],

                'primary_page_links' =>
                    $primaryPageLinks,
            ];

            $evidenceData = $this->evidenceService->analyze(
                $brand,
                $normalizedDomain,
                $candidateDataForEvidence
            );

            $hasEmailInfrastructure =
                $emailData['has_mx'] ||
                $emailData['has_spf'] ||
                $emailData['has_dmarc'] ||
                $emailData['has_dkim'];

            $usesHttps =
                !empty($livenessData['url']) &&
                str_starts_with(
                    strtolower($livenessData['url']),
                    'https://'
                );

            $ownershipEvidence = [
                'is_alive' =>
                    $livenessData['is_alive'] ?? false,

                'uses_https' =>
                    $usesHttps,

                'mentions_brand' =>
                    $evidenceData['mentions_brand'],

                'brand_domain_match' =>
                    $evidenceData['brand_domain_match'],

                'links_to_primary' =>
                    $evidenceData['links_to_primary'],

                'redirects_to_primary' =>
                    $evidenceData['redirects_to_primary'],

                'primary_links_to_candidate' =>
                    $evidenceData['primary_links_to_candidate'],

                'has_email_infrastructure' =>
                    $hasEmailInfrastructure,

                'is_parked' =>
                    $evidenceData['is_parked'],
            ];

            $ownershipResult =
                $this->ownershipScoringService->calculate(
                    $domain,
                    $normalizedDomain,
                    $ownershipEvidence
                );

            $candidateRecords[] = [
                'variation_domain' => $domain,
                'ownership_score' =>
                    $ownershipResult['score'],
                'ownership_classification' =>
                    $ownershipResult['classification'],
                'ownership_reasons' =>
                    $ownershipResult['reasons'],
            ];

            $testResults[] = [
                'is_alive' =>
                    $livenessData['is_alive'],

                'http_status' =>
                    $livenessData['status_code'],

                'redirects_to' =>
                    $redirectData['redirect_url'],

                'page_title' =>
                    $livenessData['page_title'] ?? null,

                'page_text' =>
                    $livenessData['page_text'] ?? null,

                'page_links' =>
                    $livenessData['page_links'] ?? [],

                'has_mx' =>
                    $emailData['has_mx'],

                'has_spf' =>
                    $emailData['has_spf'],

                'has_dmarc' =>
                    $emailData['has_dmarc'],

                'has_dkim' =>
                    $emailData['has_dkim'],

                'dkim_selectors' =>
                    $emailData['dkim_selectors'],

                'mentions_brand' =>
                    $evidenceData['mentions_brand'],

                'links_to_primary' =>
                    $evidenceData['links_to_primary'],

                'redirects_to_primary' =>
                    $evidenceData['redirects_to_primary'],

                'primary_links_to_candidate' =>
                    $evidenceData['primary_links_to_candidate'],

                'is_parked' =>
                    $evidenceData['is_parked'],

                'parking_provider' =>
                    $evidenceData['parking_provider'],
            ];
        }

        $timings['evidence_processing'] = round(
            microtime(true) - $start,
            3
        );

        $start = microtime(true);

        if (!empty($candidateRecords)) {
            $scan->candidates()->createMany(
                $candidateRecords
            );
        }

        $timings['candidate_insert'] = round(
            microtime(true) - $start,
            3
        );

        $start = microtime(true);

        $scan->load('candidates');

        $timings['candidate_reload'] = round(
            microtime(true) - $start,
            3
        );

        $start = microtime(true);

        foreach ($scan->candidates as $index => $candidate) {
            $candidate->is_alive =
                $testResults[$index]['is_alive'];

            $candidate->http_status =
                $testResults[$index]['http_status'];

            $candidate->redirects_to =
                $testResults[$index]['redirects_to'];

            $candidate->page_title =
                $testResults[$index]['page_title'];

            $candidate->page_text =
                $testResults[$index]['page_text'];

            $candidate->page_links =
                $testResults[$index]['page_links'];

            $candidate->has_mx =
                $testResults[$index]['has_mx'];

            $candidate->has_spf =
                $testResults[$index]['has_spf'];

            $candidate->has_dmarc =
                $testResults[$index]['has_dmarc'];

            $candidate->has_dkim =
                $testResults[$index]['has_dkim'];

            $candidate->dkim_selectors =
                $testResults[$index]['dkim_selectors'];

            $candidate->mentions_brand =
                $testResults[$index]['mentions_brand'];

            $candidate->links_to_primary =
                $testResults[$index]['links_to_primary'];

            $candidate->redirects_to_primary =
                $testResults[$index]['redirects_to_primary'];

            $candidate->primary_links_to_candidate =
                $testResults[$index]['primary_links_to_candidate'];

            $candidate->is_parked =
                $testResults[$index]['is_parked'];

            $candidate->parking_provider =
                $testResults[$index]['parking_provider'];
        }

        $timings['result_attachment'] = round(
            microtime(true) - $start,
            3
        );

        $timings['total'] = round(
            microtime(true) - $totalStart,
            3
        );

        Log::info(
            'Domain discovery performance',
            $timings
        );

        return $scan;
    }
}