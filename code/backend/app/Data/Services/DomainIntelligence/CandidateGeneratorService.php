<?php

namespace App\Data\Services\DomainIntelligence;

class CandidateGeneratorService
{
    // V1 Prototype ke liye kuch basic prefixes, suffixes aur TLDs
    protected array $prefixes = ['get', 'my', 'try', 'login-'];
    protected array $suffixes = ['-mail', 'app', 'hq', 'inc', '-support'];
    protected array $tlds = ['.com', '.net', '.io', '.co', '.org'];

    /**
     * Generates a list of candidate lookalike domains based on the brand name.
     *
     * @param string $brand
     * @return array
     */
    public function generate(string $brand): array
    {
        $candidates = [];

        // 1. Original brand with different TLDs (e.g., acme.net, acme.io)
        foreach ($this->tlds as $tld) {
            $candidates[] = $brand . $tld;
        }

        // 2. Prefixes + brand + .com (e.g., getacme.com, login-acme.com)
        foreach ($this->prefixes as $prefix) {
            $candidates[] = $prefix . $brand . '.com';
        }

        // 3. Brand + suffixes + .com (e.g., acme-mail.com, acmehq.com)
        foreach ($this->suffixes as $suffix) {
            $candidates[] = $brand . $suffix . '.com';
        }

        // Duplicate entry check karke array return karna
        return array_values(array_unique($candidates));
    }
}