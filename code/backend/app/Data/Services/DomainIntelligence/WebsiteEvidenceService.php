<?php

namespace App\Data\Services\DomainIntelligence;

class WebsiteEvidenceService
{
    protected array $parkingProviders = [
        'hugedomains.com' => 'HugeDomains',
        'sedo.com' => 'Sedo',
        'godaddy.com' => 'GoDaddy',
        'afternic.com' => 'Afternic',
        'dan.com' => 'Dan',
        'parkingcrew.net' => 'ParkingCrew',
        'bodis.com' => 'Bodis',
    ];

    protected array $parkingPhrases = [
        'domain for sale',
        'this domain is for sale',
        'buy this domain',
        'domain parked',
        'parked domain',
        'domain parking',
        'make an offer for this domain',
        'this domain may be for sale',
    ];

    public function analyze(
        string $brand,
        string $primaryDomain,
        array $candidateData
    ): array {
        $candidateDomain =
            $candidateData['candidate_domain'] ?? '';

        $mentionsBrand = false;
        $brandDomainMatch = false;
        $linksToPrimary = false;
        $redirectsToPrimary = false;
        $primaryLinksToCandidate = false;

        if (!empty($candidateDomain) && !empty($brand)) {
            $candidateLabel = strtolower(
                preg_replace('/[^a-z0-9]/', '', $candidateDomain)
            );

            $brandLabel = strtolower(
                preg_replace('/[^a-z0-9]/', '', $brand)
            );

            if (
                $brandLabel !== '' &&
                str_contains($candidateLabel, $brandLabel)
            ) {
                $brandDomainMatch = true;
            }
        }

        if (
            !empty($candidateData['page_title']) &&
            stripos(
                $candidateData['page_title'],
                $brand
            ) !== false
        ) {
            $mentionsBrand = true;
        }

        if (
            !empty($candidateData['page_text']) &&
            stripos(
                $candidateData['page_text'],
                $brand
            ) !== false
        ) {
            $mentionsBrand = true;
        }

        if (!empty($candidateData['page_links'])) {
            foreach ($candidateData['page_links'] as $link) {
                $resolvedUrl = $this->resolveUrl(
                    $link,
                    $candidateDomain
                );

                if (
                    $resolvedUrl &&
                    $this->isHostMatching(
                        $resolvedUrl,
                        $primaryDomain
                    )
                ) {
                    $linksToPrimary = true;
                    break;
                }
            }
        }

        if (!empty($candidateData['redirects_to'])) {
            if (
                $this->isPrimaryRedirect(
                    $candidateData['redirects_to'],
                    $primaryDomain
                )
            ) {
                $redirectsToPrimary = true;
            }
        }

        if (
            !empty($candidateData['primary_page_links']) &&
            !empty($candidateDomain)
        ) {
            foreach (
                $candidateData['primary_page_links']
                as $link
            ) {
                $resolvedUrl = $this->resolveUrl(
                    $link,
                    $primaryDomain
                );

                if (
                    $resolvedUrl &&
                    $this->isHostMatching(
                        $resolvedUrl,
                        $candidateDomain
                    )
                ) {
                    $primaryLinksToCandidate = true;
                    break;
                }
            }
        }

        $parkingData = $this->detectParking(
            $candidateData
        );

        return [
            'mentions_brand' => $mentionsBrand,
            'brand_domain_match' => $brandDomainMatch,
            'links_to_primary' => $linksToPrimary,
            'redirects_to_primary' => $redirectsToPrimary,
            'primary_links_to_candidate' =>
                $primaryLinksToCandidate,
            'is_parked' => $parkingData['is_parked'],
            'parking_provider' =>
                $parkingData['parking_provider'],
        ];
    }

    protected function detectParking(
        array $candidateData
    ): array {
        $redirectUrl =
            $candidateData['redirects_to'] ?? null;

        if (!empty($redirectUrl)) {
            $provider =
                $this->detectParkingProvider(
                    $redirectUrl
                );

            if ($provider !== null) {
                return [
                    'is_parked' => true,
                    'parking_provider' => $provider,
                ];
            }
        }

        $content = strtolower(
            trim(
                ($candidateData['page_title'] ?? '') .
                ' ' .
                ($candidateData['page_text'] ?? '')
            )
        );

        if (!empty($content)) {
            foreach ($this->parkingPhrases as $phrase) {
                if (str_contains($content, $phrase)) {
                    return [
                        'is_parked' => true,
                        'parking_provider' => null,
                    ];
                }
            }
        }

        return [
            'is_parked' => false,
            'parking_provider' => null,
        ];
    }

    protected function detectParkingProvider(
        string $url
    ): ?string {
        if (!preg_match('#^https?://#i', $url)) {
            $url = 'http://' . $url;
        }

        $host = parse_url(
            strtolower($url),
            PHP_URL_HOST
        );

        if (!$host) {
            return null;
        }

        foreach ($this->parkingProviders as $domain => $provider) {
            if (
                $host === $domain ||
                str_ends_with(
                    $host,
                    '.' . $domain
                )
            ) {
                return $provider;
            }
        }

        return null;
    }

    protected function isPrimaryRedirect(
        string $url,
        string $primaryDomain
    ): bool {
        if (!preg_match('#^https?://#i', $url)) {
            $url = 'http://' . $url;
        }

        $host = parse_url(
            strtolower($url),
            PHP_URL_HOST
        );

        if (!$host) {
            return false;
        }

        $primaryDomain = strtolower(
            trim($primaryDomain)
        );

        return $host === $primaryDomain ||
            $host === 'www.' . $primaryDomain;
    }

    protected function resolveUrl(
        string $url,
        string $baseDomain
    ): ?string {
        $url = trim($url);

        if (empty($url)) {
            return null;
        }

        if (
            str_starts_with($url, '#') ||
            str_starts_with(
                strtolower($url),
                'javascript:'
            ) ||
            str_starts_with(
                strtolower($url),
                'mailto:'
            ) ||
            str_starts_with(
                strtolower($url),
                'tel:'
            )
        ) {
            return null;
        }

        if (
            preg_match(
                '#^https?://#i',
                $url
            )
        ) {
            return $url;
        }

        if (empty($baseDomain)) {
            return null;
        }

        $baseUrl = 'https://' . $baseDomain;

        if (str_starts_with($url, '//')) {
            return 'https:' . $url;
        }

        if (str_starts_with($url, '/')) {
            return $baseUrl . $url;
        }

        return $baseUrl . '/' . ltrim($url, '/');
    }

    protected function isHostMatching(
        string $url,
        string $targetDomain
    ): bool {
        if (!preg_match('#^https?://#i', $url)) {
            $url = 'http://' . $url;
        }

        $host = parse_url(
            strtolower($url),
            PHP_URL_HOST
        );

        if (!$host) {
            return false;
        }

        $targetDomain = strtolower(
            trim($targetDomain)
        );

        return $host === $targetDomain ||
            str_ends_with(
                $host,
                '.' . $targetDomain
            );
    }
}