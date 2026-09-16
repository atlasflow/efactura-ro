<?php

declare(strict_types=1);

namespace AtlasFlow\EFacturaRo\Anaf;

use AtlasFlow\EFacturaRo\Anaf\Exceptions\UnexpectedResponse;
use ZipArchive;

/**
 * What /descarcare returns, unpacked: two XML files — the payload (the
 * invoice, the error list, or a RASP message) and MF's detached signature
 * over it (`semnatura_<id>.xml`). The pair is the legal original; keep both.
 */
final readonly class SignedBundle
{
    public function __construct(
        public BundleKind $kind,
        public string $payloadXml,
        public string $signatureXml,
        public string $payloadFilename,
        public string $signatureFilename,
    ) {}

    public static function fromZip(string $bytes): self
    {
        $path = tempnam(sys_get_temp_dir(), 'efactura-');

        if ($path === false) {
            throw new UnexpectedResponse('Cannot create a temporary file to unpack the ZIP.');
        }

        try {
            file_put_contents($path, $bytes);
            $zip = new ZipArchive;

            if ($zip->open($path) !== true) {
                throw new UnexpectedResponse('The download is not a ZIP archive.');
            }

            $files = [];

            for ($i = 0; $i < $zip->numFiles; $i++) {
                $name = (string) $zip->getNameIndex($i);
                $files[$name] = (string) $zip->getFromIndex($i);
            }

            $zip->close();
        } finally {
            @unlink($path);
        }

        $signatureName = null;

        foreach (array_keys($files) as $name) {
            if (str_starts_with(strtolower(basename($name)), 'semnatura')) {
                $signatureName = $name;
            }
        }

        if ($signatureName === null || count($files) < 2) {
            throw new UnexpectedResponse(sprintf('Expected a payload and a semnatura_*.xml in the ZIP, found: %s.', implode(', ', array_keys($files)) ?: 'nothing'));
        }

        $payloadName = array_values(array_filter(array_keys($files), fn ($n) => $n !== $signatureName))[0];
        $payload = $files[$payloadName];

        return new self(self::kindOf($payload), $payload, $files[$signatureName], $payloadName, $signatureName);
    }

    /** Everything a caller needs to re-check the bundle later through AnafClient::verifySignature(). */
    public function isInvoice(): bool
    {
        return $this->kind === BundleKind::INVOICE;
    }

    private static function kindOf(string $xml): BundleKind
    {
        $head = substr($xml, 0, 4096);

        if (preg_match('/<(\w+:)?(Invoice|CreditNote)[\s>]/', $head)) {
            return BundleKind::INVOICE;
        }

        if (preg_match('/<(\w+:)?header[\s>]/i', $head) || str_contains($head, 'Error')) {
            return BundleKind::ERRORS;
        }

        return BundleKind::RASP;
    }
}
