<?php

namespace App\Data\Services\DomainIntelligence;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class CertificateTransparencyService
{
    protected int $timeout = 10;

    protected int $connectTimeout = 3;

    public function fetchDomains(string $domain): array
    {
        $cleanDomain = parse_url($domain, PHP_URL_HOST) ?? $domain;

        $cleanDomain = preg_replace(
            '/^www\./',
            '',
            trim(strtolower($cleanDomain))
        );

        $url = "https://crt.sh/?q=%25.{$cleanDomain}&output=json";

        try {
            $response = Http::connectTimeout($this->connectTimeout)
                ->timeout($this->timeout)
                ->get($url);

            if (
                !$response->successful() ||
                !is_array($response->json())
            ) {
                return [];
            }

            $entries = $response->json();
            $domains = [];

            foreach ($entries as $entry) {
                if (!isset($entry['name_value'])) {
                    continue;
                }

                $names = explode(
                    "\n",
                    $entry['name_value']
                );

                foreach ($names as $name) {
                    $name = trim(strtolower($name));

                    if (
                        str_starts_with($name, '*.')
                    ) {
                        continue;
                    }

                    if (
                        !filter_var(
                            $name,
                            FILTER_VALIDATE_DOMAIN,
                            FILTER_FLAG_HOSTNAME
                        )
                    ) {
                        continue;
                    }

                    if (
                        $name === $cleanDomain ||
                        str_ends_with(
                            $name,
                            '.' . $cleanDomain
                        )
                    ) {
                        $domains[] = $name;
                    }
                }
            }

            return array_values(
                array_unique($domains)
            );

        } catch (\Throwable $e) {
            Log::error(
                "crt.sh fetch failed for {$cleanDomain}: "
                . $e->getMessage()
            );
        }

        return [];
    }
}