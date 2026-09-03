<?php
/**
 * VOIDBILL — lightweight JSON-file persistence. No database is needed for
 * a small single-user billing tool; a locked JSON file is enough to keep
 * invoice history and settings safe across requests.
 *
 * Reads and writes during a locked operation share a single file handle
 * (Windows enforces mandatory file locks, so a second handle to the same
 * file would deadlock against our own lock).
 */

declare(strict_types=1);

final class Storage
{
    public function __construct(private string $invoicesFile, private string $businessFile)
    {
    }

    /** @return array<int, array<string, mixed>> */
    public function allInvoices(): array
    {
        return $this->readJson($this->invoicesFile, []);
    }

    public function findInvoice(string $number): ?array
    {
        foreach ($this->allInvoices() as $invoice) {
            if (($invoice['number'] ?? null) === $number) {
                return $invoice;
            }
        }
        return null;
    }

    public function saveInvoice(array $invoice): void
    {
        $this->withLock($this->invoicesFile, function ($handle) use ($invoice) {
            $invoices = $this->readFromHandle($handle, []);
            $invoices[] = $invoice;
            $this->writeToHandle($handle, $invoices);
        });
    }

    public function updateInvoiceStatus(string $number, string $status): bool
    {
        $updated = false;
        $this->withLock($this->invoicesFile, function ($handle) use ($number, $status, &$updated) {
            $invoices = $this->readFromHandle($handle, []);
            foreach ($invoices as &$invoice) {
                if (($invoice['number'] ?? null) === $number) {
                    $invoice['status'] = $status;
                    $updated = true;
                    break;
                }
            }
            unset($invoice);
            if ($updated) {
                $this->writeToHandle($handle, $invoices);
            }
        });
        return $updated;
    }

    /** @return array<string, mixed> */
    public function getBusiness(array $defaults): array
    {
        $data = $this->readJson($this->businessFile, []);
        return array_merge($defaults, $data);
    }

    public function saveBusiness(array $business): void
    {
        $this->withLock($this->businessFile, function ($handle) use ($business) {
            $this->writeToHandle($handle, $business);
        });
    }

    private function readJson(string $file, array $default): array
    {
        if (!is_file($file)) {
            return $default;
        }
        $contents = file_get_contents($file);
        $data = $contents !== false && $contents !== '' ? json_decode($contents, true) : $default;
        return is_array($data) ? $data : $default;
    }

    /** @param resource $handle */
    private function readFromHandle($handle, array $default): array
    {
        rewind($handle);
        $contents = stream_get_contents($handle);
        $data = $contents !== false && $contents !== '' ? json_decode($contents, true) : $default;
        return is_array($data) ? $data : $default;
    }

    /** @param resource $handle */
    private function writeToHandle($handle, array $data): void
    {
        rewind($handle);
        ftruncate($handle, 0);
        fwrite($handle, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        fflush($handle);
    }

    private function withLock(string $file, callable $callback): void
    {
        $dir = dirname($file);
        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }
        if (!is_file($file)) {
            touch($file);
        }

        $handle = fopen($file, 'r+');
        if ($handle === false) {
            throw new RuntimeException("Unable to open $file.");
        }
        try {
            flock($handle, LOCK_EX);
            $callback($handle);
            flock($handle, LOCK_UN);
        } finally {
            fclose($handle);
        }
    }
}
