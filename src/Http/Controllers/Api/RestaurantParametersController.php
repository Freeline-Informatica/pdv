<?php

namespace Freeline\Pdv\Http\Controllers\Api;

use Freeline\Pdv\Http\Controllers\Controller;
use Freeline\Pdv\Models\CompanySetting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class RestaurantParametersController extends Controller
{
    private const OPERATION_MODES = ['automatic', 'manual', 'hybrid'];

    private const TABLE_MODES = ['automatic', 'manual', 'disabled'];

    private const TICKET_MODES = ['automatic', 'manual', 'disabled'];

    private const CODE_GENERATION_TYPES = ['continuous', 'daily', 'random'];

    public function show(): JsonResponse
    {
        if (config('pdv.mode') === 'erp') {
            return response()->json([
                'restaurant_parameters' => $this->normalizeParameters(null),
            ]);
        }

        $record = CompanySetting::query()->first();

        return response()->json([
            'restaurant_parameters' => $this->normalizeParameters($record?->restaurant_parameters),
        ]);
    }

    public function upsert(Request $request): JsonResponse
    {
        if (config('pdv.mode') === 'erp') {
            return response()->json([
                'message' => 'Parâmetros de restaurante são gerenciados pelo ERP neste modo.',
            ], 409);
        }

        $payload = $request->validate([
            'operation_mode' => ['nullable', Rule::in(self::OPERATION_MODES)],
            'tables' => ['nullable', 'array'],
            'tables.mode' => ['nullable', Rule::in(self::TABLE_MODES)],
            'tables.quantity' => ['nullable', 'integer', 'min:0'],
            'tables.prefix' => ['nullable', 'string', 'max:30'],
            'tables.start_number' => ['nullable', 'integer', 'min:0'],
            'tables.padding' => ['nullable', 'integer', 'min:1', 'max:6'],
            'tables.allow_manual_rename' => ['nullable', 'boolean'],
            'tables.allow_blocking' => ['nullable', 'boolean'],
            'tables.use_capacity' => ['nullable', 'boolean'],
            'tables.default_capacity' => ['nullable', 'integer', 'min:1', 'max:999'],
            'tables.allow_create_during_service' => ['nullable', 'boolean'],
            'tables.allow_temporary_table' => ['nullable', 'boolean'],
            'tabs_or_tickets' => ['nullable', 'array'],
            'tabs_or_tickets.mode' => ['nullable', Rule::in(self::TICKET_MODES)],
            'tabs_or_tickets.allow_without_table' => ['nullable', 'boolean'],
            'tabs_or_tickets.require_table' => ['nullable', 'boolean'],
            'tabs_or_tickets.allow_multiple_per_table' => ['nullable', 'boolean'],
            'tabs_or_tickets.code_generation_type' => ['nullable', Rule::in(self::CODE_GENERATION_TYPES)],
            'tabs_or_tickets.prefix' => ['nullable', 'string', 'max:30'],
            'tabs_or_tickets.start_number' => ['nullable', 'integer', 'min:0'],
            'tabs_or_tickets.padding' => ['nullable', 'integer', 'min:1', 'max:6'],
            'tabs_or_tickets.random_code_length' => ['nullable', 'integer', 'min:3', 'max:10'],
            'tabs_or_tickets.quantity' => ['nullable', 'integer', 'min:0'],
            'tabs_or_tickets.reuse_after_closing' => ['nullable', 'boolean'],
            'tabs_or_tickets.allow_blocking' => ['nullable', 'boolean'],
        ], [
            'tables.quantity.min' => 'A quantidade de mesas não pode ser negativa.',
            'tabs_or_tickets.quantity.min' => 'A quantidade de fichas/comandas não pode ser negativa.',
            'tables.padding.min' => 'O preenchimento de mesas deve ser entre 1 e 6.',
            'tables.padding.max' => 'O preenchimento de mesas deve ser entre 1 e 6.',
            'tabs_or_tickets.padding.min' => 'O preenchimento de fichas/comandas deve ser entre 1 e 6.',
            'tabs_or_tickets.padding.max' => 'O preenchimento de fichas/comandas deve ser entre 1 e 6.',
            'tabs_or_tickets.random_code_length.min' => 'O tamanho do código aleatório deve ser entre 3 e 10.',
            'tabs_or_tickets.random_code_length.max' => 'O tamanho do código aleatório deve ser entre 3 e 10.',
        ]);

        $parameters = $this->normalizeParameters($payload);
        $errors = $this->validateBusinessRules($parameters);

        if ($errors !== []) {
            return response()->json([
                'message' => 'Verifique os parâmetros informados.',
                'errors' => $errors,
            ], 422);
        }

        $record = CompanySetting::query()->first() ?? new CompanySetting();
        $record->restaurant_parameters = $parameters;
        $record->save();

        return response()->json([
            'message' => 'Parâmetros do restaurante salvos com sucesso.',
            'restaurant_parameters' => $parameters,
        ]);
    }

    private function validateBusinessRules(array $parameters): array
    {
        $errors = [];

        $mode = $parameters['operation_mode'];
        $tables = $parameters['tables'];
        $tickets = $parameters['tabs_or_tickets'];

        if (($tickets['allow_without_table'] ?? false) && ($tickets['require_table'] ?? false)) {
            $errors['tabs_or_tickets.require_table'] = ['Se permitir ficha sem mesa, a mesa não pode ser obrigatória.'];
        }

        if ($mode === 'manual') {
            if ((int) ($tables['quantity'] ?? 0) <= 0) {
                $errors['tables.quantity'] = ['No modo manual, a quantidade de mesas deve ser maior que zero.'];
            }

            if ((int) ($tickets['quantity'] ?? 0) <= 0) {
                $errors['tabs_or_tickets.quantity'] = ['No modo manual, a quantidade de fichas/comandas deve ser maior que zero.'];
            }
        }

        if ($mode === 'hybrid' && (int) ($tables['quantity'] ?? 0) <= 0) {
            $errors['tables.quantity'] = ['No modo híbrido, a quantidade de mesas deve ser maior que zero.'];
        }

        if (($tickets['code_generation_type'] ?? 'continuous') === 'random') {
            $length = (int) ($tickets['random_code_length'] ?? 0);
            if ($length < 3 || $length > 10) {
                $errors['tabs_or_tickets.random_code_length'] = ['O tamanho do código aleatório deve ser entre 3 e 10.'];
            }
        }

        return $errors;
    }

    private function normalizeParameters(mixed $payload): array
    {
        $input = is_array($payload) ? $payload : [];

        $mode = (string) ($input['operation_mode'] ?? 'automatic');
        if (! in_array($mode, self::OPERATION_MODES, true)) {
            $mode = 'automatic';
        }

        $tablesInput = is_array($input['tables'] ?? null) ? $input['tables'] : [];
        $ticketsInput = is_array($input['tabs_or_tickets'] ?? null) ? $input['tabs_or_tickets'] : [];

        $tablesMode = (string) ($tablesInput['mode'] ?? 'automatic');
        if (! in_array($tablesMode, self::TABLE_MODES, true)) {
            $tablesMode = 'automatic';
        }

        $ticketsMode = (string) ($ticketsInput['mode'] ?? 'automatic');
        if (! in_array($ticketsMode, self::TICKET_MODES, true)) {
            $ticketsMode = 'automatic';
        }

        if ($mode === 'automatic') {
            $tablesMode = 'automatic';
            $ticketsMode = 'automatic';
        }

        if ($mode === 'manual') {
            $tablesMode = 'manual';
            $ticketsMode = 'manual';
        }

        if ($mode === 'hybrid') {
            $tablesMode = 'manual';
            $ticketsMode = 'automatic';
        }

        $codeGenerationType = (string) ($ticketsInput['code_generation_type'] ?? 'continuous');
        if (! in_array($codeGenerationType, self::CODE_GENERATION_TYPES, true)) {
            $codeGenerationType = 'continuous';
        }

        $allowWithoutTable = (bool) ($ticketsInput['allow_without_table'] ?? false);
        $requireTable = (bool) ($ticketsInput['require_table'] ?? true);
        if ($allowWithoutTable) {
            $requireTable = false;
        }

        return [
            'operation_mode' => $mode,
            'tables' => [
                'mode' => $tablesMode,
                'quantity' => $this->normalizeInt($tablesInput['quantity'] ?? 20, 20, 0),
                'prefix' => $this->normalizeShortText($tablesInput['prefix'] ?? 'Mesa', 'Mesa', 30),
                'start_number' => $this->normalizeInt($tablesInput['start_number'] ?? 1, 1, 0),
                'padding' => $this->normalizeInt($tablesInput['padding'] ?? 2, 2, 1, 6),
                'allow_manual_rename' => (bool) ($tablesInput['allow_manual_rename'] ?? false),
                'allow_blocking' => (bool) ($tablesInput['allow_blocking'] ?? true),
                'use_capacity' => (bool) ($tablesInput['use_capacity'] ?? false),
                'default_capacity' => $this->normalizeInt($tablesInput['default_capacity'] ?? 4, 4, 1, 999),
                'allow_create_during_service' => (bool) ($tablesInput['allow_create_during_service'] ?? true),
                'allow_temporary_table' => (bool) ($tablesInput['allow_temporary_table'] ?? true),
                'future_statuses' => [
                    'livre',
                    'ocupada',
                    'reservada',
                    'bloqueada',
                ],
            ],
            'tabs_or_tickets' => [
                'mode' => $ticketsMode,
                'allow_without_table' => $allowWithoutTable,
                'require_table' => $requireTable,
                'allow_multiple_per_table' => (bool) ($ticketsInput['allow_multiple_per_table'] ?? true),
                'code_generation_type' => $codeGenerationType,
                'prefix' => $this->normalizeShortText($ticketsInput['prefix'] ?? ($ticketsMode === 'manual' ? 'Ficha' : 'CMD'), $ticketsMode === 'manual' ? 'Ficha' : 'CMD', 30),
                'start_number' => $this->normalizeInt($ticketsInput['start_number'] ?? 1, 1, 0),
                'padding' => $this->normalizeInt($ticketsInput['padding'] ?? ($ticketsMode === 'manual' ? 3 : 4), $ticketsMode === 'manual' ? 3 : 4, 1, 6),
                'random_code_length' => $this->normalizeInt($ticketsInput['random_code_length'] ?? 4, 4, 3, 10),
                'quantity' => $this->normalizeInt($ticketsInput['quantity'] ?? 100, 100, 0),
                'reuse_after_closing' => (bool) ($ticketsInput['reuse_after_closing'] ?? true),
                'allow_blocking' => (bool) ($ticketsInput['allow_blocking'] ?? true),
                'future_statuses' => [
                    'livre',
                    'em_uso',
                    'fechada',
                    'cancelada',
                    'bloqueada',
                ],
            ],
        ];
    }

    private function normalizeInt(mixed $value, int $fallback, int $min, ?int $max = null): int
    {
        if (! is_numeric($value)) {
            return $fallback;
        }

        $normalized = (int) $value;

        if ($normalized < $min) {
            $normalized = $min;
        }

        if ($max !== null && $normalized > $max) {
            $normalized = $max;
        }

        return $normalized;
    }

    private function normalizeShortText(mixed $value, string $fallback, int $maxLength): string
    {
        $text = trim((string) $value);

        if ($text === '') {
            $text = $fallback;
        }

        if (mb_strlen($text) > $maxLength) {
            $text = mb_substr($text, 0, $maxLength);
        }

        return $text;
    }
}
