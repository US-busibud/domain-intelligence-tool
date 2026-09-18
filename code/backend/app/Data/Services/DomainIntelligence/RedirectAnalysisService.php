<?php

namespace App\Data\Services\DomainIntelligence;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class RedirectAnalysisService
{
    /**
     * Check if the given URL redirects to another destination.
     *
     * @param string $url The base URL to check (e.g., https://example.com)
     * @return array
     */
    public function analyze(string $url): array
    {
        try {
            // We use a 'HEAD' request to get only the headers.
            // We disable automatic redirects ('allow_redirects' => false) so we can manually 
            // catch the exact 301 or 302 response and see where it points.
            $response = Http::timeout(5)
                ->withOptions([
                    'allow_redirects' => false,
                    
                    // Disable SSL verification to ensure we still read headers 
                    // even if the certificate is expired or invalid.
                    'verify' => false, 
                ])
                ->head($url);

            $statusCode = $response->status();

            // Check if the status code represents a redirect (e.g., 301, 302, 307, 308)
            // Laravel provides a convenient isRedirect() method for this.
            if ($response->redirect()) {
                    
                // Get the 'Location' header which contains the target URL
                $redirectUrl = $response->header('Location');

                return [
                    'has_redirect' => true,
                    'original_url' => $url,
                    'redirect_url' => $redirectUrl,
                    'status_code' => $statusCode,
                ];
            }

            // Return false if the response is a normal 200 OK or anything other than a redirect
            return [
                'has_redirect' => false,
                'original_url' => $url,
                'redirect_url' => null,
                'status_code' => $statusCode,
            ];

        } catch (\Exception $e) {
            // Log any connection timeouts or DNS failures silently
            Log::debug("Redirect analysis failed for {$url}: " . $e->getMessage());

            return [
                'has_redirect' => false,
                'original_url' => $url,
                'redirect_url' => null,
                'status_code' => null,
            ];
        }
    }
}