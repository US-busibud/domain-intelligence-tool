<?php

namespace App\Data\Services\DomainIntelligence;

class DnsAnalysisService
{
    public function verifyDomain(string $domain): bool
    {
        // Clean domain
        $domain = trim(strtolower($domain));

        if (empty($domain)) {
            return false;
        }

        // Check for A, AAAA, or CNAME DNS records to verify if the domain is live/resolving
        $hasRecord = false;

        // Check A record
        if (checkdnsrr($domain, 'A')) {
            $hasRecord = true;
        }
        // Check CNAME record
        elseif (checkdnsrr($domain, 'CNAME')) {
            $hasRecord = true;
        }
        // Check AAAA record (IPv6)
        elseif (checkdnsrr($domain, 'AAAA')) {
            $hasRecord = true;
        }

        return $hasRecord;
    }

    public function filterValidDomains(array $domains): array
    {
        $validDomains = [];

        foreach ($domains as $domain) {
            if ($this->verifyDomain($domain)) {
                $validDomains[] = $domain;
            }
        }

        return array_values(array_unique($validDomains));
    }
}