<?php

namespace Freeline\Pdv\Http\Controllers\Api;

use Freeline\Pdv\Contracts\CompanyContextResolver;
use Freeline\Pdv\Http\Controllers\Controller;
use Freeline\Pdv\Models\AuditLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AuditLogsController extends Controller
{
    public function __construct(
        private readonly CompanyContextResolver $companyContext,
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:160'],
            'action' => ['nullable', 'string', 'max:120'],
            'entity' => ['nullable', 'string', 'max:120'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:200'],
        ]);

        $perPage = (int) ($filters['per_page'] ?? 20);

        $query = $this->scopedAuditQuery()->orderByDesc('created_at');

        if (! empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($builder) use ($search): void {
                $builder
                    ->where('action_label', 'ilike', "%{$search}%")
                    ->orWhere('entity', 'ilike', "%{$search}%")
                    ->orWhere('operator_name', 'ilike', "%{$search}%")
                    ->orWhere('operator_code', 'ilike', "%{$search}%")
                    ->orWhere('details', 'ilike', "%{$search}%");
            });
        }

        if (! empty($filters['action'])) {
            $query->where('action_key', $filters['action']);
        }

        if (! empty($filters['entity'])) {
            $query->where('entity', $filters['entity']);
        }

        $logs = $query->paginate($perPage);

        $actions = $this->scopedAuditQuery()
            ->select(['action_key', 'action_label'])
            ->distinct()
            ->orderBy('action_label')
            ->get()
            ->map(fn (AuditLog $item) => [
                'key' => $item->action_key,
                'label' => $item->action_label,
            ])
            ->values();

        $entities = $this->scopedAuditQuery()
            ->select(['entity'])
            ->distinct()
            ->orderBy('entity')
            ->pluck('entity')
            ->values();

        return response()->json([
            'data' => collect($logs->items())->map(fn (AuditLog $item) => [
                'id' => $item->id,
                'grupo_empresarial_id' => $item->grupo_empresarial_id,
                'estabelecimento_id' => $item->estabelecimento_id,
                'created_at' => $item->created_at?->toIso8601String(),
                'action_key' => $item->action_key,
                'action_label' => $item->action_label,
                'entity' => $item->entity,
                'entity_id' => $item->entity_id,
                'operator' => [
                    'id' => $item->operator_id,
                    'nome' => $item->operator_name,
                    'codigo' => $item->operator_code,
                    'perfil' => $item->operator_role,
                ],
                'details' => $item->details,
            ]),
            'meta' => [
                'current_page' => $logs->currentPage(),
                'last_page' => $logs->lastPage(),
                'per_page' => $logs->perPage(),
                'total' => $logs->total(),
            ],
            'filters' => [
                'actions' => $actions,
                'entities' => $entities,
            ],
        ]);
    }

    private function scopedAuditQuery()
    {
        $query = AuditLog::query();

        if (config('pdv.mode') !== 'erp') {
            return $query;
        }

        $groupId = $this->companyContext->currentGroupId();
        $establishmentId = $this->companyContext->currentEstablishmentId();

        if (! $groupId || ! $establishmentId) {
            abort(409, 'Selecione uma filial no ERP para usar o PDV.');
        }

        return $query
            ->where('grupo_empresarial_id', (string) $groupId)
            ->where('estabelecimento_id', (string) $establishmentId);
    }
}
