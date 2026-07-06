<?php

namespace Freeline\Pdv\Http\Controllers\Api;

use Freeline\Pdv\Http\Controllers\Controller;
use Freeline\Pdv\Services\PafMenuFiscalService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;
use Symfony\Component\HttpFoundation\Response;

class MenuFiscalController extends Controller
{
    public function __construct(private readonly PafMenuFiscalService $menuFiscal)
    {
    }

    public function identificacao(): JsonResponse
    {
        return response()->json($this->menuFiscal->identification());
    }

    public function arquivoI(Request $request): Response|JsonResponse
    {
        $filters = $request->validate([
            'date' => ['nullable', 'date'],
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date'],
            'stock_scope' => ['nullable', 'string', 'in:total,partial,none'],
            'product' => ['nullable', 'string', 'max:120'],
            'search' => ['nullable', 'string', 'max:120'],
        ]);

        return $this->xmlDownload(1, $this->menuFiscal->arquivoI($filters), 'menu-fiscal-arquivo-i.xml');
    }

    public function arquivoII(Request $request): Response|JsonResponse
    {
        $filters = $request->validate([
            'month' => ['nullable', 'integer', 'min:1', 'max:12'],
            'year' => ['nullable', 'integer', 'min:2000', 'max:2100'],
            'cpf_cnpj' => ['nullable', 'string', 'max:20'],
        ]);

        return $this->xmlDownload(2, $this->menuFiscal->arquivoII($filters), 'menu-fiscal-arquivo-ii.xml');
    }

    public function arquivoIII(Request $request): Response|JsonResponse
    {
        $filters = $request->validate([
            'date' => ['nullable', 'date'],
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date'],
        ]);

        return $this->xmlDownload(3, $this->menuFiscal->arquivoIII($filters), 'menu-fiscal-arquivo-iii.xml');
    }

    public function mesasAbertas(): JsonResponse
    {
        $items = $this->menuFiscal->mesasAbertas();

        return response()->json([
            'data' => $items,
            'items' => $items,
        ]);
    }

    public function arquivoIV(Request $request): Response|JsonResponse
    {
        $filters = $request->validate([
            'type' => ['nullable', 'string', 'in:open,without_dfe,all'],
        ]);

        return $this->xmlDownload(4, $this->menuFiscal->arquivoIV($filters), 'menu-fiscal-arquivo-iv.xml');
    }

    private function xmlDownload(int $fileNumber, string $content, string $filename): Response|JsonResponse
    {
        try {
            $xml = $this->menuFiscal->signedXml($fileNumber, $content);
        } catch (RuntimeException $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        }

        return response($xml, 200, [
            'Content-Type' => 'application/xml; charset=UTF-8',
            'Content-Disposition' => sprintf('attachment; filename="%s"', $filename),
        ]);
    }
}
