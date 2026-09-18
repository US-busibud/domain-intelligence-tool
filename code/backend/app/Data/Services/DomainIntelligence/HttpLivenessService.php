<?php

namespace App\Data\Services\DomainIntelligence;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class HttpLivenessService
{
    /**
     * Check if the domain is alive on HTTPS or HTTP.
     */
    public function check(string $domain): array
    {
        // Step 1: Try HTTPS first.
        $httpsResult = $this->ping("https://{$domain}");

        if ($httpsResult['is_alive']) {
            return $httpsResult;
        }

        // Step 2: Fallback to HTTP if HTTPS fails.
        return $this->ping("http://{$domain}");
    }

    /**
     * Make a GET request and extract website + redirect information
     * from the same response.
     */
    protected function ping(string $url): array
    {
        try {
            $response = Http::timeout(5)
                ->withOptions([
                    // We need the original response so we can inspect
                    // redirect status and Location header ourselves.
                    'allow_redirects' => false,

                    // Keep this disabled for V1 so SSL issues do not
                    // prevent us from checking the domain.
                    'verify' => false,
                ])
                ->get($url);

            $statusCode = $response->status();
            $redirectUrl = null;

            // Check whether the response is a redirect.
            if ($response->redirect()) {
                $redirectUrl = $response->header('Location');
            }

            $html = $response->body();

            $pageTitle = null;
            $pageText = null;
            $pageLinks = [];

            // Extract HTML data.
            if (!empty($html)) {
                libxml_use_internal_errors(true);

                $dom = new \DOMDocument();

                @$dom->loadHTML(
                    mb_convert_encoding($html, 'HTML-ENTITIES', 'UTF-8')
                );

                // 1. Extract title.
                $titleNodes = $dom->getElementsByTagName('title');

                if ($titleNodes->length > 0) {
                    $pageTitle = trim(
                        $titleNodes->item(0)->nodeValue
                    );
                }

                // 2. Extract clean text.
                $bodyNodes = $dom->getElementsByTagName('body');

                if ($bodyNodes->length > 0) {
                    $rawText = strip_tags(
                        $bodyNodes->item(0)->nodeValue
                    );

                    $pageText = trim(
                        preg_replace('/\s+/', ' ', $rawText)
                    );
                }

                // 3. Extract links.
                $aNodes = $dom->getElementsByTagName('a');

                foreach ($aNodes as $a) {
                    $href = $a->getAttribute('href');

                    if (
                        !empty($href) &&
                        !str_starts_with($href, '#') &&
                        !str_starts_with($href, 'javascript:')
                    ) {
                        $pageLinks[] = $href;
                    }
                }

                $pageLinks = array_values(
                    array_unique($pageLinks)
                );

                libxml_clear_errors();
            }

            return [
                'is_alive' => true,
                'url' => $url,
                'status_code' => $statusCode,

                // NEW: redirect information from the same GET request.
                'has_redirect' => $response->redirect(),
                'redirect_url' => $redirectUrl,

                'page_title' => $pageTitle,
                'page_text' => substr($pageText ?? '', 0, 500),
                'page_links' => $pageLinks,
            ];

        } catch (\Exception $e) {
            Log::debug(
                "Liveness check failed for {$url}: " . $e->getMessage()
            );

            return [
                'is_alive' => false,
                'url' => $url,
                'status_code' => null,

                'has_redirect' => false,
                'redirect_url' => null,

                'page_title' => null,
                'page_text' => null,
                'page_links' => [],
            ];
        }
    }
}