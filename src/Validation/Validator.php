<?php

declare(strict_types=1);

namespace Lubie\Validation;

use Lubie\Http\HttpException;
use Lubie\Support\Money;

/**
 * Server-side validation (authoritative; browser checks are only hints).
 * Collects field messages in plain language and throws one 422 at the end.
 */
final class Validator
{
    /** @var array<string, string> */
    private array $errors = [];
    /** @var array<string, mixed> */
    private array $clean = [];

    /** @param array<string, mixed> $input */
    public function __construct(private array $input)
    {
    }

    /** @param array<string, mixed> $input */
    public static function of(array $input): self
    {
        return new self($input);
    }

    private function raw(string $f): mixed
    {
        $v = $this->input[$f] ?? null;
        return is_string($v) ? trim($v) : $v;
    }

    private function missing(string $f): bool
    {
        $v = $this->raw($f);
        return $v === null || $v === '';
    }

    public function string(string $f, int $max, bool $required = true, int $min = 1): self
    {
        if ($this->missing($f)) {
            $required ? $this->errors[$f] = 'This field is required.' : $this->clean[$f] = null;
            return $this;
        }
        $v = $this->raw($f);
        if (!is_string($v) && !is_numeric($v)) {
            $this->errors[$f] = 'Enter text.';
        } elseif (mb_strlen((string) $v) > $max || mb_strlen((string) $v) < $min) {
            $this->errors[$f] = "Enter {$min} to {$max} characters.";
        } else {
            $this->clean[$f] = (string) $v;
        }
        return $this;
    }

    public function int(string $f, int $min, int $max, bool $required = true): self
    {
        if ($this->missing($f)) {
            $required ? $this->errors[$f] = 'This field is required.' : $this->clean[$f] = null;
            return $this;
        }
        $v = $this->raw($f);
        if (!is_int($v) && !(is_string($v) && preg_match('/^-?\d+$/', $v))) {
            $this->errors[$f] = 'Enter a whole number.';
        } elseif ((int) $v < $min || (int) $v > $max) {
            $this->errors[$f] = "Enter a number from {$min} to {$max}.";
        } else {
            $this->clean[$f] = (int) $v;
        }
        return $this;
    }

    /** Decimal with up to two places, stored as hundredths (e.g. 85.5 % -> 8550). */
    public function percent(string $f, bool $required = true): self
    {
        if ($this->missing($f)) {
            $required ? $this->errors[$f] = 'This field is required.' : $this->clean[$f] = null;
            return $this;
        }
        $v = (string) $this->raw($f);
        if (!preg_match('/^\d{1,3}(\.\d{1,2})?$/', $v) || (float) $v > 100) {
            $this->errors[$f] = 'Enter a value from 0 to 100.';
        } else {
            $this->clean[$f] = $v;
        }
        return $this;
    }

    /** Money in GH₵ with up to two decimals; returns pesewas. */
    public function money(string $f, bool $required = true, bool $positive = false): self
    {
        if ($this->missing($f)) {
            $required ? $this->errors[$f] = 'This field is required.' : $this->clean[$f] = null;
            return $this;
        }
        try {
            $p = Money::toPesewas((string) $this->raw($f));
            if ($p === null || $p < 0 || ($positive && $p === 0)) {
                $this->errors[$f] = $positive ? 'Enter an amount greater than 0.' : 'Enter an amount of 0 or more.';
            } elseif ($p > 99_999_999_999_999) {
                $this->errors[$f] = 'The amount is too large.';
            } else {
                $this->clean[$f] = $p;
            }
        } catch (\InvalidArgumentException) {
            $this->errors[$f] = 'Enter an amount such as 1250.50 (two decimals at most).';
        }
        return $this;
    }

    /** ISO date YYYY-MM-DD (the UI shows DD/MM/YYYY but always sends ISO). */
    public function date(string $f, bool $required = true, ?string $notAfter = null): self
    {
        if ($this->missing($f)) {
            $required ? $this->errors[$f] = 'This field is required.' : $this->clean[$f] = null;
            return $this;
        }
        $v = (string) $this->raw($f);
        $d = \DateTimeImmutable::createFromFormat('!Y-m-d', $v);
        if ($d === false || $d->format('Y-m-d') !== $v) {
            $this->errors[$f] = 'Enter a valid date.';
        } elseif ($notAfter !== null && $v > $notAfter) {
            $this->errors[$f] = 'The date cannot be in the future.';
        } else {
            $this->clean[$f] = $v;
        }
        return $this;
    }

    /** @param list<string> $allowed */
    public function in(string $f, array $allowed, bool $required = true): self
    {
        if ($this->missing($f)) {
            $required ? $this->errors[$f] = 'Choose an option.' : $this->clean[$f] = null;
            return $this;
        }
        $v = (string) $this->raw($f);
        in_array($v, $allowed, true) ? $this->clean[$f] = $v : $this->errors[$f] = 'Choose one of the listed options.';
        return $this;
    }

    public function phone(string $f, bool $required = false): self
    {
        if ($this->missing($f)) {
            $required ? $this->errors[$f] = 'This field is required.' : $this->clean[$f] = null;
            return $this;
        }
        $v = preg_replace('/\s+/', '', (string) $this->raw($f)) ?? '';
        if (preg_match('/^0(\d{9})$/', $v, $m)) {
            $v = '+233' . $m[1];
        }
        preg_match('/^\+233\d{9}$/', $v) ? $this->clean[$f] = $v : $this->errors[$f] = 'Enter a Ghana number such as +233 24 123 4567.';
        return $this;
    }

    public function uuid(string $f, bool $required = false): self
    {
        if ($this->missing($f)) {
            $required ? $this->errors[$f] = 'This field is required.' : $this->clean[$f] = null;
            return $this;
        }
        $v = strtolower((string) $this->raw($f));
        preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/', $v) ? $this->clean[$f] = $v : $this->errors[$f] = 'Invalid reference.';
        return $this;
    }

    public function after(string $later, string $earlier, string $message): self
    {
        $a = $this->clean[$later] ?? null;
        $b = $this->clean[$earlier] ?? null;
        if ($a !== null && $b !== null && $a < $b) {
            $this->errors[$later] = $message;
        }
        return $this;
    }

    public function addError(string $f, string $message): self
    {
        $this->errors[$f] = $message;
        return $this;
    }

    /** @return array<string, mixed> */
    public function validated(): array
    {
        if ($this->errors) {
            throw HttpException::validation($this->errors);
        }
        return $this->clean;
    }
}
