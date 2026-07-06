<?php

namespace Freeline\Pdv\Services;

use Freeline\Pdv\Models\DigitalCertificate;
use Freeline\Pdv\Models\FiscalConfig;
use DOMDocument;
use DOMElement;
use RuntimeException;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Storage;

class PafXmlSigner
{
    private const MENU_NAMESPACE = 'http://www.sef.sc.gov.br/nfce';
    private const DS_NAMESPACE = 'http://www.w3.org/2000/09/xmldsig#';

    public function signFiscalFile(string $content, int $fileNumber, FiscalConfig $config): string
    {
        $doc = new DOMDocument('1.0', 'UTF-8');
        $doc->formatOutput = false;
        $doc->preserveWhiteSpace = false;

        $root = $doc->createElementNS(self::MENU_NAMESPACE, 'menuFiscal');
        $doc->appendChild($root);

        $arquivo = $doc->createElement('arquivo');
        $arquivo->setAttribute('nroArquivo', (string) $fileNumber);
        $arquivo->setAttribute('data', now()->format('dmY'));
        $arquivo->setAttribute('hora', now()->format('His'));
        $arquivo->setAttribute('arqBD', $this->architecture($config->paf_database_architecture, 'Banco de dados na nuvem', $config->paf_cloud_provider));
        $arquivo->setAttribute('arqSist', $this->architecture($config->paf_system_architecture, 'PAF-NFC-e Nuvem', $config->paf_cloud_provider));
        $arquivo->appendChild($doc->createCDATASection(base64_encode($content)));
        $root->appendChild($arquivo);

        $certificate = DigitalCertificate::query()->first();
        [$privateKey, $publicCertificate] = $this->readCertificate($certificate);
        $this->appendSignature($doc, $root, $privateKey, $publicCertificate);

        return $doc->saveXML() ?: '';
    }

    private function architecture(mixed $value, string $fallback, mixed $provider): string
    {
        $label = trim((string) ($value ?: $fallback));
        $provider = trim((string) $provider);

        if ($provider !== '' && str_contains(mb_strtolower($label), 'nuvem')) {
            return "{$label} - {$provider}";
        }

        return $label;
    }

    /**
     * @return array{0: mixed, 1: string}
     */
    private function readCertificate(?DigitalCertificate $certificate): array
    {
        if (! $certificate?->pfx_storage_path || ! $certificate->pfx_password_encrypted) {
            throw new RuntimeException('Configure um certificado A1 local para assinar o XML do Menu Fiscal.');
        }

        if (! Storage::disk('local')->exists($certificate->pfx_storage_path)) {
            throw new RuntimeException('Arquivo PFX do certificado A1 nao encontrado no storage privado.');
        }

        $pfx = Storage::disk('local')->get($certificate->pfx_storage_path);
        $password = Crypt::decryptString($certificate->pfx_password_encrypted);
        $certs = [];
        if (! openssl_pkcs12_read($pfx, $certs, $password)) {
            throw new RuntimeException('Nao foi possivel abrir o certificado A1 com a senha informada.');
        }

        if (empty($certs['pkey']) || empty($certs['cert'])) {
            throw new RuntimeException('O certificado A1 nao contem chave privada e certificado publico validos.');
        }

        return [$certs['pkey'], (string) $certs['cert']];
    }

    private function appendSignature(DOMDocument $doc, DOMElement $root, mixed $privateKey, string $certificate): void
    {
        $digestValue = base64_encode(hash('sha256', $root->C14N(false, false), true));

        $signature = $doc->createElementNS(self::DS_NAMESPACE, 'Signature');
        $signedInfo = $doc->createElementNS(self::DS_NAMESPACE, 'SignedInfo');
        $signature->appendChild($signedInfo);

        $canonicalization = $doc->createElementNS(self::DS_NAMESPACE, 'CanonicalizationMethod');
        $canonicalization->setAttribute('Algorithm', 'http://www.w3.org/TR/2001/REC-xml-c14n-20010315');
        $signedInfo->appendChild($canonicalization);

        $signatureMethod = $doc->createElementNS(self::DS_NAMESPACE, 'SignatureMethod');
        $signatureMethod->setAttribute('Algorithm', 'http://www.w3.org/2001/04/xmldsig-more#rsa-sha256');
        $signedInfo->appendChild($signatureMethod);

        $reference = $doc->createElementNS(self::DS_NAMESPACE, 'Reference');
        $reference->setAttribute('URI', '');
        $signedInfo->appendChild($reference);

        $transforms = $doc->createElementNS(self::DS_NAMESPACE, 'Transforms');
        $reference->appendChild($transforms);
        foreach ([
            'http://www.w3.org/2000/09/xmldsig#enveloped-signature',
            'http://www.w3.org/TR/2001/REC-xml-c14n-20010315',
        ] as $algorithm) {
            $transform = $doc->createElementNS(self::DS_NAMESPACE, 'Transform');
            $transform->setAttribute('Algorithm', $algorithm);
            $transforms->appendChild($transform);
        }

        $digestMethod = $doc->createElementNS(self::DS_NAMESPACE, 'DigestMethod');
        $digestMethod->setAttribute('Algorithm', 'http://www.w3.org/2001/04/xmlenc#sha256');
        $reference->appendChild($digestMethod);
        $reference->appendChild($doc->createElementNS(self::DS_NAMESPACE, 'DigestValue', $digestValue));

        $signatureBytes = '';
        if (! openssl_sign($signedInfo->C14N(false, false), $signatureBytes, $privateKey, OPENSSL_ALGO_SHA256)) {
            throw new RuntimeException('Nao foi possivel assinar o XML do Menu Fiscal.');
        }

        $signature->appendChild($doc->createElementNS(self::DS_NAMESPACE, 'SignatureValue', base64_encode($signatureBytes)));
        $keyInfo = $doc->createElementNS(self::DS_NAMESPACE, 'KeyInfo');
        $x509Data = $doc->createElementNS(self::DS_NAMESPACE, 'X509Data');
        $x509Data->appendChild($doc->createElementNS(self::DS_NAMESPACE, 'X509Certificate', $this->stripCertificate($certificate)));
        $keyInfo->appendChild($x509Data);
        $signature->appendChild($keyInfo);

        $root->appendChild($signature);
    }

    private function stripCertificate(string $certificate): string
    {
        return trim(str_replace([
            '-----BEGIN CERTIFICATE-----',
            '-----END CERTIFICATE-----',
            "\r",
            "\n",
            ' ',
        ], '', $certificate));
    }
}
