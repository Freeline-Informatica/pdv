<?php

namespace Freeline\Pdv\Http\Controllers\Api;

use Freeline\Pdv\Http\Controllers\Controller;
use Freeline\Pdv\Models\FiscalConfig;
use Freeline\Pdv\Models\SaleFiscalDocument;
use Freeline\Pdv\Services\NotaAgilFiscalService;
use Illuminate\Support\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use NotaAgil\Integration\NotaAgilClient;

class NotagilWebhookController extends Controller
{
    public function __construct(private readonly NotaAgilFiscalService $notaAgil)
    {
    }

    public function __invoke(Request $request): JsonResponse
    {
        $secrets = $this->resolveWebhookSecrets();

        if ($secrets === []) {
            return response()->json(['message' => 'Webhook NotaAgil não configurado.'], 503);
        }

        if (! $this->hasValidAuthentication($request, $secrets)) {
            $this->logAuthenticationFailure($request, count($secrets));

            return response()->json(['message' => 'Webhook NotaAgil não autorizado.'], 401);
        }

        $payload = $request->json()->all();

        if (! is_array($payload) || $payload === []) {
            return response()->json(['message' => 'Payload do webhook inválido.'], 422);
        }

        if ((string) data_get($payload, 'type') === 'webhook.test') {
            return response()->json([
                'message' => 'Webhook NotaAgil testado.',
                'event' => 'webhook.test',
            ], 202);
        }

        $document = $this->notaAgil->applyWebhookPayload($payload, debug: [
            'webhook_source' => 'http',
            'webhook_id' => data_get($payload, 'id'),
            'webhook_type' => data_get($payload, 'type'),
            'webhook_event' => $request->headers->get('X-NotaAgil-Event') ?: data_get($payload, 'type') ?: data_get($payload, 'event'),
            'webhook_delivery' => $request->headers->get('X-NotaAgil-Delivery'),
            'webhook_timestamp' => $request->headers->get('X-NotaAgil-Timestamp'),
        ]);

        if (! $document) {
            return response()->json([
                'message' => 'Documento fiscal não encontrado para este webhook.',
            ], 404);
        }

        return response()->json([
            'message' => 'Webhook NotaAgil processado.',
            'fiscal' => [
                'id' => $document->id,
                'sale_id' => $document->sale_id,
                'external_id' => $document->external_id,
                'status' => $document->status,
                'authorized' => $document->status === SaleFiscalDocument::STATUS_AUTHORIZED,
            ],
        ], 202);
    }

    private function hasValidAuthentication(Request $request, array $secrets): bool
    {
        foreach ($this->tokenCandidates($request) as $candidate) {
            foreach ($secrets as $secret) {
                if (hash_equals($secret, $candidate)) {
                    return true;
                }
            }
        }

        $signatures = $this->signatureHeaderValues((string) $request->headers->get('X-NotaAgil-Signature', ''));
        if ($signatures === []) {
            return false;
        }

        $timestamp = trim((string) $request->headers->get('X-NotaAgil-Timestamp', ''));
        if ($timestamp !== '' && ! $this->hasFreshTimestamp($timestamp)) {
            return false;
        }

        $deliveryId = trim((string) $request->headers->get('X-NotaAgil-Delivery', ''));
        $bodyCandidates = $this->bodyCandidates($request);

        foreach ($secrets as $secret) {
            foreach ($bodyCandidates as $body) {
                foreach ($this->signatureCandidates($body, $secret, $timestamp, $deliveryId) as $candidate) {
                    foreach ($signatures as $signature) {
                        if ($this->signatureMatches($candidate, $signature)) {
                            return true;
                        }
                    }
                }
            }
        }

        return false;
    }

    private function tokenCandidates(Request $request): array
    {
        $authorization = trim((string) $request->headers->get('Authorization', ''));
        $bearer = preg_match('/^Bearer\s+(.+)$/i', $authorization, $matches)
            ? trim($matches[1])
            : '';

        return array_values(array_filter([
            trim((string) $request->headers->get('X-NotaAgil-Webhook-Token', '')),
            trim((string) $request->headers->get('X-Webhook-Token', '')),
            $bearer,
        ], static fn (string $value): bool => $value !== ''));
    }

