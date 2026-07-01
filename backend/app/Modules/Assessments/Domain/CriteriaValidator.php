<?php

declare(strict_types=1);

namespace App\Modules\Assessments\Domain;

use App\Modules\Assessments\Domain\Exceptions\InvalidCriteriaException;

/**
 * Validates that criteria are structural data only — no code injection.
 * Each criterion: criterion_id (string, non-empty), label (string, non-empty),
 * max_points (numeric, > 0), descriptors (array<string>|null).
 * Also provides canonical JSON for content-addressed versioning.
 */
final class CriteriaValidator
{
    /**
     * @param  array<int, mixed>  $criteria
     *
     * @throws InvalidCriteriaException
     */
    public function validate(array $criteria): void
    {
        foreach ($criteria as $index => $criterion) {
            if (! is_array($criterion)) {
                throw new InvalidCriteriaException("Criterion at index {$index} must be an object.");
            }

            $id = $criterion['criterion_id'] ?? '';
            if (! is_string($id) || trim($id) === '') {
                throw new InvalidCriteriaException("Criterion at index {$index} requires a non-empty criterion_id string.");
            }

            $label = $criterion['label'] ?? '';
            if (! is_string($label) || trim($label) === '') {
                throw new InvalidCriteriaException("Criterion at index {$index} requires a non-empty label string.");
            }

            $maxPoints = $criterion['max_points'] ?? null;
            if (! is_numeric($maxPoints) || (float) $maxPoints <= 0) {
                throw new InvalidCriteriaException("Criterion at index {$index}: max_points must be a positive number.");
            }

            $descriptors = $criterion['descriptors'] ?? null;
            if ($descriptors !== null) {
                if (! is_array($descriptors)) {
                    throw new InvalidCriteriaException("Criterion at index {$index}: descriptors must be an array or null.");
                }
                foreach ($descriptors as $d) {
                    if (! is_string($d)) {
                        throw new InvalidCriteriaException("Criterion at index {$index}: each descriptor must be a string.");
                    }
                }
            }
        }
    }

    /**
     * Stable canonical serialization: recursively key-sorted JSON.
     * Used to compute content_hash for content-addressed publish idempotency.
     *
     * @param  array<array-key, mixed>  $criteria
     */
    public function canonicalJson(array $criteria): string
    {
        $sorted = $this->ksortRecursive($criteria);

        return json_encode($sorted, JSON_THROW_ON_ERROR);
    }

    /**
     * @param  array<array-key, mixed>  $value
     * @return array<array-key, mixed>
     */
    private function ksortRecursive(array $value): array
    {
        ksort($value);
        foreach ($value as $k => $v) {
            if (is_array($v)) {
                $value[$k] = $this->ksortRecursive($v);
            }
        }

        return $value;
    }
}
