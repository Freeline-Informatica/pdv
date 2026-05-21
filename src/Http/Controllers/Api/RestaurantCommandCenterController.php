<?php

namespace Freeline\Pdv\Http\Controllers\Api;

use Freeline\Pdv\Http\Controllers\Controller;
use Freeline\Pdv\Models\RestaurantFicha;
use Freeline\Pdv\Models\RestaurantTable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class RestaurantCommandCenterController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        return response()->json([
            'snapshot' => $this->buildSnapshot(),
            'meta' => [
                'source' => 'server',
                'generated_at' => now()->toIso8601String(),
            ],
        ]);
    }

    public function reintegrate(Request $request): JsonResponse
    {
        return response()->json([
            'message' => 'Comandas reintegradas com sucesso.',
            'snapshot' => $this->buildSnapshot(),
            'meta' => [
                'source' => 'server',
                'reintegrated_at' => now()->toIso8601String(),
            ],
        ]);
    }

    public function transfer(Request $request): JsonResponse
    {
        $payload = $request->validate([
            'origin_table_id' => ['required', 'string', 'max:80'],
            'origin_command_id' => ['required', 'string', 'max:80'],
            'destination_type' => ['required', 'string', 'in:command,table'],
            'destination_code' => ['required', 'string', 'max:80'],
            'transfer_mode' => ['required', 'string', 'in:partial,full'],
            'quantity' => ['nullable', 'numeric', 'gt:0'],
            'reason' => ['nullable', 'string', 'max:1000'],
        ]);

        return response()->json([
            'message' => 'Transferência operacional registrada.',
            'audit' => [
                'event' => 'command_transfer_registered',
                'registered_at' => now()->toIso8601String(),
                'registered_by' => (string) ($request->user()?->name ?? 'Operador'),
                'payload' => $payload,
            ],
        ]);
    }

    public function merge(Request $request): JsonResponse
    {
        $payload = $request->validate([
            'source_command_id' => ['required', 'string', 'max:80'],
            'destination_command_id' => ['required', 'string', 'max:80', 'different:source_command_id'],
            'keep_original_open_date' => ['required', 'boolean'],
        ]);

        return response()->json([
            'message' => 'Junção de fichas registrada para conferência.',
            'audit' => [
                'event' => 'command_merge_registered',
                'registered_at' => now()->toIso8601String(),
                'registered_by' => (string) ($request->user()?->name ?? 'Operador'),
                'payload' => $payload,
            ],
        ]);
    }

    public function print(Request $request): JsonResponse
    {
        $payload = $request->validate([
            'action' => ['required', 'string', 'in:conference,non_fiscal_receipt,kitchen_order,bar_order'],
            'table_id' => ['required', 'string', 'max:80'],
            'command_id' => ['required', 'string', 'max:80'],
        ]);

        return response()->json([
            'message' => 'Impressão operacional registrada. Não é documento fiscal.',
            'audit' => [
                'event' => 'command_print_registered',
                'registered_at' => now()->toIso8601String(),
                'registered_by' => (string) ($request->user()?->name ?? 'Operador'),
                'payload' => $payload,
            ],
        ]);
    }

    public function conference(Request $request): JsonResponse
    {
        $payload = $request->validate([
            'table_id' => ['required', 'string', 'max:80'],
            'command_id' => ['required', 'string', 'max:80'],
        ]);

        return response()->json([
            'message' => 'Conferência operacional registrada.',
            'audit' => [
                'event' => 'command_conference_registered',
                'registered_at' => now()->toIso8601String(),
                'registered_by' => (string) ($request->user()?->name ?? 'Operador'),
                'payload' => $payload,
            ],
        ]);
    }

    private function buildSnapshot(): array
    {
        $tables = RestaurantTable::query()
            ->where('active', true)
            ->orderBy('code')
            ->get(['id', 'code', 'name']);

        if ($tables->isEmpty()) {
            return [
                'closedTables' => [],
                'openedTables' => [],
            ];
        }

        $fichas = RestaurantFicha::query()
            ->whereIn('table_id', $tables->pluck('id'))
            ->whereIn('status', $this->commandCenterTrackedStatuses())
            ->with([
                'table:id,code,name',
                'productionTickets' => function ($query): void {
                    $query->with('items')->orderBy('created_at');
                },
            ])
            ->orderBy('opened_at')
            ->get();

        $openedFichasByTable = $fichas
            ->filter(fn (RestaurantFicha $ficha): bool => $this->isOpenedTabStatus((string) $ficha->status))
            ->groupBy('table_id');

        $closedFichasByTable = $fichas
            ->filter(fn (RestaurantFicha $ficha): bool => ! $this->isOpenedTabStatus((string) $ficha->status))
            ->groupBy('table_id');

        $openedTables = $openedFichasByTable
            ->map(function (Collection $tableFichas) {
                return $this->buildTableSnapshotFromFichas($tableFichas, true);
            })
            ->filter()
            ->sortBy(fn (array $table): string => (string) ($table['code'] ?? ''))
            ->values()
            ->all();

        $closedTables = $closedFichasByTable
            ->map(function (Collection $tableFichas) {
                return $this->buildTableSnapshotFromFichas($tableFichas, false);
            })
            ->filter()
            ->sortBy(fn (array $table): string => (string) ($table['code'] ?? ''))
            ->values()
            ->all();

        return [
            'closedTables' => $closedTables,
            'openedTables' => $openedTables,
        ];
    }

    private function commandCenterTrackedStatuses(): array
    {
        return [
            RestaurantFicha::STATUS_OPENED,
            RestaurantFicha::STATUS_IN_SERVICE,
            RestaurantFicha::STATUS_WAITING_PRODUCTION,
            RestaurantFicha::STATUS_PARTIALLY_DELIVERED,
            RestaurantFicha::STATUS_IN_CONFERENCE,
            RestaurantFicha::STATUS_WAITING_PAYMENT,
            RestaurantFicha::STATUS_PAID,
            RestaurantFicha::STATUS_CANCELED,
            RestaurantFicha::STATUS_CLOSED,
        ];
    }

    private function isOpenedTabStatus(string $status): bool
    {
        return in_array($status, [
            RestaurantFicha::STATUS_OPENED,
            RestaurantFicha::STATUS_IN_SERVICE,
            RestaurantFicha::STATUS_WAITING_PRODUCTION,
            RestaurantFicha::STATUS_PARTIALLY_DELIVERED,
            RestaurantFicha::STATUS_IN_CONFERENCE,
        ], true);
    }

    private function buildTableSnapshotFromFichas(Collection $tableFichas, bool $openedTab): ?array
    {
        /** @var RestaurantFicha|null $referenceFicha */
        $referenceFicha = $tableFichas->first();
        if (! $referenceFicha) return null;

        $commands = $tableFichas
            ->map(fn (RestaurantFicha $ficha): array => $this->buildCommandSnapshot($ficha))
            ->values();

        $tableCode = (string) ($referenceFicha->table?->code ?: '--');
        $tableName = (string) ($referenceFicha->table?->name ?: "Mesa {$tableCode}");
        $firstCommandWithWaiter = $commands->first(function (array $command): bool {
            return trim((string) ($command['waiterName'] ?? '')) !== '';
        });
        $waiterName = (string) (($firstCommandWithWaiter['waiterName'] ?? '') ?: $referenceFicha->waiter_name ?: 'Equipe');
        $itemsCount = (int) $commands->sum(fn (array $command): int => count($command['items'] ?? []));
        $commandsCount = (int) $commands->count();
        $tableTotal = round((float) $commands->sum('total'), 2);
        $pendingFiscal = $commands->contains(fn (array $command): bool => (bool) ($command['pendingFiscal'] ?? false));

        $openedAt = $tableFichas
            ->pluck('opened_at')
            ->filter()
            ->sort()
            ->first();

        $closedAt = $tableFichas
            ->map(fn (RestaurantFicha $ficha) => $ficha->closed_at ?: $ficha->closing_requested_at)
            ->filter()
            ->sort()
            ->last();

        return [
            'id' => (string) ($referenceFicha->table_id ?: ''),
            'code' => $tableCode,
            'customerName' => $tableName,
            'openedAtLabel' => $this->formatTimeLabel($openedAt, 'Aberta'),
            'closedAtLabel' => $this->formatTimeLabel($closedAt, 'Fechada'),
            'status' => $openedTab ? 'opened' : 'closed',
            'waiterName' => $waiterName,
            'pendingFiscal' => $pendingFiscal,
            'commandsCount' => $commandsCount,
            'itemsCount' => $itemsCount,
            'total' => $tableTotal,
            'commands' => $commands->all(),
        ];
    }

    private function buildCommandSnapshot(RestaurantFicha $ficha): array
    {
        $items = collect($ficha->productionTickets)
            ->flatMap(function ($ticket) use ($ficha): Collection {
                $ticketWaiterName = trim((string) ($ticket?->waiter_name ?: $ficha->waiter_name ?: 'Equipe'));
                $ticketTime = optional($ticket?->created_at)->format('H:i') ?: '--';

                return collect($ticket?->items ?? [])
                    ->map(function ($item) use ($ticketWaiterName, $ticketTime): array {
                        $qty = round(max(0.001, (float) ($item?->quantity ?? 0)), 3);
                        $unitPrice = round((float) ($item?->unit_price ?? 0), 2);

                        return [
                            'id' => (string) ($item?->id ?: ''),
                            'productId' => (string) ($item?->product_id ?: ''),
                            'nome' => (string) ($item?->product_name ?: 'Item sem descrição'),
                            'codigo' => (string) ($item?->product_code ?: ''),
                            'unidade' => 'UN',
                            'qty' => $qty,
                            'preco_venda' => $unitPrice,
                            'total' => round($qty * $unitPrice, 2),
                            'observation' => trim((string) ($item?->observation ?: '')),
                            'sellerName' => $ticketWaiterName,
                            'history' => [
                                [
                                    'id' => sprintf('hist-%s', (string) ($item?->id ?: uniqid())),
                                    'atLabel' => $ticketTime,
                                    'action' => 'Lançamento inicial',
                                    'by' => $ticketWaiterName,
                                    'quantity' => $qty,
                                ],
                            ],
                        ];
                    });
            })
            ->values();

        $total = round((float) $items->sum(fn (array $item): float => (float) ($item['total'] ?? 0)), 2);
        $commandStatus = $this->mapCommandStatus((string) $ficha->status);
        $pendingFiscal = $commandStatus === 'pending_fiscal';

        return [
            'id' => (string) $ficha->id,
            'code' => (string) ($ficha->code ?: '--'),
            'status' => $commandStatus,
            'openedAtLabel' => $this->formatTimeLabel($ficha->opened_at, 'Aberta'),
            'closedAtLabel' => $this->formatTimeLabel($ficha->closed_at ?: $ficha->closing_requested_at, 'Fechada'),
            'waiterName' => (string) ($ficha->waiter_name ?: 'Equipe'),
            'total' => $total,
            'itemsCount' => $items->count(),
            'pendingFiscal' => $pendingFiscal,
            'items' => $items->all(),
            'tags' => $commandStatus === 'problem' ? ['Atenção'] : [],
        ];
    }

    private function mapCommandStatus(string $fichaStatus): string
    {
        if ($fichaStatus === RestaurantFicha::STATUS_WAITING_PAYMENT) {
            return 'pending_fiscal';
        }

        if (in_array($fichaStatus, [RestaurantFicha::STATUS_PAID, RestaurantFicha::STATUS_CLOSED], true)) {
            return 'closed';
        }

        if ($fichaStatus === RestaurantFicha::STATUS_CANCELED) {
            return 'problem';
        }

        return 'opened';
    }

    private function formatTimeLabel($value, string $prefix): string
    {
        if (! $value) {
            return "{$prefix} às --";
        }

        return sprintf('%s às %s', $prefix, optional($value)->format('H:i') ?: '--');
    }
}
