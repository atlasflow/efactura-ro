<?php

declare(strict_types=1);

namespace AtlasFlow\EFacturaRo\Validation;

final readonly class ValidationResult
{
    /**
     * @param  list<ValidationError>  $errors
     */
    private function __construct(
        public bool $ok,
        public array $errors,
        public ?string $traceId = null,
    ) {}

    public static function ok(?string $traceId = null): self
    {
        return new self(true, [], $traceId);
    }

    /**
     * @param  list<ValidationError>  $errors
     */
    public static function failed(array $errors, ?string $traceId = null): self
    {
        return $errors === [] ? self::ok($traceId) : new self(false, array_values($errors), $traceId);
    }

    public function merge(ValidationResult $other): self
    {
        return self::failed([...$this->errors, ...$other->errors], $other->traceId ?? $this->traceId);
    }

    /** @return list<ValidationError> */
    public function from(ValidationSource $source): array
    {
        return array_values(array_filter($this->errors, fn (ValidationError $e) => $e->source === $source));
    }

    /** @return list<string> */
    public function codes(): array
    {
        return array_values(array_unique(array_map(fn (ValidationError $e) => $e->code, $this->errors)));
    }

    public function has(string $code): bool
    {
        return in_array($code, $this->codes(), true);
    }
}
