<?php

namespace Freeline\Pdv\Http\Controllers\Api;

use Freeline\Pdv\Http\Controllers\Controller;
use Freeline\Pdv\Models\Produto;
use Freeline\Pdv\Models\RestaurantFicha;
use Freeline\Pdv\Models\RestaurantProductionTicket;
use Freeline\Pdv\Models\RestaurantProductionTicketItem;
use Freeline\Pdv\Models\RestaurantTable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class RestaurantOperationsController extends Controller
{
    public function orderingContext(Request $request): JsonResponse
    {
        $tables = RestaurantTable::query()
            ->where('active', true)
            ->orderBy('code')
            ->get();

        $fichas = RestaurantFicha::query()
            ->whereIn('table_id', $tables->pluck('id'))
            ->whereIn('status', $this->activeFichaStatuses())
            ->with([
                'productionTickets' => function ($query): void {
                    $query->with('items')->orderBy('created_at');
                },
            ])
            ->orderBy('opened_at')
            ->get()
            ->groupBy('table_id');

        $snapshotTables = $tables->map(function (RestaurantTable $table) use ($fichas): array {
            $tableFichas = $fichas->get($table->id, collect())
                ->map(fn (RestaurantFicha $ficha): array => $this->buildFichaSnapshot($ficha))
                ->values();

            $tableTotal = round((float) $tableFichas->sum('total'), 2);

            return [
                'id' => (string) $table->id,
                'code' => (string) $table->code,
                'name' => (string) ($table->name ?: "Mesa {$table->code}"),
                'status' => $tableFichas->isEmpty() ? 'empty' : 'opened',
                'fichas' => $tableFichas,
                'fichasCount' => (int) $tableFichas->count(),
                'totalOpen' => $tableTotal,
            ];
        })->values();

        return response()->json([
            'waiter' => [
                'id' => (string) ($request->user()?->id ?: ''),
                'name' => (string) ($request->user()?->name ?: 'Equipe'),
                'email' => (string) ($request->user()?->email ?: ''),
            ],
            'tables' => $snapshotTables,
            'meta' => [
                'generated_at' => now()->toIso8601String(),
                'source' => 'server',
            ],
        ]);
    }

    public function createFicha(Request $request): JsonResponse
    {
        $payload = $request->validate([
            'table_id' => ['required', 'string', 'exists:restaurant_tables,id'],
            'customer_name' => ['nullable', 'string', 'max:120'],
            'random_customer' => ['nullable', 'boolean'],
            'ficha_code' => ['nullable', 'string', 'max:30'],
        ]);

        $table = RestaurantTable::query()->findOrFail($payload['table_id']);

        if (! $table->active) {
            return response()->json([
                'message' => 'A mesa selecionada está inativa.',
            ], 422);
        }

        $randomCustomer = array_key_exists('random_customer', $payload)
            ? (bool) $payload['random_customer']
            : true;

        $providedCode = trim((string) ($payload['ficha_code'] ?? ''));
        $code = $providedCode !== '' ? $providedCode : $this->generateFichaCode($table->code);
        $customerName = trim((string) ($payload['customer_name'] ?? ''));

        if ($randomCustomer && $customerName === '') {
            $customerName = sprintf('Cliente %s', str_pad((string) random_int(1, 999), 3, '0', STR_PAD_LEFT));
        }

        if (! $randomCustomer && $customerName === '') {
            $customerName = 'Cliente balcão';
        }

        $ficha = RestaurantFicha::query()->create([
            'table_id' => $table->id,
            'code' => $this->resolveUniqueFichaCode($code),
            'customer_name' => $customerName,
            'is_random_customer' => $randomCustomer,
            'waiter_name' => (string) ($request->user()?->name ?: 'Equipe'),
            'status' => RestaurantFicha::STATUS_OPENED,
            'opened_at' => now(),
        ]);

        return response()->json([
            'message' => 'Ficha criada com sucesso.',
            'ficha' => $this->buildFichaSnapshot($ficha->load('productionTickets.items')),
        ], 201);
    }

    public function fichaSummary(RestaurantFicha $ficha): JsonResponse
    {
        return response()->json([
            'summary' => $this->buildFichaSummary($ficha),
        ]);
    }

    public function saveFichaObservation(Request $request, RestaurantFicha $ficha): JsonResponse
    {
        $payload = $request->validate([
            'observation' => ['nullable', 'string', 'max:2000'],
        ]);

        $ficha->observation = trim((string) ($payload['observation'] ?? '')) ?: null;
        $ficha->save();

        return response()->json([
            'message' => 'Observação da ficha salva com sucesso.',
            'summary' => $this->buildFichaSummary($ficha),
        ]);
    }

    public function requestFichaClosing(Request $request, RestaurantFicha $ficha): JsonResponse
    {
        if (in_array($ficha->status, [RestaurantFicha::STATUS_PAID, RestaurantFicha::STATUS_CANCELED], true)) {
            return response()->json([
                'message' => 'A ficha selecionada não pode ser enviada ao caixa neste status.',
            ], 422);
        }

        $ficha->loadMissing(['productionTickets.items']);
        $totals = $this->computeFichaTotals($ficha);

        if (($totals['items_count'] ?? 0) <= 0) {
            return response()->json([
                'message' => 'A ficha precisa ter itens salvos antes de solicitar fechamento.',
            ], 422);
        }

        $ficha->status = RestaurantFicha::STATUS_WAITING_PAYMENT;
        $ficha->closing_requested_at = now();
        $ficha->closing_requested_by = (string) ($request->user()?->name ?: $ficha->waiter_name ?: 'Equipe');
        $ficha->save();

        return response()->json([
            'message' => 'Ficha enviada para o caixa. Aguardando pagamento.',
            'summary' => $this->buildFichaSummary($ficha),
        ]);
    }

    public function conference(RestaurantFicha $ficha): JsonResponse
    {
        return response()->json([
            'message' => 'Conferência carregada com sucesso.',
            'summary' => $this->buildFichaSummary($ficha),
        ]);
    }

    public function submitFichaOrder(Request $request, RestaurantFicha $ficha): JsonResponse
    {
        if (! $this->canReceiveOrders($ficha->status)) {
            return response()->json([
                'message' => 'Esta ficha não aceita novos lançamentos no momento.',
            ], 422);
        }

        $payload = $request->validate([
            'table_id' => ['required', 'string', 'exists:restaurant_tables,id'],
            'order_observation' => ['nullable', 'string', 'max:2000'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'string'],
            'items.*.nome' => ['nullable', 'string', 'max:160'],
            'items.*.quantity' => ['required', 'numeric', 'gt:0'],
            'items.*.observation' => ['nullable', 'string', 'max:1000'],
            'items.*.selected_options' => ['nullable', 'array'],
            'items.*.selected_options.*' => ['nullable', 'string', 'max:120'],
            'items.*.removed_ingredients' => ['nullable', 'array'],
            'items.*.removed_ingredients.*' => ['nullable', 'string', 'max:120'],
        ]);

        if ((string) $payload['table_id'] !== (string) $ficha->table_id) {
            return response()->json([
                'message' => 'A mesa selecionada não corresponde à ficha informada.',
            ], 422);
        }

        $productIds = collect($payload['items'])
            ->pluck('product_id')
            ->map(fn ($id) => (string) $id)
            ->filter()
            ->unique()
            ->values();

        $productsById = Produto::query()
            ->with([
                'familia:id,nome',
                'precos:id,produto_id,valor,ativo,tipo',
            ])
            ->whereIn('id', $productIds)
            ->get(['id', 'descricao', 'descricao_curta', 'cod_sku', 'codigo_operacional', 'produto_familia_id'])
            ->keyBy(fn (Produto $product) => (string) $product->id);

        $missingProducts = $productIds
            ->filter(fn (string $productId) => ! $productsById->has($productId))
            ->values();

        if ($missingProducts->isNotEmpty()) {
            return response()->json([
                'message' => 'Um ou mais produtos do pedido não existem mais no banco.',
                'errors' => [
                    'items' => ['Produtos inválidos: ' . $missingProducts->implode(', ')],
                ],
            ], 422);
        }

        $orderObservation = trim((string) ($payload['order_observation'] ?? ''));

        $itemsBySector = [
            'cozinha' => [],
            'bar' => [],
        ];

        foreach ($payload['items'] as $item) {
            $productId = (string) $item['product_id'];
            $product = $productsById->get($productId);

            if (! $product) {
                return response()->json([
                    'message' => 'Produto inválido no pedido atual.',
                ], 422);
            }

            $productName = trim((string) ($item['nome'] ?? $product->descricao ?? $product->descricao_curta ?? 'Item sem descrição'));
            $productCode = (string) ($product->cod_sku ?: $product->codigo_operacional ?: '');
            $quantity = round(max(0.001, (float) $item['quantity']), 3);
            $unitPrice = $this->resolveProductUnitPrice($product);
            $totalPrice = round($unitPrice * $quantity, 2);
            $sector = $this->resolveSectorForProduct($product);

            $itemsBySector[$sector][] = [
                'product_id' => null,
                'product_name' => $productName,
                'product_code' => $productCode,
                'quantity' => $quantity,
                'unit_price' => $unitPrice,
                'total_price' => $totalPrice,
                'observation' => trim((string) ($item['observation'] ?? '')),
                'selected_options' => $this->stringifySimpleList($item['selected_options'] ?? []),
                'removed_ingredients' => $this->stringifySimpleList($item['removed_ingredients'] ?? []),
            ];
        }

        $createdTickets = [];

        DB::transaction(function () use ($itemsBySector, $ficha, $request, $orderObservation, &$createdTickets): void {
            foreach ($itemsBySector as $sector => $items) {
                if (! count($items)) {
                    continue;
                }

                $ticket = RestaurantProductionTicket::query()->create([
                    'table_id' => $ficha->table_id,
                    'ficha_id' => $ficha->id,
                    'sector' => $sector,
                    'status' => RestaurantProductionTicket::STATUS_NEW,
                    'waiter_name' => (string) ($request->user()?->name ?: $ficha->waiter_name ?: 'Equipe'),
                    'order_observation' => $orderObservation !== '' ? $orderObservation : null,
                ]);

                foreach ($items as $line) {
                    RestaurantProductionTicketItem::query()->create([
                        'ticket_id' => $ticket->id,
                        'product_id' => $line['product_id'],
                        'product_name' => $line['product_name'],
                        'product_code' => $line['product_code'],
                        'quantity' => $line['quantity'],
                        'unit_price' => $line['unit_price'],
                        'total_price' => $line['total_price'],
                        'observation' => $line['observation'] !== '' ? $line['observation'] : null,
                        'selected_options' => $line['selected_options'] !== '' ? $line['selected_options'] : null,
                        'removed_ingredients' => $line['removed_ingredients'] !== '' ? $line['removed_ingredients'] : null,
                    ]);
                }

                $createdTickets[] = [
                    'id' => (string) $ticket->id,
                    'sector' => (string) $ticket->sector,
                    'status' => (string) $ticket->status,
                    'itemsCount' => count($items),
                ];
            }

            if (count($createdTickets) > 0) {
                $ficha->status = RestaurantFicha::STATUS_WAITING_PRODUCTION;
                $ficha->waiter_name = (string) ($request->user()?->name ?: $ficha->waiter_name ?: 'Equipe');
                $ficha->save();
            }
        });

        $ficha->refresh();

        return response()->json([
            'message' => sprintf('Pedido enviado para produção e salvo na ficha %s.', (string) $ficha->code),
            'ficha_id' => (string) $ficha->id,
            'tickets' => $createdTickets,
            'summary' => $this->buildFichaSummary($ficha),
        ], 201);
    }

    public function productionTickets(Request $request): JsonResponse
    {
        $payload = $request->validate([
            'sector' => ['nullable', 'string', 'in:todos,cozinha,bar'],
            'delayed_only' => ['nullable'],
        ]);

        $sector = (string) ($payload['sector'] ?? 'todos');
        $delayedRaw = mb_strtolower(trim((string) ($payload['delayed_only'] ?? '0')));
        if (! in_array($delayedRaw, ['', '0', '1', 'true', 'false'], true)) {
            return response()->json([
                'message' => 'Parametro delayed_only invalido.',
                'errors' => [
                    'delayed_only' => ['Use 0, 1, true ou false.'],
                ],
            ], 422);
        }
        $delayedOnly = in_array($delayedRaw, ['1', 'true'], true);

        $query = RestaurantProductionTicket::query()
            ->with(['table', 'ficha', 'items'])
            ->orderBy('created_at');

        if ($sector !== 'todos') {
            $query->where('sector', $sector);
        }

        $tickets = $query->get()
            ->map(function (RestaurantProductionTicket $ticket): array {
                $elapsedMinutes = now()->diffInMinutes($ticket->created_at);
                $isDelayed = $elapsedMinutes >= 20;

                return [
                    'id' => (string) $ticket->id,
                    'status' => (string) $ticket->status,
                    'setor' => (string) $ticket->sector,
                    'mesa' => (string) ($ticket->table?->code ?: '--'),
                    'ficha' => (string) ($ticket->ficha?->code ?: '--'),
                    'garcom' => (string) ($ticket->waiter_name ?: $ticket->ficha?->waiter_name ?: 'Equipe'),
                    'criadoEm' => optional($ticket->created_at)->toIso8601String(),
                    'elapsedMinutes' => $elapsedMinutes,
                    'isDelayed' => $isDelayed,
                    'itens' => $ticket->items->map(function (RestaurantProductionTicketItem $item): array {
                        return [
                            'id' => (string) $item->id,
                            'nome' => (string) $item->product_name,
                            'quantidade' => (float) $item->quantity,
                            'observacao' => (string) ($item->observation ?: ''),
                        ];
                    })->values(),
                ];
            })
            ->when($delayedOnly, function ($collection) {
                return $collection->filter(fn (array $ticket) => $ticket['isDelayed'])->values();
            })
            ->values();

        return response()->json([
            'tickets' => $tickets,
            'meta' => [
                'generated_at' => now()->toIso8601String(),
                'sector' => $sector,
                'delayed_only' => $delayedOnly,
            ],
        ]);
    }

    public function updateProductionTicketStatus(Request $request, RestaurantProductionTicket $ticket): JsonResponse
    {
        $payload = $request->validate([
            'status' => ['required', 'string', 'in:em_preparo,pronto,entregue'],
        ]);

        $nextStatus = (string) $payload['status'];

        $ticket->status = $nextStatus;
        if ($nextStatus === RestaurantProductionTicket::STATUS_PREPARING && ! $ticket->started_at) {
            $ticket->started_at = now();
        }
        if ($nextStatus === RestaurantProductionTicket::STATUS_READY) {
            $ticket->ready_at = now();
        }
        if ($nextStatus === RestaurantProductionTicket::STATUS_DELIVERED) {
            $ticket->delivered_at = now();
        }
        $ticket->save();

        return response()->json([
            'message' => 'Status do ticket atualizado com sucesso.',
            'ticket' => [
                'id' => (string) $ticket->id,
                'status' => (string) $ticket->status,
                'setor' => (string) $ticket->sector,
            ],
        ]);
    }

    private function activeFichaStatuses(): array
    {
        return [
            RestaurantFicha::STATUS_OPENED,
            RestaurantFicha::STATUS_IN_SERVICE,
            RestaurantFicha::STATUS_WAITING_PRODUCTION,
            RestaurantFicha::STATUS_PARTIALLY_DELIVERED,
            RestaurantFicha::STATUS_IN_CONFERENCE,
            RestaurantFicha::STATUS_WAITING_PAYMENT,
        ];
    }

    private function canReceiveOrders(string $status): bool
    {
        return in_array($status, [
            RestaurantFicha::STATUS_OPENED,
            RestaurantFicha::STATUS_IN_SERVICE,
            RestaurantFicha::STATUS_WAITING_PRODUCTION,
            RestaurantFicha::STATUS_PARTIALLY_DELIVERED,
            RestaurantFicha::STATUS_IN_CONFERENCE,
        ], true);
    }

    private function buildFichaSnapshot(RestaurantFicha $ficha): array
    {
        $ficha->loadMissing(['productionTickets.items', 'table']);
        $totals = $this->computeFichaTotals($ficha);

        return [
            'id' => (string) $ficha->id,
            'tableId' => (string) $ficha->table_id,
            'tableCode' => (string) ($ficha->table?->code ?: '--'),
            'code' => (string) $ficha->code,
            'customerName' => (string) ($ficha->customer_name ?: 'Cliente balcão'),
            'isRandomCustomer' => (bool) $ficha->is_random_customer,
            'waiterName' => (string) ($ficha->waiter_name ?: 'Equipe'),
            'status' => (string) $ficha->status,
            'observation' => (string) ($ficha->observation ?: ''),
            'openedAt' => optional($ficha->opened_at)->toIso8601String(),
            'itemsCount' => $totals['items_count'],
            'total' => $totals['total'],
            'ticketsCount' => (int) $ficha->productionTickets->count(),
            'canAddItems' => $this->canReceiveOrders((string) $ficha->status),
            'closingRequestedAt' => optional($ficha->closing_requested_at)->toIso8601String(),
            'closingRequestedBy' => (string) ($ficha->closing_requested_by ?: ''),
        ];
    }

    private function buildFichaSummary(RestaurantFicha $ficha): array
    {
        $ficha->loadMissing([
            'table',
            'productionTickets' => function ($query): void {
                $query->with('items')->orderBy('created_at');
            },
        ]);

        $totals = $this->computeFichaTotals($ficha);

        $tickets = $ficha->productionTickets
            ->map(function (RestaurantProductionTicket $ticket) use ($ficha): array {
                $ticketTotal = round((float) $ticket->items->sum(fn (RestaurantProductionTicketItem $item) => (float) $item->total_price), 2);

                return [
                    'id' => (string) $ticket->id,
                    'sector' => (string) $ticket->sector,
                    'status' => (string) $ticket->status,
                    'waiterName' => (string) ($ticket->waiter_name ?: $ficha->waiter_name ?: 'Equipe'),
                    'createdAt' => optional($ticket->created_at)->toIso8601String(),
                    'orderObservation' => (string) ($ticket->order_observation ?: ''),
                    'total' => $ticketTotal,
                    'items' => $ticket->items->map(function (RestaurantProductionTicketItem $item): array {
                        return [
                            'id' => (string) $item->id,
                            'productName' => (string) $item->product_name,
                            'productCode' => (string) ($item->product_code ?: ''),
                            'quantity' => (float) $item->quantity,
                            'unitPrice' => round((float) $item->unit_price, 2),
                            'totalPrice' => round((float) $item->total_price, 2),
                            'observation' => (string) ($item->observation ?: ''),
                        ];
                    })->values(),
                ];
            })
            ->values();

        $consolidatedMap = [];
        foreach ($tickets as $ticket) {
            foreach ($ticket['items'] as $item) {
                $key = sprintf('%s|%s|%s', $item['productName'], $item['productCode'], number_format((float) $item['unitPrice'], 2, '.', ''));
                if (! array_key_exists($key, $consolidatedMap)) {
                    $consolidatedMap[$key] = [
                        'productName' => $item['productName'],
                        'productCode' => $item['productCode'],
                        'quantity' => 0.0,
                        'unitPrice' => round((float) $item['unitPrice'], 2),
                        'totalPrice' => 0.0,
                    ];
                }
                $consolidatedMap[$key]['quantity'] += (float) $item['quantity'];
                $consolidatedMap[$key]['totalPrice'] += (float) $item['totalPrice'];
            }
        }

        $consolidatedItems = collect(array_values($consolidatedMap))
            ->map(function (array $item): array {
                return [
                    'productName' => (string) $item['productName'],
                    'productCode' => (string) $item['productCode'],
                    'quantity' => round((float) $item['quantity'], 3),
                    'unitPrice' => round((float) $item['unitPrice'], 2),
                    'totalPrice' => round((float) $item['totalPrice'], 2),
                ];
            })
            ->sortBy('productName')
            ->values();

        $subtotal = round((float) $consolidatedItems->sum('totalPrice'), 2);
        $serviceTax = 0.0;
        $discount = 0.0;
        $finalTotal = round($subtotal + $serviceTax - $discount, 2);

        return [
            'ficha' => [
                'id' => (string) $ficha->id,
                'code' => (string) $ficha->code,
                'status' => (string) $ficha->status,
                'openedAt' => optional($ficha->opened_at)->toIso8601String(),
                'closedAt' => optional($ficha->closed_at)->toIso8601String(),
                'waiterName' => (string) ($ficha->waiter_name ?: 'Equipe'),
                'customerName' => (string) ($ficha->customer_name ?: 'Cliente balcão'),
                'observation' => (string) ($ficha->observation ?: ''),
                'closingRequestedAt' => optional($ficha->closing_requested_at)->toIso8601String(),
                'closingRequestedBy' => (string) ($ficha->closing_requested_by ?: ''),
            ],
            'mesa' => [
                'id' => (string) $ficha->table_id,
                'code' => (string) ($ficha->table?->code ?: '--'),
                'name' => (string) ($ficha->table?->name ?: 'Mesa ' . ($ficha->table?->code ?: '--')),
            ],
            'pedidosEnviados' => $tickets,
            'itensDaFicha' => $consolidatedItems,
            'totals' => [
                'itemsCount' => (int) $totals['items_count'],
                'itemsDistinctCount' => (int) $consolidatedItems->count(),
                'subtotal' => $subtotal,
                'serviceTax' => $serviceTax,
                'discount' => $discount,
                'total' => $finalTotal,
            ],
        ];
    }

    private function resolveSectorForProduct(?Produto $product): string
    {
        if (! $product) {
            return 'cozinha';
        }

        $categoryName = mb_strtolower(trim((string) $product?->familia?->nome));
        if ($categoryName === '') {
            return 'cozinha';
        }

        $barKeywords = ['bebida', 'drink', 'suco', 'bar'];
        foreach ($barKeywords as $keyword) {
            if (str_contains($categoryName, $keyword)) {
                return 'bar';
            }
        }

        return 'cozinha';
    }

    private function resolveProductUnitPrice(Produto $product): float
    {
        $activePrice = $product->precos->firstWhere('ativo', true)
            ?? $product->precos->first();

        return round((float) ($activePrice?->valor ?? 0), 2);
    }

    private function stringifySimpleList(array $items): string
    {
        $normalized = collect($items)
            ->map(fn ($item) => trim((string) $item))
            ->filter(fn ($item) => $item !== '')
            ->values();

        return $normalized->implode(', ');
    }

    private function resolveUniqueFichaCode(string $baseCode): string
    {
        $normalized = trim($baseCode);
        if ($normalized === '') {
            $normalized = 'F' . random_int(1000, 9999);
        }

        if (! RestaurantFicha::query()->where('code', $normalized)->exists()) {
            return $normalized;
        }

        for ($attempt = 0; $attempt < 20; $attempt++) {
            $candidate = sprintf('%s-%02d', $normalized, $attempt + 1);
            if (! RestaurantFicha::query()->where('code', $candidate)->exists()) {
                return $candidate;
            }
        }

        return sprintf('%s-%s', $normalized, random_int(100, 999));
    }

    private function generateFichaCode(string $tableCode): string
    {
        $normalizedTable = preg_replace('/\D+/', '', $tableCode) ?: strtoupper(trim($tableCode));
        $prefix = $normalizedTable !== '' ? "F{$normalizedTable}" : 'F';

        return sprintf('%s-%03d', $prefix, random_int(1, 999));
    }

    private function computeFichaTotals(RestaurantFicha $ficha): array
    {
        $itemsCount = 0;
        $total = 0.0;

        foreach ($ficha->productionTickets as $ticket) {
            foreach ($ticket->items as $item) {
                $itemsCount++;
                $total += (float) $item->total_price;
            }
        }

        return [
            'items_count' => $itemsCount,
            'total' => round($total, 2),
        ];
    }
}
