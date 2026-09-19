<?php

namespace App\Data\Services\DomainIntelligence;

use Illuminate\Http\Client\Pool;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class HttpLivenessService
{
    protected int $concurrency = 10;

    protected int $timeout = 3;

    protected int $maxBodySize = 2000000;

    public function check(string $domain): array
    {
        if (!$this->isSafeDomain($domain)) {
            return $this->failedResult("https://{$domain}");
        }

        $httpsResult = $this->ping("https://{$domain}");

        if ($httpsResult['is_alive']) {
            return $httpsResult;
        }

        return $this->ping("http://{$domain}");
    }

    public function checkMany(array $domains): array
    {
        $results = [];

        foreach (array_chunk($domains, $this->concurrency) as $batch) {
            $batchResults = $this->checkBatch($batch);

            foreach ($batchResults as $domain => $result) {
                $results[$domain] = $result;
            }
        }

        return $results;
    }

    protected function checkBatch(array $domains): array
    {
        $results = [];
        $safeDomains = [];

        foreach ($domains as $domain) {
            if ($this->isSafeDomain($domain)) {
                $safeDomains[] = $domain;
            } else {
                $results[$domain] = $this->failedResult(
                    "https://{$domain}"
                );
            }
        }

        if (empty($safeDomains)) {
            return $results;
        }

        $httpsResponses = Http::pool(
            function (Pool $pool) use ($safeDomains) {
                $requests = [];

                foreach ($safeDomains as $domain) {
                    $requests[$domain] = $pool
                        ->as($domain)
                        ->timeout($this->timeout)
                        ->withOptions([
                            'allow_redirects' => false,
                            'verify' => false,
                        ])
                        ->get("https://{$domain}");
                }

                return $requests;
            }
        );

        $httpFallbackDomains = [];

        foreach ($safeDomains as $domain) {
            $response = $httpsResponses[$domain] ?? null;

            if (!$response || $response instanceof \Throwable) {
                $httpFallbackDomains[] = $domain;
                continue;
            }

            $results[$domain] = $this->parseResponse(
                $response,
                "https://{$domain}"
            );
        }

        if (!empty($httpFallbackDomains)) {
            $httpResponses = Http::pool(
                function (Pool $pool) use ($httpFallbackDomains) {
                    $requests = [];

                    foreach ($httpFallbackDomains as $domain) {
                        $requests[$domain] = $pool
                            ->as($domain)
                            ->timeout($this->timeout)
                            ->withOptions([
                                'allow_redirects' => false,
                                'verify' => false,
                            ])
                            ->get("http://{$domain}");
                    }

                    return $requests;
                }
            );

            foreach ($httpFallbackDomains as $domain) {
                $response = $httpResponses[$domain] ?? null;

                if (!$response || $response instanceof \Throwable) {
                    $results[$domain] = $this->failedResult(
                        "http://{$domain}"
                    );

                    continue;
                }

                $results[$domain] = $this->parseResponse(
                    $response,
                    "http://{$domain}"
                );
            }
        }

        return $results;
    }

    protected function ping(string $url): array
    {
        try {
            $response = Http::timeout($this->timeout)
                ->withOptions([
                    'allow_redirects' => false,
                    'verify' => false,
                ])
                ->get($url);

            return $this->parseResponse($response, $url);

        } catch (\Throwable $e) {
            Log::debug(
                "Liveness check failed for {$url}: "
                . $e->getMessage()
            );

            return $this->failedResult($url);
        }
    }

    protected function parseResponse(
        $response,
        string $url
    ): array {
        $statusCode = $response->status();

        $redirectUrl = null;

        if ($response->redirect()) {
            $redirectUrl = $response->header('Location');

            if (
                !empty($redirectUrl) &&
                !$this->isSafeRedirectUrl($redirectUrl)
            ) {
                return [
                    'is_alive' => true,
                    'url' => $url,
                    'status_code' => $response->status(),
                    'has_redirect' => true,
                    'redirect_url' => null, 
                    'redirect_blocked' => true,
                    'page_title' => null,
                    'page_text' => null,
                    'page_links' => [],
                ];
            }
        }

        if (
            $statusCode < 200 ||
            $statusCode >= 400
        ) {
            return [
                'is_alive' => true,
                'url' => $url,
                'status_code' => $statusCode,
                'has_redirect' => $response->redirect(),
                'redirect_url' => $redirectUrl,
                'redirect_blocked' => false,
                'page_title' => null,
                'page_text' => null,
                'page_links' => [],
            ];
        }

        $html = $response->body();

        if (empty($html)) {
            return [
                'is_alive' => true,
                'url' => $url,
                'status_code' => $statusCode,
                'has_redirect' => $response->redirect(),
                'redirect_url' => $redirectUrl,
                'redirect_blocked' => false,
                'page_title' => null,
                'page_text' => null,
                'page_links' => [],
            ];
        }

        if (strlen($html) > $this->maxBodySize) {
            $html = substr($html, 0, $this->maxBodySize);
        }

        $pageTitle = null;
        $pageText = null;
        $pageLinks = [];

        libxml_use_internal_errors(true);

        $dom = new \DOMDocument();

        @$dom->loadHTML(
            mb_convert_encoding(
                $html,
                'HTML-ENTITIES',
                'UTF-8'
            )
        );

        $titleNodes = $dom->getElementsByTagName('title');

        if ($titleNodes->length > 0) {
            $pageTitle = trim(
                $titleNodes->item(0)->nodeValue
            );
        }

        $bodyNodes = $dom->getElementsByTagName('body');

        if ($bodyNodes->length > 0) {
            $rawText = strip_tags(
                $bodyNodes->item(0)->nodeValue
            );

            $pageText = trim(
                preg_replace('/\s+/', ' ', $rawText)
            );
        }

        $aNodes = $dom->getElementsByTagName('a');

        foreach ($aNodes as $a) {
            $href = $a->getAttribute('href');

            if (
                !empty($href) &&
                !str_starts_with($href, '#') &&
                !str_starts_with(
                    strtolower($href),
                    'javascript:'
                )
            ) {
                $pageLinks[] = $href;
            }
        }

        $pageLinks = array_values(
            array_unique($pageLinks)
        );

        libxml_clear_errors();

        return [
            'is_alive' => true,
            'url' => $url,
            'status_code' => $statusCode,
            'has_redirect' => $response->redirect(),
            'redirect_url' => $redirectUrl,
            'redirect_blocked' => false,
            'page_title' => $pageTitle,
            'page_text' => substr($pageText ?? '', 0, 20000),
            'page_links' => $pageLinks,
        ];
    }

    protected function isSafeDomain(string $domain): bool
    {
        $domain = trim(strtolower($domain));

        if (empty($domain)) {
            return false;
        }

        if (
            $domain === 'localhost' ||
            str_ends_with($domain, '.localhost') ||
            $domain === 'localhost.localdomain'
        ) {
            return false;
        }

        $ips = @dns_get_record(
            $domain,
            DNS_A | DNS_AAAA
        );

        if (!is_array($ips) || empty($ips)) {
            return false;
        }

        foreach ($ips as $record) {
            $ip = $record['ip'] ?? $record['ipv6'] ?? null;

            if (
                $ip &&
                !$this->isPublicIp($ip)
            ) {
                return false;
            }
        }

        return true;
    }

    protected function isSafeRedirectUrl(string $redirectUrl): bool
    {
        if (!preg_match('#^https?://#i', $redirectUrl)) {
            return false;
        }

        $host = parse_url($redirectUrl, PHP_URL_HOST);

        if (!$host) {
            return false;
        }

        return $this->isSafeDomain($host);
    }

    protected function isPublicIp(string $ip): bool
    {
        return filter_var(
            $ip,
            FILTER_VALIDATE_IP,
            FILTER_FLAG_NO_PRIV_RANGE |
            FILTER_FLAG_NO_RES_RANGE
        ) !== false;
    }

    protected function failedResult(string $url): array
    {
        return [
            'is_alive' => false,
            'url' => $url,
            'status_code' => null,
            'has_redirect' => false,
            'redirect_url' => null,
            'redirect_blocked' => false,
            'page_title' => null,
            'page_text' => null,
            'page_links' => [],
        ];
    }
}