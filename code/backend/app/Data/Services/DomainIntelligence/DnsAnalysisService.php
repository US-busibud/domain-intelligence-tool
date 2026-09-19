<?php

namespace App\Data\Services\DomainIntelligence;

use Illuminate\Support\Facades\Concurrency;

class DnsAnalysisService
{
    protected int $concurrency = 10;

    public function verifyDomain(string $domain): bool
    {
        $domain = trim(strtolower($domain));

        if (empty($domain)) {
            return false;
        }

        $dnsData = $this->analyze($domain);

        return $dnsData['has_dns'];
    }

    public function analyze(string $domain): array
    {
        $domain = trim(strtolower($domain));

        if (empty($domain)) {
            return $this->emptyResult($domain);
        }

        $aRecords = $this->safeDnsGetRecord($domain, DNS_A);
        $aaaaRecords = $this->safeDnsGetRecord($domain, DNS_AAAA);
        $cnameRecords = $this->safeDnsGetRecord($domain, DNS_CNAME);
        $nsRecords = $this->safeDnsGetRecord($domain, DNS_NS);

        $a = [];
        foreach ($aRecords as $record) {
            if (!empty($record['ip'])) {
                $a[] = $record['ip'];
            }
        }

        $aaaa = [];
        foreach ($aaaaRecords as $record) {
            if (!empty($record['ipv6'])) {
                $aaaa[] = $record['ipv6'];
            }
        }

        $cname = [];
        foreach ($cnameRecords as $record) {
            if (!empty($record['target'])) {
                $cname[] = $record['target'];
            }
        }

        $ns = [];
        foreach ($nsRecords as $record) {
            if (!empty($record['target'])) {
                $ns[] = $record['target'];
            }
        }

        $a = array_values(array_unique($a));
        $aaaa = array_values(array_unique($aaaa));
        $cname = array_values(array_unique($cname));
        $ns = array_values(array_unique($ns));

        return [
            'domain' => $domain,
            'has_dns' => !empty($a)
                || !empty($aaaa)
                || !empty($cname)
                || !empty($ns),
            'a' => $a,
            'aaaa' => $aaaa,
            'cname' => $cname,
            'ns' => $ns,
        ];
    }

    public function analyzeMany(array $domains): array
    {
        $results = [];

        foreach (array_chunk($domains, $this->concurrency) as $batch) {
            $tasks = [];

            foreach ($batch as $domain) {
                $tasks[$domain] = function () use ($domain) {
                    return $this->analyze($domain);
                };
            }

            $batchResults = Concurrency::run($tasks);

            foreach ($batchResults as $domain => $result) {
                $results[$domain] = $result;
            }
        }

        return $results;
    }

    protected function safeDnsGetRecord(
        string $domain,
        int $type
    ): array {
        try {
            $records = @dns_get_record($domain, $type);

            return is_array($records) ? $records : [];
        } catch (\Throwable $e) {
            return [];
        }
    }

    protected function emptyResult(string $domain): array
    {
        return [
            'domain' => $domain,
            'has_dns' => false,
            'a' => [],
            'aaaa' => [],
            'cname' => [],
            'ns' => [],
        ];
    }

    public function filterValidDomains(array $domains): array
    {
        $dnsResults = $this->analyzeMany($domains);

        $validDomains = [];

        foreach ($dnsResults as $domain => $dnsData) {
            if ($dnsData['has_dns']) {
                $validDomains[] = $domain;
            }
        }

        return array_values(array_unique($validDomains));
    }
}