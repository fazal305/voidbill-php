<?php
/**
 * VOIDBILL — sequential invoice numbering (e.g. INV-2026-0001).
 *
 * Numbers persist in storage/counter.json so refreshing the app or
 * generating multiple invoices never reuses or skips a number. The
 * sequence resets per calendar year.
 */

declare(strict_types=1);

final class InvoiceNumberGenerator
{
    public function __construct(private string $counterFile, private string $prefix = 'INV')
    {
    }

    /**
     * Atomically reserve and return the next invoice number for the
     * current year, persisting the updated counter to disk.
     */
    public function next(): string
    {
        $dir = dirname($this->counterFile);
        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }

        $handle = fopen($this->counterFile, 'c+');
        if ($handle === false) {
            throw new RuntimeException('Unable to open invoice counter file.');
        }

        try {
            flock($handle, LOCK_EX);

            $contents = stream_get_contents($handle);
            $data = $contents !== false && $contents !== '' ? json_decode($contents, true) : [];
            if (!is_array($data)) {
                $data = [];
            }

            $year = date('Y');
            $sequence = (int)($data[$year] ?? 0) + 1;
            $data[$year] = $sequence;

            ftruncate($handle, 0);
            rewind($handle);
            fwrite($handle, json_encode($data, JSON_PRETTY_PRINT));
            fflush($handle);

            flock($handle, LOCK_UN);
        } finally {
            fclose($handle);
        }

        return sprintf('%s-%s-%04d', $this->prefix, $year, $sequence);
    }
}
