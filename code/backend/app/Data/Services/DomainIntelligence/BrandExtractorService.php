<?php

namespace App\Data\Services\DomainIntelligence;

class BrandExtractorService
{
    /**
     * Extracts the brand name from a normalized domain.
     * As per Master Handoff V1 scope: extracts the first domain segment.
     *
     * @param string $normalizedDomain
     * @return string
     */
    public function extract(string $normalizedDomain): string
    {
        // Domain ko dot (.) ke base par split karein
        $parts = explode('.', $normalizedDomain);
        
        // Pehla hissa return karein (e.g., 'acme' from 'acme.com')
        return $parts[0] ?? $normalizedDomain;
    }
}