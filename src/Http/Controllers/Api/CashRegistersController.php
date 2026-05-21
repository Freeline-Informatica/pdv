<?php

namespace Freeline\Pdv\Http\Controllers\Api;

use Freeline\Pdv\Contracts\CompanyContextResolver;
use Freeline\Pdv\Http\Controllers\Controller;
use Freeline\Pdv\Models\CashRegisterMovement;
use Freeline\Pdv\Models\CashRegisterSession;
use Freeline\Pdv\Models\PosTerminal;
use Freeline\Pdv\Models\Sale;
use Freeline\Pdv\Models\SalePayment;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CashRegistersController extends Controller
{
    public function __construct(
        private readonly CompanyContextResolver $companyContext,
    ) {
    }

    public function index(): JsonResponse
    {
        $sessions = $this->scopedSessionQuery()
            ->with(['opener:id,name,email', 'closer:id,name,email'])
            ->orderByDesc('opened_at')
            ->orderByDesc('created_at')
            ->get();

        $openRegisters = $sessions
            ->filter(fn (CashRegisterSession $session) => $session->status === CashRegisterSession::STATUS_OPEN)
            ->values();

        return response()->json([
            'open_registers' => $openRegisters
                ->map(fn (CashRegisterSession $session) => $this->presentOpenCard($session))
                ->values(),
            'history' => $sessions
                ->map(fn (CashRegisterSession $session) => $this->presentHistoryRow($session))
                ->values(),
        ]);
    }

    public function show(CashRegisterSession $cashRegister): JsonResponse
    {
        $this->ensureSessionBelongsToCurrentScope($cashRegister);

        $cashRegister->load([
            'opener:id,name,email',
            'closer:id,name,email',
            'movements.creator:id,name,email',
        ]);

        return response()->json($this->presentDetail($cashRegister));
    }

    public function open(Request $request): JsonResponse
    {
        $payload = $request->validate([
            'terminal_id' => ['required', 'string'],
            'valor_inicial' => ['required', 'numeric', 'min:0'],
            'observacoes' => ['nullable', 'string', 'max:1000'],
        ]);

        $operatorId = $request->user()?->id;
        $scope = $this->currentScope();

        $session = DB::transaction(function () use ($payload, $operatorId, $scope): CashRegisterSession {
            $terminal = $this->scopedTerminalQuery()
                ->lockForUpdate()
                ->find($payload['terminal_id']);

            if (! $terminal || ! $terminal->ativo) {
                throw ValidationException::withMessages([
                    'terminal_id' => ['Selecione um terminal ativo para abrir o caixa.'],
                ]);
            }

            $normalizedTerminalCode = mb_strtoupper(trim((string) $terminal->identificador));

            $hasOpenRegisterForTerminal = $this->scopedSessionQuery()
                ->where('status', CashRegisterSession::STATUS_OPEN)
                ->whereRaw('UPPER(terminal_codigo) = ?', [$normalizedTerminalCode])
                ->lockForUpdate()
                ->exists();

            if ($hasOpenRegisterForTerminal) {
                throw ValidationException::withMessages([
                    'terminal_id' => ['Já existe um caixa aberto para este terminal.'],
                ]);
            }

            $openingAmount = $this->roundMoney($payload['valor_inicial']);
            $openedAt = now();

            $record = CashRegisterSession::query()->create([
                'grupo_empresarial_id' => $scope['grupo_id'],
                'estabelecimento_id' => $scope['estabelecimento_id'],
                'terminal_nome' => trim((string) $terminal->nome),
                'terminal_codigo' => $normalizedTerminalCode,
                'status' => CashRegisterSession::STATUS_OPEN,
                'opened_at' => $openedAt,
                'closed_at' => null,
                'opening_amount' => $openingAmount,
                'cash_received_amount' => 0,
                'sangria_amount' => 0,
                'suprimento_amount' => 0,
                'expected_amount' => $openingAmount,
                'counted_amount' => null,
                'difference_amount' => null,
                'observacoes' => trim((string) ($payload['observacoes'] ?? '')) ?: null,
                'opened_by' => $operatorId,
                'closed_by' => null,
            ]);

            CashRegisterMovement::query()->create([
                'cash_register_session_id' => $record->id,
                'type' => CashRegisterMovement::TYPE_OPENING,
                'amount' => $openingAmount,
                'description' => 'Abertura de caixa',
                'happened_at' => $openedAt,
                'created_by' => $operatorId,
            ]);

            return $record;
        });

        $session->load(['opener:id,name,email', 'closer:id,name,email', 'movements.creator:id,name,email']);

        return response()->json($this->presentDetail($session), 201);
    }

    public function addMovement(Request $request, CashRegisterSession $cashRegister): JsonResponse
    {
        $this->ensureSessionBelongsToCurrentScope($cashRegister);

        $payload = $request->validate([
            'type' => ['required', 'string', 'in:sangria,suprimento'],
            'valor' => ['required', 'numeric', 'gt:0'],
            'descricao' => ['nullable', 'string', 'max:1000'],
        ]);

        $operatorId = $request->user()?->id;

        DB::transaction(function () use ($cashRegister, $payload, $operatorId): void {
            $record = $this->scopedSessionQuery()
                ->lockForUpdate()
                ->findOrFail($cashRegister->id);

            if ($record->status !== CashRegisterSession::STATUS_OPEN) {
                throw ValidationException::withMessages([
                    'status' => ['Este caixa já foi fechado e não aceita novas movimentações.'],
                ]);
            }

            $amount = $this->roundMoney($payload['valor']);
            $type = (string) $payload['type'];

            if ($type === CashRegisterMovement::TYPE_WITHDRAWAL) {
                $record->sangria_amount = $this->roundMoney((float) $record->sangria_amount + $amount);
            } else {
                $record->suprimento_amount = $this->roundMoney((float) $record->suprimento_amount + $amount);
            }

            $paymentSummary = $this->computePaymentSummaryForSession($record);
            $record->cash_received_amount = $paymentSummary['dinheiro'] ?? 0.0;
            $record->expected_amount = $this->computeExpectedAmount($record, (float) $record->cash_received_amount);
            $record->save();

            CashRegisterMovement::query()->create([
                'cash_register_session_id' => $record->id,
                'type' => $type,
                'amount' => $amount,
                'description' => trim((string) ($payload['descricao'] ?? '')) ?: null,
                'happened_at' => now(),
                'created_by' => $operatorId,
            ]);
        });

        $cashRegister->refresh()->load([
            'opener:id,name,email',
            'closer:id,name,email',
            'movements.creator:id,name,email',
        ]);

        return response()->json($this->presentDetail($cashRegister));
    }

    public function close(Request $request, CashRegisterSession $cashRegister): JsonResponse
    {
        $this->ensureSessionBelongsToCurrentScope($cashRegister);

        $payload = $request->validate([
            'valor_contado' => ['required', 'numeric', 'min:0'],
            'observacoes' => ['nullable', 'string', 'max:1000'],
        ]);

        $operatorId = $request->user()?->id;

        DB::transaction(function () use ($cashRegister, $payload, $operatorId): void {
            $record = $this->scopedSessionQuery()
                ->lockForUpdate()
                ->findOrFail($cashRegister->id);

            if ($record->status === CashRegisterSession::STATUS_CLOSED) {
                throw ValidationException::withMessages([
                    'status' => ['Este caixa já está fechado.'],
                ]);
            }

            $paymentSummary = $this->computePaymentSummaryForSession($record);
            $record->cash_received_amount = $paymentSummary['dinheiro'] ?? 0.0;
            $expectedAmount = $this->computeExpectedAmount($record, (float) $record->cash_received_amount);
            $countedAmount = $this->roundMoney($payload['valor_contado']);
            $differenceAmount = $this->roundMoney($countedAmount - $expectedAmount);

            $record->status = CashRegisterSession::STATUS_CLOSED;
            $record->expected_amount = $expectedAmount;
            $record->counted_amount = $countedAmount;
            $record->difference_amount = $differenceAmount;
            $record->closed_at = now();
            $record->closed_by = $operatorId;

            $extraNotes = trim((string) ($payload['observacoes'] ?? ''));
            if ($extraNotes !== '') {
                $record->observacoes = trim(implode(PHP_EOL, array_filter([
                    (string) $record->observacoes,
                    '[Fechamento] '.$extraNotes,
                ])));
            }

            $record->save();

            CashRegisterMovement::query()->create([
                'cash_register_session_id' => $record->id,
                'type' => CashRegisterMovement::TYPE_CLOSING,
                'amount' => $countedAmount,
                'description' => 'Fechamento de caixa',
                'happened_at' => now(),
                'created_by' => $operatorId,
            ]);
        });

        $cashRegister->refresh()->load([
            'opener:id,name,email',
            'closer:id,name,email',
            'movements.creator:id,name,email',
        ]);

        return response()->json($this->presentDetail($cashRegister));
    }

    private function presentOpenCard(CashRegisterSession $session): array
    {
        return [
            'id' => $session->id,
            'terminal_nome' => $session->terminal_nome,
            'terminal_codigo' => $session->terminal_codigo,
            'status' => $session->status,
            'status_label' => $session->status === CashRegisterSession::STATUS_OPEN ? 'Aberto' : 'Fechado',
            'opened_at' => $session->opened_at?->toIso8601String(),
            'opening_amount' => (float) $session->opening_amount,
        ];
    }

    private function presentHistoryRow(CashRegisterSession $session): array
    {
        $paymentSummary = $session->status === CashRegisterSession::STATUS_OPEN
            ? $this->computePaymentSummaryForSession($session)
            : null;
        $expectedAmount = $session->status === CashRegisterSession::STATUS_OPEN
            ? $this->computeExpectedAmount($session, $paymentSummary['dinheiro'] ?? null)
            : (float) $session->expected_amount;

        return [
            'id' => $session->id,
            'terminal_nome' => $session->terminal_nome,
            'terminal_codigo' => $session->terminal_codigo,
            'status' => $session->status,
            'opened_at' => $session->opened_at?->toIso8601String(),
            'closed_at' => $session->closed_at?->toIso8601String(),
            'opening_amount' => (float) $session->opening_amount,
            'expected_amount' => $expectedAmount,
            'counted_amount' => $session->counted_amount == null ? null : (float) $session->counted_amount,
            'difference_amount' => $session->difference_amount == null ? null : (float) $session->difference_amount,
            'opened_by' => $session->opener ? [
                'id' => $session->opener->id,
                'name' => $session->opener->name,
                'email' => $session->opener->email,
            ] : null,
        ];
    }

    private function presentDetail(CashRegisterSession $session): array
    {
        $session->loadMissing([
            'opener:id,name,email',
            'closer:id,name,email',
            'movements.creator:id,name,email',
        ]);
        $paymentSummary = $this->computePaymentSummaryForSession($session);
        $cashReceivedAmount = $paymentSummary['dinheiro'];
        $expectedAmount = $session->status === CashRegisterSession::STATUS_OPEN
            ? $this->computeExpectedAmount($session, $cashReceivedAmount)
            : (float) $session->expected_amount;
        $salePaymentMovements = $this->buildSalePaymentMovementsForSession($session);
        $movements = $session->movements
            ->map(fn (CashRegisterMovement $movement) => [
                'id' => $movement->id,
                'type' => $movement->type,
                'label' => $this->movementLabel($movement->type),
                'amount' => (float) $movement->amount,
                'signed_amount' => $this->movementSignedAmount($movement),
                'description' => $movement->description,
                'happened_at' => $movement->happened_at?->toIso8601String(),
                'created_by' => $movement->creator ? [
                    'id' => $movement->creator->id,
                    'name' => $movement->creator->name,
                    'email' => $movement->creator->email,
                ] : null,
            ])
            ->values()
            ->merge($salePaymentMovements)
            ->sortByDesc(function (array $row): int {
                $timestamp = $row['happened_at'] ?? null;
                return $timestamp ? (strtotime((string) $timestamp) ?: 0) : 0;
            })
            ->values();

        return [
            'id' => $session->id,
            'terminal_nome' => $session->terminal_nome,
            'terminal_codigo' => $session->terminal_codigo,
            'status' => $session->status,
            'status_label' => $session->status === CashRegisterSession::STATUS_OPEN ? 'Aberto' : 'Fechado',
            'opened_at' => $session->opened_at?->toIso8601String(),
            'closed_at' => $session->closed_at?->toIso8601String(),
            'observacoes' => $session->observacoes,
            'opened_by' => $session->opener ? [
                'id' => $session->opener->id,
                'name' => $session->opener->name,
                'email' => $session->opener->email,
            ] : null,
            'closed_by' => $session->closer ? [
                'id' => $session->closer->id,
                'name' => $session->closer->name,
                'email' => $session->closer->email,
            ] : null,
            'summary' => [
                'opening_amount' => (float) $session->opening_amount,
                'cash_received_amount' => $cashReceivedAmount,
                'sangria_amount' => (float) $session->sangria_amount,
                'suprimento_amount' => (float) $session->suprimento_amount,
                'expected_amount' => $expectedAmount,
                'counted_amount' => $session->counted_amount == null ? null : (float) $session->counted_amount,
                'difference_amount' => $session->difference_amount == null ? null : (float) $session->difference_amount,
                'balance_amount' => $expectedAmount,
            ],
            'payment_summary' => $paymentSummary,
            'movements' => $movements->all(),
        ];
    }

    private function movementLabel(string $type): string
    {
        return match ($type) {
            CashRegisterMovement::TYPE_OPENING => 'Abertura',
            CashRegisterMovement::TYPE_SUPPLY => 'Suprimento',
            CashRegisterMovement::TYPE_WITHDRAWAL => 'Sangria',
            CashRegisterMovement::TYPE_CLOSING => 'Fechamento',
            default => mb_convert_case($type, MB_CASE_TITLE, 'UTF-8'),
        };
    }

    private function movementSignedAmount(CashRegisterMovement $movement): float
    {
        $amount = (float) $movement->amount;

        if ($movement->type === CashRegisterMovement::TYPE_WITHDRAWAL) {
            return -$amount;
        }

        return $amount;
    }

    private function computeExpectedAmount(CashRegisterSession $session, ?float $cashReceived = null): float
    {
        $opening = (float) $session->opening_amount;
        $cashReceived = $cashReceived == null
            ? (float) $session->cash_received_amount
            : $this->roundMoney($cashReceived);
        $suprimento = (float) $session->suprimento_amount;
        $sangria = (float) $session->sangria_amount;

        return $this->roundMoney($opening + $cashReceived + $suprimento - $sangria);
    }

    private function computePaymentSummaryForSession(CashRegisterSession $session): array
    {
        $range = $this->resolveSessionDateRange($session);

        if (! $range['start']) {
            return [
                'dinheiro' => 0.0,
                'pix' => 0.0,
                'debito' => 0.0,
                'credito' => 0.0,
            ];
        }

        $summary = [
            'dinheiro' => 0.0,
            'pix' => 0.0,
            'debito' => 0.0,
            'credito' => 0.0,
        ];

        $payments = SalePayment::query()
            ->select([
                'sale_payments.metodo_nome',
                'sale_payments.descricao',
                'sale_payments.valor',
            ])
            ->join('sales', 'sales.id', '=', 'sale_payments.sale_id')
            ->where('sales.status', Sale::STATUS_FINALIZED)
            ->whereNotNull('sales.sold_at')
            ->whereBetween('sales.sold_at', [$range['start'], $range['end']])
            ->when(config('pdv.mode') === 'erp', function ($query) use ($session): void {
                $query
                    ->where('sales.grupo_empresarial_id', $session->grupo_empresarial_id)
                    ->where('sales.estabelecimento_id', $session->estabelecimento_id);
            })
            ->get();

        foreach ($payments as $payment) {
            $amount = $this->roundMoney((float) $payment->valor);
            $bucket = $this->resolvePaymentBucket(
                (string) $payment->metodo_nome,
                (string) ($payment->descricao ?? ''),
            );
            if (! array_key_exists($bucket, $summary)) {
                continue;
            }
            $summary[$bucket] = $this->roundMoney($summary[$bucket] + $amount);
        }

        return $summary;
    }

    private function buildSalePaymentMovementsForSession(CashRegisterSession $session): Collection
    {
        $range = $this->resolveSessionDateRange($session);
        if (! $range['start']) {
            return collect();
        }

        $payments = SalePayment::query()
            ->select([
                'sale_payments.id',
                'sale_payments.metodo_nome',
                'sale_payments.descricao',
                'sale_payments.valor',
                'sales.numero as sale_numero',
                'sales.sold_at as sold_at',
            ])
            ->join('sales', 'sales.id', '=', 'sale_payments.sale_id')
            ->where('sales.status', Sale::STATUS_FINALIZED)
            ->whereNotNull('sales.sold_at')
            ->whereBetween('sales.sold_at', [$range['start'], $range['end']])
            ->when(config('pdv.mode') === 'erp', function ($query) use ($session): void {
                $query
                    ->where('sales.grupo_empresarial_id', $session->grupo_empresarial_id)
                    ->where('sales.estabelecimento_id', $session->estabelecimento_id);
            })
            ->orderByDesc('sales.sold_at')
            ->get();

        return $payments->map(function ($payment): array {
            $method = trim((string) $payment->metodo_nome);
            $amount = $this->roundMoney((float) $payment->valor);
            $description = trim((string) ($payment->descricao ?? ''));

            $movementDescription = "Venda #{$payment->sale_numero} • {$method}";
            if ($description !== '') {
                $movementDescription .= " • {$description}";
            }

            return [
                'id' => "sale-payment-{$payment->id}",
                'type' => 'venda',
                'label' => 'Venda',
                'amount' => $amount,
                'signed_amount' => $amount,
                'description' => $movementDescription,
                'happened_at' => $payment->sold_at ? Carbon::parse($payment->sold_at)->toIso8601String() : null,
                'created_by' => null,
            ];
        })->values();
    }

    private function resolveSessionDateRange(CashRegisterSession $session): array
    {
        $start = $session->opened_at;
        if (! $start) {
            return ['start' => null, 'end' => null];
        }

        $end = $session->closed_at ?? now();

        return [
            'start' => $start->copy(),
            'end' => $end->copy(),
        ];
    }

    private function resolvePaymentBucket(string $methodName, string $description = ''): string
    {
        $text = mb_strtolower(trim("{$methodName} {$description}"), 'UTF-8');

        if ($text === '') {
            return 'dinheiro';
        }

        if (str_contains($text, 'pix')) {
            return 'pix';
        }

        if (str_contains($text, 'debito') || str_contains($text, 'débito')) {
            return 'debito';
        }

        if (str_contains($text, 'credito') || str_contains($text, 'crédito')) {
            return 'credito';
        }

        if (str_contains($text, 'dinheiro') || str_contains($text, 'cash')) {
            return 'dinheiro';
        }

        return 'dinheiro';
    }

    private function ensureSessionBelongsToCurrentScope(CashRegisterSession $session): void
    {
        if (config('pdv.mode') !== 'erp') {
            return;
        }

        $scope = $this->currentScope();
        if ((string) $session->estabelecimento_id === (string) $scope['estabelecimento_id']) {
            return;
        }

        abort(404);
    }

    private function scopedSessionQuery(): Builder
    {
        $query = CashRegisterSession::query();

        if (config('pdv.mode') !== 'erp') {
            return $query;
        }

        $scope = $this->currentScope();

        return $query
            ->where('grupo_empresarial_id', $scope['grupo_id'])
            ->where('estabelecimento_id', $scope['estabelecimento_id']);
    }

    private function scopedTerminalQuery(): Builder
    {
        $query = PosTerminal::query();

        if (config('pdv.mode') !== 'erp') {
            return $query;
        }

        $scope = $this->currentScope();

        return $query
            ->where('grupo_empresarial_id', $scope['grupo_id'])
            ->where('estabelecimento_id', $scope['estabelecimento_id']);
    }

    private function currentScope(): array
    {
        if (config('pdv.mode') !== 'erp') {
            return [
                'grupo_id' => null,
                'estabelecimento_id' => null,
            ];
        }

        $groupId = $this->companyContext->currentGroupId();
        $establishmentId = $this->companyContext->currentEstablishmentId();

        if (! $groupId || ! $establishmentId) {
            abort(409, 'Selecione uma filial no ERP para usar o PDV.');
        }

        return [
            'grupo_id' => (string) $groupId,
            'estabelecimento_id' => (string) $establishmentId,
        ];
    }

    private function roundMoney(float|int|string $value): float
    {
        return round((float) $value, 2);
    }
}
