<?php

namespace App\Data\Services\DomainIntelligence;

use InvalidArgumentException;

class DomainNormalizerService
{
    /**
     * Normalizes a given domain string.
     *
     * @param string $inputDomain
     * @return string
     * @throws InvalidArgumentException
     */
    public function normalize(string $inputDomain): string
    {
        $domain = strtolower(trim($inputDomain));

        if (!str_starts_with($domain, 'http://') && !str_starts_with($domain, 'https://')) {
            $domain = 'https://' . $domain;
        }

        $host = parse_url($domain, PHP_URL_HOST);

        if (!$host) {
            throw new InvalidArgumentException("Invalid domain input provided.");
        }

        if (str_starts_with($host, 'www.')) {
            $host = substr($host, 4);
        }

        return $host;
    }
}