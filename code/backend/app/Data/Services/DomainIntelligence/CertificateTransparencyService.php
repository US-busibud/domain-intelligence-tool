<?php

namespace App\Data\Services\DomainIntelligence;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class CertificateTransparencyService
{
    public function fetchDomains(string $domain): array
    {
        // Clean domain (remove protocol or www if passed)
        $cleanDomain = parse_url($domain, PHP_URL_HOST) ?? $domain;
        $cleanDomain = preg_replace('/^www\./', '', trim($cleanDomain));

        // crt.sh JSON endpoint query
        $url = "https://crt.sh/?q=%25.{$cleanDomain}&output=json";

        try {
            // Making HTTP request with a timeout
            $response = Http::timeout(15)->get($url);

            if ($response->successful() && is_array($response->json())) {
                $entries = $response->json();
                $domains = [];

                foreach ($entries as $entry) {
                    if (isset($entry['name_value'])) {
                        // crt.sh sometimes returns multiple domains separated by newlines in one entry
                        $names = explode("\n", $entry['name_value']);
                        foreach ($names as $name) {
                            $name = trim(strtolower($name));
                            
                            // Filter out wildcard domains (e.g., *.example.com) and keep valid hostnames
                            if (!str_starts_with($name, '*') && filter_var($name, FILTER_VALIDATE_DOMAIN, FILTER_FLAG_HOSTNAME)) {
                                $domains[] = $name;
                            }
                        }
                    }
                }

                // Remove duplicates and re-index array
                return array_values(array_unique($domains));
            }
        } catch (\Exception $e) {
            Log::error("crt.sh fetch failed for {$cleanDomain}: " . $e->getMessage());
        }

        return [];
    }
}