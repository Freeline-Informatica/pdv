<?php

namespace Freeline\Pdv\Http\Controllers\Api;

use Freeline\Pdv\Http\Controllers\Controller;
use Freeline\Pdv\Models\Product;
use Freeline\Pdv\Services\CatalogProductMirror;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;

class ProductsController extends Controller
{
    public function index(Request $request, CatalogProductMirror $catalogProductMirror): JsonResponse
    {
        $limit = $request->filled('limit')
            ? max(1, min($request->integer('limit'), 100))
            : 100;

        $catalogProductMirror->sync(
            $request->string('search')->toString(),
            $limit,
        );

        $query = Product::query()->with('category:id,nome')->orderBy('nome');

        if ($request->boolean('active_only')) {
            $query->where('ativo', true);
        }

        if ($request->filled('category_id')) {
            $query->where('category_id', $request->string('category_id')->toString());
        }

        if ($request->filled('search')) {
            $needle = mb_strtolower(trim($request->string('search')->toString()));
            $query->where(function ($builder) use ($needle): void {
                $builder->whereRaw('LOWER(nome) like ?', ["%{$needle}%"])
                    ->orWhereRaw('LOWER(COALESCE(codigo, \'\')) like ?', ["%{$needle}%"]);
            });
        }

        if ($request->filled('limit')) {
            $query->limit($limit);
        }

        return response()->json($query->get());
    }

    public function store(Request $request): JsonResponse
    {
        $payload = $this->validatePayload($request);
        $payload = $this->applyImagePayload($request, $payload);

        $record = Product::create($payload);

        return response()->json($record->load('category:id,nome'), 201);
    }

    public function update(Request $request, Product $product): JsonResponse
    {
        $payload = $this->validatePayload($request);
        $payload = $this->applyImagePayload($request, $payload, $product);

        $product->update($payload);

        return response()->json($product->load('category:id,nome'));
    }

    public function destroy(Product $product): JsonResponse
    {
        $this->deleteManagedProductImage($product->imagem_url);
        $product->delete();

        return response()->json(['message' => 'Produto removido.']);
    }

    private function validatePayload(Request $request): array
    {
        $restaurantConfigInput = $request->input('restaurant_config');
        if (is_string($restaurantConfigInput) && trim($restaurantConfigInput) !== '') {
            $decodedConfig = json_decode($restaurantConfigInput, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decodedConfig)) {
                $request->merge([
                    'restaurant_config' => $decodedConfig,
                ]);
            }
        }

        $payload = $request->validate([
            'nome' => ['required', 'string', 'max:255'],
            'codigo' => ['nullable', 'string', 'max:255'],
            'preco_venda' => ['required', 'numeric', 'min:0'],
            'preco_custo' => ['nullable', 'numeric', 'min:0'],
            'unidade' => ['required', 'string', 'max:10'],
            'estoque_atual' => ['required', 'numeric'],
            'estoque_minimo' => ['nullable', 'numeric'],
            'category_id' => ['nullable', 'uuid', 'exists:categories,id'],
            'ativo' => ['required', 'boolean'],
            'observacoes' => ['nullable', 'string'],
            'imagem_url' => ['nullable', 'string', 'max:2048'],
            'imagem_file' => ['nullable', 'file', 'image', 'mimes:jpg,jpeg,png,webp,avif', 'max:5120'],
            'remover_imagem' => ['nullable', 'boolean'],
            'restaurant_config' => ['nullable', 'array'],
            'restaurant_config.fiscal_group' => ['nullable', 'string', 'max:80'],
            'restaurant_config.predefined_options' => ['nullable', 'array'],
            'restaurant_config.predefined_options.*' => ['nullable', 'string', 'max:80'],
            'restaurant_config.custom_options' => ['nullable', 'array'],
            'restaurant_config.custom_options.*' => ['nullable', 'string', 'max:120'],
        ]);

        $payload['restaurant_config'] = $this->sanitizeRestaurantConfig($payload['restaurant_config'] ?? null);

        return $payload;
    }

    private function sanitizeRestaurantConfig(mixed $config): ?array
    {
        if (! is_array($config)) {
            return null;
        }

        $fiscalGroup = trim((string) ($config['fiscal_group'] ?? ''));
        $predefined = collect($config['predefined_options'] ?? [])
            ->map(fn ($value) => trim((string) $value))
            ->filter()
            ->unique()
            ->values()
            ->all();

        $custom = collect($config['custom_options'] ?? [])
            ->map(fn ($value) => trim((string) $value))
            ->filter()
            ->unique()
            ->values()
            ->all();

        if ($fiscalGroup === '' && ! count($predefined) && ! count($custom)) {
            return null;
        }

        return [
            'fiscal_group' => $fiscalGroup !== '' ? $fiscalGroup : null,
            'predefined_options' => $predefined,
            'custom_options' => $custom,
        ];
    }

    private function applyImagePayload(Request $request, array $payload, ?Product $product = null): array
    {
        $currentImageUrl = $product?->imagem_url;
        $hasImageUrlKey = array_key_exists('imagem_url', $payload);
        $removeImage = array_key_exists('remover_imagem', $payload) ? (bool) $payload['remover_imagem'] : false;
        $imageFile = $request->file('imagem_file');

        unset($payload['imagem_file'], $payload['remover_imagem']);

        if ($imageFile instanceof UploadedFile) {
            $payload['imagem_url'] = $this->storeProductImage($imageFile);
            $this->deleteManagedProductImage($currentImageUrl);

            return $payload;
        }

        if ($removeImage) {
            $payload['imagem_url'] = null;
            $this->deleteManagedProductImage($currentImageUrl);

            return $payload;
        }

        if ($hasImageUrlKey) {
            $normalizedImageUrl = trim((string) $payload['imagem_url']);
            $payload['imagem_url'] = $normalizedImageUrl !== '' ? $normalizedImageUrl : null;

            if ($payload['imagem_url'] !== $currentImageUrl) {
                $this->deleteManagedProductImage($currentImageUrl);
            }

            return $payload;
        }

        return $payload;
    }

    private function storeProductImage(UploadedFile $file): string
    {
        $directory = public_path('uploads/products');
        if (! is_dir($directory)) {
            mkdir($directory, 0755, true);
        }

        $extension = strtolower($file->getClientOriginalExtension() ?: $file->extension() ?: 'jpg');
        $fileName = Str::uuid()->toString().'.'.$extension;
        $file->move($directory, $fileName);

        return '/uploads/products/'.$fileName;
    }

    private function deleteManagedProductImage(?string $url): void
    {
        $filePath = $this->managedProductImagePath($url);
        if (! $filePath || ! is_file($filePath)) {
            return;
        }

        @unlink($filePath);
    }

    private function managedProductImagePath(?string $url): ?string
    {
        if (! $url) {
            return null;
        }

        $path = parse_url($url, PHP_URL_PATH);
        if (! is_string($path) || ! str_starts_with($path, '/uploads/products/')) {
            return null;
        }

        return public_path(ltrim($path, '/'));
    }
}
