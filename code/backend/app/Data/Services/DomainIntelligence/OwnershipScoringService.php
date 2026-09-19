<?php

namespace App\Data\Services\DomainIntelligence;

class OwnershipScoringService
{
    public function calculate(
        string $candidateDomain,
        string $primaryDomain,
        array $evidence
    ): array {
        $score = 0;
        $reasons = [];

        if ($this->isSubdomain($candidateDomain, $primaryDomain)) {
            $score += 30;
            $reasons[] = [
                'type' => 'subdomain',
                'points' => 30,
                'reason' =>
                    'Candidate is a subdomain of the primary domain.',
            ];
        }

        if ($evidence['redirects_to_primary'] ?? false) {
            $score += 40;
            $reasons[] = [
                'type' => 'redirect',
                'points' => 40,
                'reason' =>
                    'Candidate redirects to the primary domain.',
            ];
        }

        if ($evidence['primary_links_to_candidate'] ?? false) {
            $score += 30;
            $reasons[] = [
                'type' => 'primary_link',
                'points' => 30,
                'reason' =>
                    'Primary website links to the candidate domain.',
            ];
        }

        if ($evidence['links_to_primary'] ?? false) {
            $score += 25;
            $reasons[] = [
                'type' => 'candidate_link',
                'points' => 25,
                'reason' =>
                    'Candidate links to the primary domain.',
            ];
        }

        if ($evidence['mentions_brand'] ?? false) {
            $score += 10;
            $reasons[] = [
                'type' => 'brand',
                'points' => 10,
                'reason' =>
                    'Candidate website mentions the company brand.',
            ];
        }

        if ($evidence['brand_domain_match'] ?? false) {
            $score += 5;
            $reasons[] = [
                'type' => 'brand_domain',
                'points' => 5,
                'reason' =>
                    'Candidate domain contains the company brand.',
            ];
        }

        if ($evidence['has_email_infrastructure'] ?? false) {
            $score += 5;
            $reasons[] = [
                'type' => 'email',
                'points' => 5,
                'reason' =>
                    'Candidate has email infrastructure.',
            ];
        }

        if ($evidence['is_alive'] ?? false) {
            $score += 3;
            $reasons[] = [
                'type' => 'website',
                'points' => 3,
                'reason' =>
                    'Candidate website is reachable.',
            ];
        }

        if ($evidence['uses_https'] ?? false) {
            $score += 2;
            $reasons[] = [
                'type' => 'https',
                'points' => 2,
                'reason' =>
                    'Candidate is reachable over HTTPS.',
            ];
        }

        if ($evidence['is_parked'] ?? false) {
            $score -= 20;
            $reasons[] = [
                'type' => 'parking',
                'points' => -20,
                'reason' =>
                    'Candidate appears to be a parked or domain-for-sale domain.',
            ];
        }

        $score = max(0, min($score, 100));

        return [
            'score' => $score,
            'classification' => $this->classify($score),
            'reasons' => $reasons,
        ];
    }

    protected function classify(int $score): string
    {
        if ($score >= 90) return 'Very Likely Company-Owned';
        if ($score >= 75) return 'Likely Company-Owned';
        if ($score >= 50) return 'Possibly Company-Owned';
        if ($score >= 25) return 'Weak Relationship';
        return 'Insufficient Evidence';
    }

    protected function isSubdomain(
        string $candidateDomain,
        string $primaryDomain
    ): bool {
        $candidateDomain = strtolower(trim($candidateDomain));
        $primaryDomain = strtolower(trim($primaryDomain));

        if ($candidateDomain === $primaryDomain) {
            return false;
        }

        return str_ends_with(
            $candidateDomain,
            '.' . $primaryDomain
        );
    }
}