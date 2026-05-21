<?php

namespace Freeline\Pdv\Http\Controllers\Api;

use Freeline\Pdv\Http\Controllers\Controller;
use Freeline\Pdv\Models\Category;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CategoriesController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Category::query()->orderBy('nome');

        if ($request->boolean('active_only')) {
            $query->where('ativo', true);
        }

        return response()->json($query->get());
    }

    public function store(Request $request): JsonResponse
    {
        $payload = $request->validate([
            'nome' => ['required', 'string', 'max:255'],
            'descricao' => ['nullable', 'string'],
            'ativo' => ['required', 'boolean'],
        ]);

        $record = Category::create($payload);

        return response()->json($record, 201);
    }

    public function update(Request $request, Category $category): JsonResponse
    {
        $payload = $request->validate([
            'nome' => ['required', 'string', 'max:255'],
            'descricao' => ['nullable', 'string'],
            'ativo' => ['required', 'boolean'],
        ]);

        $category->update($payload);

        return response()->json($category);
    }

    public function destroy(Category $category): JsonResponse
    {
        $category->delete();

        return response()->json(['message' => 'Categoria removida.']);
    }
}
