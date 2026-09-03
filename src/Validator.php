<?php
/**
 * VOIDBILL — server-side validation. JavaScript may hint at problems in the
 * browser, but nothing is trusted until it passes through here.
 */

declare(strict_types=1);

final class Validator
{
    /** @var array<string, string> */
    private array $errors = [];

    public function fail(string $field, string $message): void
    {
        if (!isset($this->errors[$field])) {
            $this->errors[$field] = $message;
        }
    }

    public function hasErrors(): bool
    {
        return count($this->errors) > 0;
    }

    /** @return array<string, string> */
    public function errors(): array
    {
        return $this->errors;
    }

    public function requireText(array $data, string $field, string $label, int $maxLength = 255): string
    {
        $value = trim((string)($data[$field] ?? ''));
        if ($value === '') {
            $this->fail($field, "$label is required.");
        } elseif (mb_strlen($value) > $maxLength) {
            $this->fail($field, "$label must be $maxLength characters or fewer.");
        }
        return $value;
    }

    public function optionalText(array $data, string $field, string $label, int $maxLength = 255): string
    {
        $value = trim((string)($data[$field] ?? ''));
        if ($value !== '' && mb_strlen($value) > $maxLength) {
            $this->fail($field, "$label must be $maxLength characters or fewer.");
        }
        return $value;
    }

    public function optionalEmail(array $data, string $field, string $label): string
    {
        $value = trim((string)($data[$field] ?? ''));
        if ($value !== '' && filter_var($value, FILTER_VALIDATE_EMAIL) === false) {
            $this->fail($field, "$label must be a valid email address.");
        }
        return $value;
    }

    public function requireDate(array $data, string $field, string $label): string
    {
        $value = trim((string)($data[$field] ?? ''));
        if ($value === '') {
            $this->fail($field, "$label is required.");
            return '';
        }
        $date = DateTime::createFromFormat('Y-m-d', $value);
        if (!$date || $date->format('Y-m-d') !== $value) {
            $this->fail($field, "$label must be a valid date.");
        }
        return $value;
    }

    /**
     * Validate a numeric field is present, finite, and within [$min, $max].
     */
    public function requireNumber(array $data, string $field, string $label, float $min, float $max): float
    {
        $raw = $data[$field] ?? '';
        if ($raw === '' || $raw === null || !is_numeric($raw)) {
            $this->fail($field, "$label must be a valid number.");
            return 0.0;
        }
        $value = (float)$raw;
        if (!is_finite($value)) {
            $this->fail($field, "$label is not a valid number.");
            return 0.0;
        }
        if ($value < $min) {
            $this->fail($field, "$label cannot be less than $min.");
        }
        if ($value > $max) {
            $this->fail($field, "$label is too large.");
        }
        return $value;
    }

    /**
     * Validate the full items array. Returns the cleaned list of items
     * (description, quantity, unit_price) that passed validation.
     *
     * @return array<int, array{description: string, quantity: float, unit_price: float}>
     */
    public function requireItems(array $rawItems, int $maxItems, float $maxQty, float $maxPrice): array
    {
        if (empty($rawItems) || !is_array($rawItems)) {
            $this->fail('items', 'Add at least one invoice item.');
            return [];
        }

        if (count($rawItems) > $maxItems) {
            $this->fail('items', "You can add at most $maxItems items.");
        }

        $clean = [];
        $index = 0;
        foreach ($rawItems as $item) {
            $index++;
            $description = trim((string)($item['description'] ?? ''));
            $quantityRaw = $item['quantity'] ?? '';
            $priceRaw = $item['unit_price'] ?? '';

            // Skip fully-empty rows (the UI may submit a trailing blank row).
            if ($description === '' && $quantityRaw === '' && $priceRaw === '') {
                continue;
            }

            if ($description === '') {
                $this->fail("items.$index.description", "Item #$index needs a description.");
            } elseif (mb_strlen($description) > 200) {
                $this->fail("items.$index.description", "Item #$index description is too long.");
            }

            if ($quantityRaw === '' || !is_numeric($quantityRaw)) {
                $this->fail("items.$index.quantity", "Item #$index quantity must be a number.");
                $quantity = 0.0;
            } else {
                $quantity = (float)$quantityRaw;
                if ($quantity <= 0) {
                    $this->fail("items.$index.quantity", "Item #$index quantity must be greater than zero.");
                } elseif ($quantity > $maxQty) {
                    $this->fail("items.$index.quantity", "Item #$index quantity is too large.");
                }
            }

            if ($priceRaw === '' || !is_numeric($priceRaw)) {
                $this->fail("items.$index.unit_price", "Item #$index price must be a number.");
                $price = 0.0;
            } else {
                $price = (float)$priceRaw;
                if ($price < 0) {
                    $this->fail("items.$index.unit_price", "Item #$index price cannot be negative.");
                } elseif ($price > $maxPrice) {
                    $this->fail("items.$index.unit_price", "Item #$index price is too large.");
                }
            }

            $clean[] = [
                'description' => $description,
                'quantity' => $quantity,
                'unit_price' => $price,
            ];
        }

        if (empty($clean) && !$this->hasErrors()) {
            $this->fail('items', 'Add at least one invoice item.');
        }

        return $clean;
    }
}
