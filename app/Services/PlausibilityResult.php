<?php

namespace App\Services;

final readonly class PlausibilityResult
{
    /**
     * @param  list<string>  $errors  Hard errors: the reading cannot be correct and is rejected.
     * @param  string|null  $warning  Soft warning: the reading is stored but flagged for review.
     */
    public function __construct(
        public array $errors = [],
        public ?string $warning = null,
    ) {}

    public function isRejected(): bool
    {
        return $this->errors !== [];
    }
}
