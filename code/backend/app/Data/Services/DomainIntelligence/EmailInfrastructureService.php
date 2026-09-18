<?php

namespace App\Data\Services\DomainIntelligence;

class EmailInfrastructureService
{
    /**
     * Analyze email infrastructure (MX, SPF, DMARC) for a given domain.
     * Note: DKIM is highly specific to selectors (like 'google._domainkey') 
     * so it cannot be universally scanned without knowing the selector beforehand.
     */
    public function analyze(string $domain): array
    {
        return [
            'has_mx'    => $this->checkMx($domain),
            'has_spf'   => $this->checkSpf($domain),
            'has_dmarc' => $this->checkDmarc($domain),
        ];
    }

    /**
     * Check if the domain has MX (Mail Exchange) records.
     * If true, the domain is configured to receive emails.
     */
    protected function checkMx(string $domain): bool
    {
        $records = @dns_get_record($domain, DNS_MX);
        return !empty($records);
    }

    /**
     * Check if the domain has an SPF (Sender Policy Framework) record.
     * Prevents unauthorized IP addresses from sending emails on behalf of the domain.
     */
    protected function checkSpf(string $domain): bool
    {
        $records = @dns_get_record($domain, DNS_TXT);
        if (!$records) {
            return false;
        }

        foreach ($records as $record) {
            if (isset($record['txt']) && str_starts_with(strtolower($record['txt']), 'v=spf1')) {
                return true;
            }
        }
        return false;
    }

    /**
     * Check if the domain has a DMARC record.
     * Specifies how the receiver should handle emails that fail SPF/DKIM checks.
     */
    protected function checkDmarc(string $domain): bool
    {
        // DMARC records are always stored at the '_dmarc' subdomain
        $records = @dns_get_record('_dmarc.' . $domain, DNS_TXT);
        if (!$records) {
            return false;
        }

        foreach ($records as $record) {
            if (isset($record['txt']) && str_starts_with(strtolower($record['txt']), 'v=dmarc1')) {
                return true;
            }
        }
        return false;
    }
}