<?php

namespace App\Data\Services\DomainIntelligence;

use Illuminate\Support\Facades\Concurrency;

class EmailInfrastructureService
{
    protected int $concurrency = 10;

    protected array $dkimSelectors = [
        'google',
        'selector1',
        'selector2',
        'k1',
        'default',
        'mail',
        'dkim',
    ];

    public function analyze(string $domain): array
    {
        return [
            'has_mx' => $this->checkMx($domain),
            'has_spf' => $this->checkSpf($domain),
            'has_dmarc' => $this->checkDmarc($domain),
            'has_dkim' => $this->checkDkim($domain),
            'dkim_selectors' => $this->findDkimSelectors($domain),
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

    protected function checkMx(string $domain): bool
    {
        try {
            $records = @dns_get_record($domain, DNS_MX);

            return !empty($records);
        } catch (\Throwable $e) {
            return false;
        }
    }

    protected function checkSpf(string $domain): bool
    {
        try {
            $records = @dns_get_record($domain, DNS_TXT);

            if (!$records) {
                return false;
            }

            foreach ($records as $record) {
                if (
                    isset($record['txt']) &&
                    str_starts_with(
                        strtolower(trim($record['txt'])),
                        'v=spf1'
                    )
                ) {
                    return true;
                }
            }
        } catch (\Throwable $e) {
            return false;
        }

        return false;
    }

    protected function checkDmarc(string $domain): bool
    {
        try {
            $records = @dns_get_record(
                '_dmarc.' . $domain,
                DNS_TXT
            );

            if (!$records) {
                return false;
            }

            foreach ($records as $record) {
                if (
                    isset($record['txt']) &&
                    str_starts_with(
                        strtolower(trim($record['txt'])),
                        'v=dmarc1'
                    )
                ) {
                    return true;
                }
            }
        } catch (\Throwable $e) {
            return false;
        }

        return false;
    }

    protected function checkDkim(string $domain): bool
    {
        return !empty($this->findDkimSelectors($domain));
    }

    protected function findDkimSelectors(string $domain): array
    {
        $foundSelectors = [];

        foreach ($this->dkimSelectors as $selector) {
            try {
                $records = @dns_get_record(
                    $selector . '._domainkey.' . $domain,
                    DNS_TXT
                );

                if (!empty($records)) {
                    foreach ($records as $record) {
                        if (
                            isset($record['txt']) &&
                            !empty(trim($record['txt']))
                        ) {
                            $foundSelectors[] = $selector;
                            break;
                        }
                    }
                }
            } catch (\Throwable $e) {
                continue;
            }
        }

        return array_values(array_unique($foundSelectors));
    }
}