<?php
declare(strict_types=1);

namespace App\Services\FatturaPA;

use App\Support\Config;
use RuntimeException;

/**
 * Signs a FatturaPA XML into a CMS/PKCS#7 (.p7m) envelope with the firm's
 * certificate, producing the DER-encoded, content-encapsulated file the SdI
 * expects (`{filename}.xml.p7m`).
 *
 * NOTE: full SdI acceptance requires a *qualified* certificate and a CAdES-BES
 * signature. This produces a valid CMS signature with the qualified cert; where a
 * provider signs on their side, generating the plain XML (FatturaPaBuilder) is
 * enough and signing here can stay disabled. Configure via EINVOICE_* — see
 * docs/CONFIGURATION.md. Disabled by default.
 */
final class CadesSigner
{
    private string $certPath;
    private string $keyPath;
    private string $keyPass;

    public function __construct(?string $certPath = null, ?string $keyPath = null, ?string $keyPass = null)
    {
        $this->certPath = $certPath ?? (string) Config::get('einvoice.cert_path', '');
        $this->keyPath  = $keyPath ?? (string) Config::get('einvoice.key_path', '');
        $this->keyPass  = $keyPass ?? (string) Config::get('einvoice.key_pass', '');
    }

    /** True when signing is enabled and the certificate + key files are readable. */
    public function isConfigured(): bool
    {
        return (bool) Config::get('einvoice.sign', false)
            && $this->certPath !== '' && is_file($this->certPath)
            && $this->keyPath !== '' && is_file($this->keyPath);
    }

    /** Whether the signer has usable material regardless of the enable flag (for tests/direct use). */
    public function hasMaterial(): bool
    {
        return $this->certPath !== '' && is_file($this->certPath)
            && $this->keyPath !== '' && is_file($this->keyPath);
    }

    /**
     * Sign the XML and return the DER-encoded .p7m bytes.
     *
     * @throws RuntimeException if the certificate/key is unusable or signing fails.
     */
    public function sign(string $xml): string
    {
        if (!$this->hasMaterial()) {
            throw new RuntimeException('Certificato di firma non configurato.');
        }

        $in  = tempnam(sys_get_temp_dir(), 'gm_xml_');
        $out = tempnam(sys_get_temp_dir(), 'gm_p7m_');
        if ($in === false || $out === false) {
            throw new RuntimeException('Impossibile creare i file temporanei per la firma.');
        }

        try {
            file_put_contents($in, $xml);

            $key = $this->keyPass !== ''
                ? ['file://' . $this->keyPath, $this->keyPass]
                : 'file://' . $this->keyPath;

            $ok = openssl_cms_sign(
                $in,
                $out,
                'file://' . $this->certPath,
                $key,
                [],
                OPENSSL_CMS_BINARY,
                OPENSSL_ENCODING_DER
            );
            if (!$ok) {
                throw new RuntimeException('Firma CMS non riuscita: ' . self::opensslErrors());
            }

            $bytes = (string) file_get_contents($out);
            if ($bytes === '') {
                throw new RuntimeException('Firma CMS: output vuoto.');
            }
            return $bytes;
        } finally {
            @unlink($in);
            @unlink($out);
        }
    }

    private static function opensslErrors(): string
    {
        $msgs = [];
        while (($e = openssl_error_string()) !== false) {
            $msgs[] = $e;
        }
        return implode('; ', $msgs) ?: 'errore sconosciuto';
    }
}
