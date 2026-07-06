<?php

namespace Freeline\Pdv\Services;

use Freeline\Pdv\Models\CompanySetting;
use Freeline\Pdv\Models\FiscalConfig;
use Freeline\Pdv\Models\PafDav;
use Freeline\Pdv\Models\PafExternalRequisition;
use Freeline\Pdv\Models\PafPreSale;
use Freeline\Pdv\Models\Produto;
use Freeline\Pdv\Models\RestaurantConferenceReport;
use Freeline\Pdv\Models\RestaurantFicha;
use Freeline\Pdv\Models\Sale;
use Freeline\Pdv\Models\SaleFiscalDocument;
use Carbon\CarbonImmutable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class PafMenuFiscalService
{
    private const EMPTY_MESSAGE = 'Nao ha dados disponiveis para geracao do arquivo eletronico solicitado.';

    public function __construct(
        private readonly PafFixedWidth $fw,
        private readonly PafXmlSigner $xmlSigner,
    ) {
    }

    public function identification(): array
    {
        $config = $this->fiscalConfig();

        return [
            'paf_enabled' => (bool) ($config?->paf_enabled ?? false),
            'paf_app_name' => $this->appName($config),
            'paf_app_version' => $this->appVersion($config),
            'paf_database_architecture' => $this->architecture($config?->paf_database_architecture, 'Banco de dados na nuvem', $config?->paf_cloud_provider),
            'paf_system_architecture' => $this->architecture($config?->paf_system_architecture, 'PAF-NFC-e Nuvem', $config?->paf_cloud_provider),
            'paf_cloud_provider' => (string) ($config?->paf_cloud_provider ?: ''),
            'developer' => [
                'cnpj' => $this->digitsOnly($config?->paf_developer_cnpj),
                'razao_social' => (string) ($config?->paf_developer_razao_social ?: ''),
                'endereco' => (string) ($config?->paf_developer_endereco ?: ''),
                'telefone' => (string) ($config?->paf_developer_telefone ?: ''),
                'contato' => (string) ($config?->paf_developer_contato ?: ''),
            ],
            'application' => [
                'nome' => $this->appName($config),
                'versao' => $this->appVersion($config),
            ],
            'architecture' => [
                'database' => $this->architecture($config?->paf_database_architecture, 'Banco de dados na nuvem', $config?->paf_cloud_provider),
                'system' => $this->architecture($config?->paf_system_architecture, 'PAF-NFC-e Nuvem', $config?->paf_cloud_provider),
            ],
            'fuel_module_enabled' => (bool) ($config?->paf_fuel_module_enabled ?? false),
        ];
    }

    public function signedXml(int $fileNumber, string $content): string
    {
        return $this->xmlSigner->signFiscalFile($content, $fileNumber, $this->fiscalConfig());
    }

    public function arquivoI(array $filters = []): string
    {
        $company = $this->company();
        $config = $this->fiscalConfig();
        $range = $this->dateRange($filters);
        $lines = [$this->userRecord('U1', $company)];
        $dataRows = 0;

        foreach ($this->dailyPaymentRows($range[0], $range[1]) as $row) {
            $lines[] = $this->fw->line(
                $this->fw->text('A2', 2),
                $this->fw->date($row['date']),
                $this->fw->text($row['method'], 25),
                $this->fw->text($row['document_type'], 1),
                $this->fw->number($row['amount'], 12, 2),
                $this->fw->digits($row['customer_document'] ?? '', 14),
                $this->fw->digits($row['non_tax_document_number'] ?? '', 10),
            );
            $dataRows++;
        }

        foreach ($this->productRows($filters) as $product) {
            $lines[] = $this->productRecord('P2', $company, $product);
            $dataRows++;
        }

        if (($filters['stock_scope'] ?? 'total') !== 'none') {
            foreach ($this->productRows($filters) as $product) {
                $lines[] = $this->stockRecord($company, $product);
                $dataRows++;
            }
        }

        foreach ($this->davRows($range[0], $range[1]) as $dav) {
            $lines[] = $this->davRecord($company, $dav);
            $dataRows++;
            foreach ($dav->items as $item) {
                $lines[] = $this->davItemRecord($item);
                $dataRows++;
            }
            foreach ($dav->itemLogs as $log) {
                $lines[] = $this->davItemLogRecord($log);
                $dataRows++;
            }
        }

        foreach ($this->openFichaRows() as $ficha) {
            $summary = $this->fichaSummary($ficha);
            $lines[] = $this->openMesaRecord($company, $ficha, $summary);
            $dataRows++;
            foreach ($summary['items'] as $item) {
                $lines[] = $this->openMesaItemRecord($company, $ficha, $item);
                $dataRows++;
            }
        }

        foreach ($this->nfceRows($range[0], $range[1]) as $sale) {
            $lines[] = $this->nfceRecord($company, $sale);
            $dataRows++;
            if ($this->isContingencySale($sale)) {
                foreach ($sale->items as $index => $item) {
                    $lines[] = $this->contingencyItemRecord($company, $sale, $item, $index + 1);
                    $dataRows++;
                }
            }
        }

        return $this->textFile($lines, $dataRows);
    }

    public function arquivoII(array $filters = []): string
    {
        $company = $this->company();
        $config = $this->fiscalConfig();
        $month = max(1, min(12, (int) ($filters['month'] ?? now()->month)));
        $year = max(2000, min(2100, (int) ($filters['year'] ?? now()->year)));
        $start = CarbonImmutable::create($year, $month, 1)->startOfDay();
        $end = $start->endOfMonth()->endOfDay();
        $documentFilter = $this->digitsOnly($filters['cpf_cnpj'] ?? null);

        $groups = [];
        foreach ($this->nfceRows($start, $end) as $sale) {
            $document = $this->saleCustomerDocument($sale);
            if ($document === '' || ($documentFilter !== '' && $document !== $documentFilter)) {
                continue;
            }
            $groups[$document] ??= ['total' => 0.0, 'sales' => 0.0, 'other' => 0.0];
            $groups[$document]['total'] += (float) $sale->total_financeiro;
            $groups[$document]['sales'] += (float) $sale->total_financeiro;
        }

        ksort($groups);
        $lines = [
            $this->userRecord('Z1', $company),
            $this->developerRecord('Z2', $config),
            $this->appRecord('Z3', $config),
        ];
        foreach ($groups as $document => $totals) {
            $lines[] = $this->fw->line(
                $this->fw->text('Z4', 2),
                $this->fw->digits($document, 14),
                $this->fw->number($totals['total'], 14, 2),
                $this->fw->number($totals['sales'], 14, 2),
                $this->fw->number($totals['other'], 14, 2),
                $this->fw->date($start),
                $this->fw->date($end),
                $this->fw->date(now()),
                $this->fw->time(now()),
            );
        }
        $lines[] = $this->fw->line(
            $this->fw->text('Z9', 2),
            $this->fw->digits($config?->paf_developer_cnpj, 14),
            $this->fw->text($this->digitsOnly($config?->paf_developer_ie), 14),
            $this->fw->number(count($groups), 6),
        );

        return $this->textFile($lines, count($groups));
    }

    public function arquivoIII(array $filters = []): string
    {
        $company = $this->company();
        $config = $this->fiscalConfig();
        $range = $this->dateRange($filters);
        $requisitions = PafExternalRequisition::query()
            ->whereBetween('created_at', [$range[0], $range[1]])
            ->orderBy('cre')
            ->get();

        $lines = [
            $this->userRecord('W1', $company),
            $this->developerRecord('W2', $config),
            $this->appRecord('W3', $config),
        ];
        foreach ($requisitions as $requisition) {
            $lines[] = $this->fw->line(
                $this->fw->text($requisition->origin ?: 'OUTROS', 20, true),
                $this->fw->text($requisition->status ?: 'R', 1, true),
                $this->fw->digits($requisition->cre, 9),
                $this->fw->digits($this->relatedDavNumber($requisition), 13),
                $this->fw->digits($this->relatedPreSaleCode($requisition), 10),
                $this->fw->text($requisition->external_order_id, 40),
                $this->fw->number($requisition->total, 14, 2),
            );
            $lines[array_key_last($lines)] = 'W4'.$lines[array_key_last($lines)];
        }
        $lines[] = $this->fw->line(
            $this->fw->text('W5', 2),
            $this->fw->digits($config?->paf_developer_cnpj, 14),
            $this->fw->text($this->digitsOnly($config?->paf_developer_ie), 14),
            $this->fw->number($requisitions->count(), 6),
        );

        return $this->textFile($lines, $requisitions->count());
    }

    public function arquivoIV(array $filters = []): string
    {
        $company = $this->company();
        $query = PafDav::query()->orderBy('number');
        if (($filters['type'] ?? null) === 'open') {
            $query->whereNull('converted_sale_id')->where('status', PafDav::STATUS_OPEN);
        } elseif (($filters['type'] ?? null) === 'without_dfe') {
            $query->whereNull('converted_sale_id');
        }

        $davs = $query->get();
        $lines = [$this->userRecord('V1', $company)];
        foreach ($davs as $dav) {
            $lines[] = $this->fw->line(
                $this->fw->text($dav->converted_sale_id ? 'V3' : 'V2', 2),
                $this->fw->date($dav->issued_at ?: $dav->created_at),
                $this->fw->digits($dav->number, 13),
            );
        }
        $lines[] = $this->fw->line($this->fw->text('V4', 2), $this->fw->date(now()));

        return $this->textFile($lines, $davs->count());
    }

    public function mesasAbertas(): array
    {
        return $this->openFichaRows()
            ->map(function (RestaurantFicha $ficha): array {
                $summary = $this->fichaSummary($ficha);

                return [
                    'id' => $ficha->id,
                    'codigo' => $ficha->code,
                    'ficha_code' => $ficha->code,
                    'mesa' => $ficha->table?->code,
                    'table_code' => $ficha->table?->code,
                    'cliente' => $ficha->customer_name,
                    'aberta_em' => $ficha->opened_at?->toIso8601String(),
                    'opened_at' => $ficha->opened_at?->toIso8601String(),
                    'total' => $summary['total'],
                    'itens' => $summary['items'],
                    'items' => $summary['items'],
                ];
            })
            ->values()
            ->all();
    }

    private function textFile(array $lines, int $dataRows): string
    {
        if ($dataRows <= 0) {
            return self::EMPTY_MESSAGE."\r\n";
        }

        return implode("\r\n", $lines)."\r\n";
    }

    private function fiscalConfig(): FiscalConfig
    {
        return FiscalConfig::query()->first() ?? new FiscalConfig([
            'paf_enabled' => false,
            'paf_app_name' => config('app.name', 'Freeline PDV'),
            'paf_app_version' => '1.0.0',
            'paf_database_architecture' => 'Banco de dados na nuvem',
            'paf_system_architecture' => 'PAF-NFC-e Nuvem',
        ]);
    }

    private function company(): ?CompanySetting
    {
        return CompanySetting::query()->first();
    }

    private function userRecord(string $type, ?CompanySetting $company): string
    {
        return $this->fw->line(
            $this->fw->text($type, 2),
            $this->fw->digits($company?->cnpj, 14),
            $this->fw->text($this->digitsOnly($company?->inscricao_estadual), 14, true),
            $this->fw->text($this->digitsOnly($company?->inscricao_municipal), 14, true),
            $this->fw->text($company?->razao_social, 50, true),
        );
    }

    private function developerRecord(string $type, FiscalConfig $config): string
    {
        return $this->fw->line(
            $this->fw->text($type, 2),
            $this->fw->digits($config->paf_developer_cnpj, 14),
            $this->fw->text($this->digitsOnly($config->paf_developer_ie), 14, true),
            $this->fw->text($this->digitsOnly($config->paf_developer_im), 14, true),
            $this->fw->text($config->paf_developer_razao_social, 50, true),
        );
    }

    private function appRecord(string $type, FiscalConfig $config): string
    {
        return $this->fw->line(
            $this->fw->text($type, 2),
            $this->fw->text($this->appName($config), 50, true),
            $this->fw->text($this->appVersion($config), 10),
        );
    }

    private function productRecord(string $type, ?CompanySetting $company, Produto $product): string
    {
        [$situation, $rate] = $this->taxSituation($product);

        return $this->fw->line(
            $this->fw->text($type, 2),
            $this->fw->digits($company?->cnpj, 14),
            $this->fw->text($this->productCode($product), 14),
            $this->fw->text($this->digitsOnly($product->cest), 7),
            $this->fw->text($this->digitsOnly($product->ncm), 8),
            $this->fw->text($product->descricao ?: $product->descricao_curta, 50),
            $this->fw->text($this->unit($product), 6),
            $this->fw->text($this->iat($product), 1),
            $this->fw->text($this->ippt($product), 1),
            $this->fw->text($situation, 1),
            $this->fw->number($rate, 4, 2),
            $this->fw->number($this->referencePrice($product), 14, 2),
        );
    }

    private function stockRecord(?CompanySetting $company, Produto $product): string
    {
        $quantity = (float) ($product->estoque?->quantidade ?? 0);

        return $this->fw->line(
            $this->fw->text('E2', 2),
            $this->fw->digits($company?->cnpj, 14),
            $this->fw->text($this->productCode($product), 14),
            $this->fw->text($this->digitsOnly($product->cest), 7),
            $this->fw->text($this->digitsOnly($product->ncm), 8),
            $this->fw->text($product->descricao ?: $product->descricao_curta, 50),
            $this->fw->text($this->unit($product), 6),
            $this->fw->text($quantity < 0 ? '-' : '+', 1),
            $this->fw->number(abs($quantity), 9, 3),
            $this->fw->date(now()),
            $this->fw->date(now()),
        );
    }

    private function davRecord(?CompanySetting $company, PafDav $dav): string
    {
        return $this->fw->line(
            $this->fw->text('D2', 2),
            $this->fw->digits($company?->cnpj, 14),
            $this->fw->text($dav->number, 13),
            $this->fw->date($dav->issued_at ?: $dav->created_at),
            $this->fw->text($dav->title, 30),
            $this->fw->number($dav->total, 8, 2),
            $this->fw->text($dav->customer_name, 40),
            $this->fw->digits($dav->customer_document, 14),
        );
    }

    private function davItemRecord($item): string
    {
        return $this->fw->line(
            $this->fw->text('D3', 2),
            $this->fw->text($item->dav?->number, 13),
            $this->fw->date($item->included_at ?: $item->created_at),
            $this->fw->number($item->item_number, 3),
            $this->fw->text($item->product_code, 14),
            $this->fw->text($item->description, 100),
            $this->fw->number($item->quantity, 7, (int) $item->quantity_decimals),
            $this->fw->text($item->unit, 3),
            $this->fw->number($item->unit_price, 14, (int) $item->unit_price_decimals),
            $this->fw->text($item->tax_situation ?: 'T', 1),
            $this->fw->number($item->tax_rate, 4, 2),
            $this->fw->text($item->canceled ? 'S' : 'N', 1),
            $this->fw->number($item->quantity_decimals, 1),
            $this->fw->number($item->unit_price_decimals, 1),
        );
    }

    private function davItemLogRecord($log): string
    {
        return $this->fw->line(
            $this->fw->text('D4', 2),
            $this->fw->text($log->dav?->number, 13),
            $this->fw->date($log->changed_at ?: $log->created_at),
            $this->fw->time($log->changed_at ?: $log->created_at),
            $this->fw->text($log->product_code, 14),
            $this->fw->text($log->description, 100),
            $this->fw->number($log->quantity, 7, (int) $log->quantity_decimals),
            $this->fw->text($log->unit, 3),
            $this->fw->number($log->unit_price, 14, (int) $log->unit_price_decimals),
            $this->fw->text($log->tax_situation ?: 'T', 1),
            $this->fw->number($log->tax_rate, 4, 2),
            $this->fw->text($log->canceled ? 'S' : 'N', 1),
            $this->fw->number($log->quantity_decimals, 1),
            $this->fw->number($log->unit_price_decimals, 1),
            $this->fw->text($log->change_type ?: 'I', 1),
        );
    }

    private function openMesaRecord(?CompanySetting $company, RestaurantFicha $ficha, array $summary): string
    {
        return $this->fw->line(
            $this->fw->text('S2', 2),
            $this->fw->digits($company?->cnpj, 14),
            $this->fw->date($ficha->opened_at ?: $ficha->created_at),
            $this->fw->time($ficha->opened_at ?: $ficha->created_at),
            $this->fw->text($ficha->table?->code ?: $ficha->code, 13),
            $this->fw->number($summary['total'], 13, 2),
            $this->fw->text($summary['conference_number'] ?? '', 9),
        );
    }

    private function openMesaItemRecord(?CompanySetting $company, RestaurantFicha $ficha, array $item): string
    {
        return $this->fw->line(
            $this->fw->text('S3', 2),
            $this->fw->digits($company?->cnpj, 14),
            $this->fw->date($ficha->opened_at ?: $ficha->created_at),
            $this->fw->time($ficha->opened_at ?: $ficha->created_at),
            $this->fw->text($ficha->table?->code ?: $ficha->code, 13),
            $this->fw->text($item['code'], 14),
            $this->fw->text($item['description'], 100),
            $this->fw->number($item['quantity'], 7, 3),
            $this->fw->text($item['unit'], 3),
            $this->fw->number($item['unit_price'], 8, 2),
            $this->fw->number(3, 1),
            $this->fw->number(2, 1),
        );
    }

    private function nfceRecord(?CompanySetting $company, Sale $sale): string
    {
        $document = $sale->fiscalDocument;

        return $this->fw->line(
            $this->fw->text('J1', 2),
            $this->fw->digits($company?->cnpj, 14),
            $this->fw->date($sale->sold_at ?: $sale->created_at),
            $this->fw->number($sale->subtotal ?: $sale->total_bruto, 14, 2),
            $this->fw->number($sale->total_descontos ?? 0, 13, 2),
            $this->fw->text('V', 1),
            $this->fw->number($sale->total_acrescimos ?? 0, 13, 2),
            $this->fw->text('V', 1),
            $this->fw->number($sale->total_financeiro, 14, 2),
            $this->fw->number($this->isContingencySale($sale) ? 9 : 1, 1),
            $this->fw->digits($document?->access_key, 44),
            $this->fw->number($document?->number ?: $sale->numero, 10),
            $this->fw->text($document?->series ?: '1', 3),
            $this->fw->digits($this->saleCustomerDocument($sale), 14),
        );
    }

    private function contingencyItemRecord(?CompanySetting $company, Sale $sale, $item, int $number): string
    {
        $document = $sale->fiscalDocument;

        return $this->fw->line(
            $this->fw->text('J2', 2),
            $this->fw->digits($company?->cnpj, 14),
            $this->fw->date($sale->sold_at ?: $sale->created_at),
            $this->fw->number($number, 3),
            $this->fw->text($item->produto_codigo, 14),
            $this->fw->text($item->produto_nome, 100),
            $this->fw->number($item->quantidade, 7, 3),
            $this->fw->text($item->unidade, 3),
            $this->fw->number($item->valor_unitario, 8, 2),
            $this->fw->number($item->valor_desconto ?? 0, 8, 2),
            $this->fw->number($item->valor_acrescimo ?? 0, 8, 2),
            $this->fw->number($item->valor_total, 14, 2),
            $this->fw->text($this->totalizer($item->catalogProduct), 7),
            $this->fw->number(3, 1),
            $this->fw->number(2, 1),
            $this->fw->number($document?->number ?: $sale->numero, 10),
            $this->fw->text($document?->series ?: '1', 3),
            $this->fw->digits($document?->access_key, 44),
        );
    }

    /**
     * @return Collection<int, Produto>
     */
    private function productRows(array $filters): Collection
    {
        $query = Produto::query()
            ->with(['unidadeMedida', 'precos', 'codigosBarras', 'estoque'])
            ->orderBy('cod_sku')
            ->orderBy('descricao');

        $search = trim((string) ($filters['product'] ?? $filters['search'] ?? ''));
        if ($search !== '') {
            $query->where(function ($builder) use ($search): void {
                $builder->where('descricao', 'like', "%{$search}%")
                    ->orWhere('cod_sku', 'like', "%{$search}%")
                    ->orWhereHas('codigosBarras', fn ($barcode) => $barcode->where('codigo', 'like', "%{$search}%"));
            });
        }

        return $query->get();
    }

    private function davRows(CarbonImmutable $start, CarbonImmutable $end): Collection
    {
        return PafDav::query()
            ->with(['items', 'itemLogs', 'items.dav', 'itemLogs.dav'])
            ->whereBetween('created_at', [$start, $end])
            ->orderBy('number')
            ->get();
    }

    private function nfceRows(CarbonImmutable $start, CarbonImmutable $end): Collection
    {
        return Sale::query()
            ->with(['items.catalogProduct', 'payments', 'fiscalDocument'])
            ->where('document_type', 'nfce')
            ->whereBetween('sold_at', [$start, $end])
            ->orderBy('sold_at')
            ->orderBy('numero')
            ->get();
    }

    private function openFichaRows(): Collection
    {
        return RestaurantFicha::query()
            ->with(['table', 'productionTickets.items'])
            ->whereNotIn('status', [
                RestaurantFicha::STATUS_PAID,
                RestaurantFicha::STATUS_CANCELED,
                RestaurantFicha::STATUS_CLOSED,
            ])
            ->orderBy('opened_at')
            ->get();
    }

    private function dailyPaymentRows(CarbonImmutable $start, CarbonImmutable $end): array
    {
        $rows = [];
        $sales = Sale::query()
            ->with('payments')
            ->whereBetween('sold_at', [$start, $end])
            ->whereIn('status', [Sale::STATUS_FINALIZED, Sale::STATUS_CANCELED])
            ->get();

        foreach ($sales as $sale) {
            $date = ($sale->sold_at ?: $sale->created_at)->toDateString();
            $documentType = $this->documentTypeCode($sale->document_type);
            foreach ($sale->payments as $payment) {
                $method = substr((string) ($payment->metodo_nome ?: 'Pagamento'), 0, 25);
                $key = implode('|', [$date, $method, $documentType]);
                $rows[$key] ??= [
                    'date' => $date,
                    'method' => $method,
                    'document_type' => $documentType,
                    'amount' => 0.0,
                ];
                $rows[$key]['amount'] += (float) $payment->valor;
            }
        }

        usort($rows, fn ($a, $b) => [$a['date'], $a['method'], $a['document_type']] <=> [$b['date'], $b['method'], $b['document_type']]);

        return $rows;
    }

    private function fichaSummary(RestaurantFicha $ficha): array
    {
        $items = [];
        foreach ($ficha->productionTickets as $ticket) {
            foreach ($ticket->items as $item) {
                $items[] = [
                    'code' => $item->product_code,
                    'description' => $item->product_name,
                    'quantity' => (float) $item->quantity,
                    'unit' => $item->unit ?: 'UN',
                    'unit_price' => (float) $item->unit_price,
                    'total' => (float) $item->total_price,
                ];
            }
        }

        return [
            'total' => round(array_sum(array_column($items, 'total')), 2),
            'conference_number' => RestaurantConferenceReport::query()
                ->where('ficha_id', $ficha->id)
                ->latest('generated_at')
                ->value('number'),
            'items' => $items,
        ];
    }

    private function dateRange(array $filters): array
    {
        $start = CarbonImmutable::parse($filters['start_date'] ?? $filters['date'] ?? now()->toDateString())->startOfDay();
        $end = CarbonImmutable::parse($filters['end_date'] ?? $filters['date'] ?? $start->toDateString())->endOfDay();

        return [$start, $end];
    }

    private function productCode(Produto $product): string
    {
        $barcode = $product->codigosBarras->firstWhere('principal', true) ?: $product->codigosBarras->first();

        return (string) ($barcode?->codigo ?: $product->cod_sku ?: $product->codigo_operacional ?: $product->id);
    }

    private function unit(Produto $product): string
    {
        return mb_strtoupper((string) ($product->unidadeMedida?->codigo_fiscal ?: $product->unidadeMedida?->unidade ?: 'UN'));
    }

    private function iat(Produto $product): string
    {
        return in_array($product->cod_iat, ['A', 'T'], true) ? $product->cod_iat : 'A';
    }

    private function ippt(Produto $product): string
    {
        return in_array($product->cod_ippt, ['P', 'T'], true) ? $product->cod_ippt : 'T';
    }

    private function referencePrice(Produto $product): float
    {
        $price = $product->precos->firstWhere('ativo', true) ?: $product->precos->first();

        return round((float) ($price?->valor ?? 0), 2);
    }

    private function taxSituation(?Produto $product): array
    {
        if (! $product) {
            return ['T', 0.0];
        }
        if (mb_strtolower((string) $product->produto_tipo) === 'servico' || $product->servico_codigo) {
            return ['S', (float) data_get($product->atributos_logisticos, 'fiscal_aliquota_iss', 0)];
        }
        if ($this->digitsOnly($product->cest) !== '') {
            return ['F', 0.0];
        }

        return ['T', (float) data_get($product->atributos_logisticos, 'fiscal_aliquota_icms', 0)];
    }

    private function totalizer(?Produto $product): string
    {
        [$situation, $rate] = $this->taxSituation($product);

        return match ($situation) {
            'S' => 'S'.str_pad((string) (int) round($rate * 100), 4, '0', STR_PAD_LEFT),
            'F' => 'F',
            'I' => 'I',
            'N' => 'N',
            default => 'T'.str_pad((string) (int) round($rate * 100), 4, '0', STR_PAD_LEFT),
        };
    }

    private function isContingencySale(Sale $sale): bool
    {
        return $sale->fiscalDocument?->status === SaleFiscalDocument::STATUS_CONTINGENCY_PENDING
            || filled($sale->fiscalDocument?->contingency_printed_at);
    }

    private function saleCustomerDocument(Sale $sale): string
    {
        return $this->digitsOnly(data_get($sale->customer_snapshot, 'cpf_cnpj')
            ?: data_get($sale->customer_snapshot, 'documento')
            ?: data_get($sale->customer_snapshot, 'cpf')
            ?: data_get($sale->customer_snapshot, 'cnpj'));
    }

    private function documentTypeCode(?string $documentType): string
    {
        return mb_strtolower((string) $documentType) === 'nfe' ? '2' : '1';
    }

    private function relatedDavNumber(PafExternalRequisition $requisition): ?string
    {
        return $requisition->dav_id ? PafDav::query()->whereKey($requisition->dav_id)->value('number') : null;
    }

    private function relatedPreSaleCode(PafExternalRequisition $requisition): ?string
    {
        return $requisition->pre_sale_id ? PafPreSale::query()->whereKey($requisition->pre_sale_id)->value('code') : null;
    }

    private function appName(?FiscalConfig $config): string
    {
        return (string) ($config?->paf_app_name ?: config('app.name', 'Freeline PDV'));
    }

    private function appVersion(?FiscalConfig $config): string
    {
        return (string) ($config?->paf_app_version ?: '1.0.0');
    }

    private function architecture(mixed $value, string $fallback, mixed $provider): string
    {
        $label = trim((string) ($value ?: $fallback));
        $provider = trim((string) $provider);

        return $provider !== '' && str_contains(mb_strtolower($label), 'nuvem') ? "{$label} - {$provider}" : $label;
    }

    private function digitsOnly(mixed $value): string
    {
        return preg_replace('/\D+/', '', (string) ($value ?? '')) ?: '';
    }
}