    private function hasFreshTimestamp(string $timestamp): bool
    {
        $tolerance = max(0, $this->resolveWebhookToleranceSeconds());
        if ($tolerance === 0) {
            return true;
        }

        if (! ctype_digit($timestamp)) {
            return false;
        }

        $epoch = (int) $timestamp;
        if (strlen($timestamp) >= 13) {
            $epoch = (int) floor($epoch / 1000);
        }

        return abs(Carbon::createFromTimestamp($epoch)->diffInSeconds(now(), false)) <= $tolerance;
    }

    private function signatureCandidates(string $body, string $secret, string $timestamp, string $deliveryId): array
    {
        $payloads = [$body];

        if ($deliveryId !== '' && $timestamp !== '') {
            $payloads[] = $deliveryId.'.'.$timestamp.'.'.$body;
            $payloads[] = $timestamp.'.'.$deliveryId.'.'.$body;
            $payloads[] = $deliveryId.'.'.$body;
        }

        if ($timestamp !== '') {
            $payloads[] = $timestamp.'.'.$body;
        }

        $candidates = [];
        foreach (array_values(array_unique($payloads)) as $payload) {
            $hex = $payload === $deliveryId.'.'.$timestamp.'.'.$body
                ? NotaAgilClient::webhookSignature($secret, $deliveryId, $timestamp, $body)
                : hash_hmac('sha256', $payload, $secret);
            $base64 = base64_encode(hash_hmac('sha256', $payload, $secret, true));

            array_push($candidates, $hex, 'sha256='.$hex, $base64, 'sha256='.$base64);
        }

        return array_values(array_unique($candidates));
    }

    private function signatureMatches(string $candidate, string $signature): bool
    {
        if (hash_equals($candidate, $signature)) {
            return true;
        }

        if ($this->isHexSignature($candidate) && $this->isHexSignature($signature)) {
            return hash_equals(strtolower($candidate), strtolower($signature));
        }

        return false;
    }

    private function isHexSignature(string $value): bool
    {
        $value = str_starts_with(strtolower($value), 'sha256=')
            ? substr($value, 7)
            : $value;

        return preg_match('/^[a-f0-9]{64}$/i', $value) === 1;
    }

    private function signatureHeaderValues(string $signature): array
    {
        $values = [];
        foreach (explode(',', $signature) as $part) {
            $part = trim($part);
            if ($part === '') {
                continue;
            }

            $values[] = $part;
            if (str_contains($part, '=')) {
                $values[] = trim((string) substr($part, strpos($part, '=') + 1));
            }
        }

        return array_values(array_unique(array_filter($values, static fn (string $value): bool => $value !== '')));
    }

    private function bodyCandidates(Request $request): array
    {
        $raw = $request->getContent();
        $candidates = [$raw];
        $decoded = json_decode($raw, true);

        if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
            foreach ([0, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE, JSON_UNESCAPED_SLASHES, JSON_UNESCAPED_UNICODE] as $flags) {
                $encoded = json_encode($decoded, $flags);
                if (is_string($encoded)) {
                    $candidates[] = $encoded;
                }
            }
        }

        return array_values(array_unique($candidates));
    }

    private function logAuthenticationFailure(Request $request, int $secretCount): void
    {
        Log::warning('NotaAgil webhook authentication failed', [
            'event' => $request->headers->get('X-NotaAgil-Event'),
            'delivery' => $request->headers->get('X-NotaAgil-Delivery'),
            'timestamp' => $request->headers->get('X-NotaAgil-Timestamp'),
            'has_signature' => $request->headers->has('X-NotaAgil-Signature'),
            'body_sha256' => hash('sha256', $request->getContent()),
            'body_length' => strlen($request->getContent()),
            'secret_count' => $secretCount,
        ]);
    }

    private function resolveWebhookSecrets(): array
    {
        $dbSecret = trim((string) (FiscalConfig::query()->value('notagil_webhook_secret') ?? ''));
        $envSecret = trim((string) config('pdv.notagil.webhook_secret'));

        return array_values(array_unique(array_filter([
            $dbSecret,
            $envSecret,
        ], static fn (string $value): bool => $value !== '')));
    }

    private function resolveWebhookToleranceSeconds(): int
    {
        $dbTolerance = FiscalConfig::query()->value('notagil_webhook_tolerance_seconds');

        return $dbTolerance !== null
            ? (int) $dbTolerance
            : (int) config('pdv.notagil.webhook_tolerance_seconds', 300);
    }
}
