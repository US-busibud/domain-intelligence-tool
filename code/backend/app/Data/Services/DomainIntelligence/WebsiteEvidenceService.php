<?php

namespace App\Data\Services\DomainIntelligence;

class WebsiteEvidenceService
{
    /**
     * Analyze the extracted website data to find proof of ownership or affiliation.
     */
    public function analyze(string $brand, string $primaryDomain, array $candidateData): array
    {
        $mentionsBrand = false;
        $linksToPrimary = false;
        $redirectsToPrimary = false;

        // 1. Check for Brand Mentions in Title or Text
        if (!empty($candidateData['page_title']) && stripos($candidateData['page_title'], $brand) !== false) {
            $mentionsBrand = true;
        }
        if (!empty($candidateData['page_text']) && stripos($candidateData['page_text'], $brand) !== false) {
            $mentionsBrand = true;
        }

        // 2. Check if any extracted link points exactly to the primary domain
        if (!empty($candidateData['page_links'])) {
            foreach ($candidateData['page_links'] as $link) {
                if ($this->isHostMatching($link, $primaryDomain)) {
                    $linksToPrimary = true;
                    break;
                }
            }
        }

        // 3. Check if it redirects exactly to the primary domain
        if (!empty($candidateData['redirects_to'])) {
            if ($this->isHostMatching($candidateData['redirects_to'], $primaryDomain)) {
                $redirectsToPrimary = true;
            }
        }

        return [
            'mentions_brand'       => $mentionsBrand,
            'links_to_primary'     => $linksToPrimary,
            'redirects_to_primary' => $redirectsToPrimary,
        ];
    }

    /**
     * Helper to safely extract the host from a URL and compare it.
     */
    protected function isHostMatching(string $url, string $primaryDomain): bool
    {
        // Add a dummy scheme if missing so parse_url works correctly
        if (!preg_match('#^https?://#i', $url)) {
            $url = 'http://' . $url;
        }

        $host = parse_url(strtolower($url), PHP_URL_HOST);
        
        if (!$host) {
            return false;
        }

        // Return true if it matches exactly (example.com) 
        // or is a subdomain of the primary domain (www.example.com, app.example.com)
        return $host === $primaryDomain || str_ends_with($host, '.' . $primaryDomain);
    }
}