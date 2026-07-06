<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('produto')) {
            return;
        }

        Schema::table('produto', function (Blueprint $table): void {
            if (! Schema::hasColumn('produto', 'tipo_item')) {
                $table->string('tipo_item', 2)->default('00')->index('produto_tipo_item_idx');
            }
            if (! Schema::hasColumn('produto', 'natureza_item')) {
                $table->string('natureza_item', 40)->default('MERCADORIA')->index('produto_natureza_item_idx');
            }
            if (! Schema::hasColumn('produto', 'ncm')) {
                $table->string('ncm', 20)->nullable()->index('produto_ncm_idx');
            }
            if (! Schema::hasColumn('produto', 'ncm_descricao')) {
                $table->string('ncm_descricao', 120)->nullable();
            }
            if (! Schema::hasColumn('produto', 'cest')) {
                $table->string('cest', 20)->nullable()->index('produto_cest_idx');
            }
            if (! Schema::hasColumn('produto', 'origem_mercadoria')) {
                $table->unsignedTinyInteger('origem_mercadoria')->nullable();
            }
            if (! Schema::hasColumn('produto', 'servico_codigo')) {
                $table->string('servico_codigo', 20)->nullable()->index('produto_servico_codigo_idx');
            }
            if (! Schema::hasColumn('produto', 'codigo_nbs')) {
                $table->string('codigo_nbs', 20)->nullable()->index('produto_codigo_nbs_idx');
            }
            if (! Schema::hasColumn('produto', 'cod_classe_tributo')) {
                $table->string('cod_classe_tributo', 20)->nullable();
            }
            if (! Schema::hasColumn('produto', 'ipi_classe')) {
                $table->string('ipi_classe', 20)->nullable();
            }
            if (! Schema::hasColumn('produto', 'ipi_cod_enquadramento')) {
                $table->string('ipi_cod_enquadramento', 20)->nullable();
            }
            if (! Schema::hasColumn('produto', 'ipi_selo_cod')) {
                $table->string('ipi_selo_cod', 20)->nullable();
            }
            if (! Schema::hasColumn('produto', 'cod_iat')) {
                $table->string('cod_iat', 20)->nullable();
            }
            if (! Schema::hasColumn('produto', 'cod_ippt')) {
                $table->string('cod_ippt', 20)->nullable();
            }
        });

        if (Schema::hasTable('produto_classificacao_mercadologica')) {
            Schema::table('produto_classificacao_mercadologica', function (Blueprint $table): void {
                if (! Schema::hasColumn('produto_classificacao_mercadologica', 'tipo_item_default')) {
                    $table->string('tipo_item_default', 2)->nullable();
                }
                if (! Schema::hasColumn('produto_classificacao_mercadologica', 'natureza_item_default')) {
                    $table->string('natureza_item_default', 40)->nullable();
                }
                if (! Schema::hasColumn('produto_classificacao_mercadologica', 'fiscal_tags_default')) {
                    $table->json('fiscal_tags_default')->nullable();
                }
            });
        }

        if (! Schema::hasTable('product_fiscal_tags')) {
            Schema::create('product_fiscal_tags', function (Blueprint $table): void {
                $table->uuid('id')->primary();
                $table->uuid('produto_id');
                $table->string('tag', 60);
                $table->timestamps();

                $table->unique(['produto_id', 'tag'], 'product_fiscal_tags_produto_tag_unique');
                $table->index('tag', 'product_fiscal_tags_tag_idx');
                $table->foreign('produto_id')->references('id')->on('produto')->cascadeOnDelete();
            });
        }

        $this->backfillProdutoFiscalContract();
    }

    public function down(): void
    {
        if (Schema::hasTable('product_fiscal_tags')) {
            Schema::dropIfExists('product_fiscal_tags');
        }

        if (Schema::hasTable('produto_classificacao_mercadologica')) {
            Schema::table('produto_classificacao_mercadologica', function (Blueprint $table): void {
                foreach (['tipo_item_default', 'natureza_item_default', 'fiscal_tags_default'] as $column) {
                    if (Schema::hasColumn('produto_classificacao_mercadologica', $column)) {
                        $table->dropColumn($column);
                    }
                }
            });
        }

        if (Schema::hasTable('produto')) {
            Schema::table('produto', function (Blueprint $table): void {
                foreach ([
                    'tipo_item',
                    'natureza_item',
                    'ncm',
                    'ncm_descricao',
                    'cest',
                    'origem_mercadoria',
                    'servico_codigo',
                    'codigo_nbs',
                    'cod_classe_tributo',
                    'ipi_classe',
                    'ipi_cod_enquadramento',
                    'ipi_selo_cod',
                    'cod_iat',
                    'cod_ippt',
                ] as $column) {
                    if (Schema::hasColumn('produto', $column)) {
                        $table->dropColumn($column);
                    }
                }
            });
        }
    }

    private function backfillProdutoFiscalContract(): void
    {
        $profileColumns = Schema::hasTable('fiscal_item_profiles');
        $productColumns = [
            'id',
            'produto_tipo',
            'tipo_item',
            'natureza_item',
            'ncm',
            'ncm_descricao',
            'cest',
            'origem_mercadoria',
            'servico_codigo',
            'cod_classe_tributo',
            'ipi_classe',
            'ipi_cod_enquadramento',
            'ipi_selo_cod',
            'cod_iat',
            'cod_ippt',
            'atributos_logisticos',
        ];

        foreach (['fiscal_item_profile_saida_id', 'fiscal_item_profile_id'] as $column) {
            if (Schema::hasColumn('produto', $column)) {
                $productColumns[] = $column;
            }
        }

        DB::table('produto')
            ->select($productColumns)
            ->orderBy('id')
            ->chunk(100, function ($products) use ($profileColumns): void {
                $profileIds = collect($products)
                    ->flatMap(fn ($product): array => [
                        $product->fiscal_item_profile_saida_id ?? null,
                        $product->fiscal_item_profile_id ?? null,
                    ])
                    ->filter()
                    ->unique()
                    ->values();

                $profiles = $profileColumns && $profileIds->isNotEmpty()
                    ? DB::table('fiscal_item_profiles')->whereIn('id', $profileIds)->get()->keyBy('id')
                    : collect();

                foreach ($products as $product) {
                    $attributes = $this->decodeJson($product->atributos_logisticos ?? null);
                    $profile = $profiles->get($product->fiscal_item_profile_saida_id ?? null)
                        ?: $profiles->get($product->fiscal_item_profile_id ?? null);

                    $tipoItem = $this->normalizeTipoItem(
                        $product->tipo_item ?: data_get($attributes, 'importacao_simples.tipo_sped')
                    );
                    $naturezaItem = $this->normalizeNaturezaItem($product->natureza_item);
                    $produtoTipo = $this->normalizeProdutoTipo($product->produto_tipo);
                    $serviceCode = $this->firstFilled(
                        $product->servico_codigo,
                        data_get($attributes, 'servico_codigo'),
                        data_get($attributes, 'importacao_simples.codigo_servico'),
                        $profile->servico_codigo ?? null,
                    );

                    $isService = $produtoTipo === 'SERVICO'
                        || $tipoItem === '09'
                        || $naturezaItem === 'SERVICO'
                        || $serviceCode !== null;

                    if ($isService) {
                        $updates = [
                            'produto_tipo' => 'SERVICO',
                            'tipo_item' => '09',
                            'natureza_item' => 'SERVICO',
                            'servico_codigo' => $serviceCode,
                            'ncm' => null,
                            'ncm_descricao' => null,
                            'cest' => null,
                            'origem_mercadoria' => null,
                            'ipi_classe' => null,
                            'ipi_cod_enquadramento' => null,
                            'ipi_selo_cod' => null,
                            'cod_iat' => null,
                            'cod_ippt' => null,
                            'updated_at' => now(),
                        ];
                    } else {
                        $updates = [
                            'produto_tipo' => $produtoTipo,
                            'tipo_item' => $tipoItem ?: '00',
                            'natureza_item' => $naturezaItem ?: 'MERCADORIA',
                            'ncm' => $this->digitsOrNull($this->firstFilled(
                                $product->ncm,
                                data_get($attributes, 'fiscal_ncm'),
                                $profile->ncm ?? null,
                            )),
                            'ncm_descricao' => $this->firstFilled($product->ncm_descricao, $profile->ncm_descricao ?? null),
                            'cest' => $this->digitsOrNull($this->firstFilled(
                                $product->cest,
                                data_get($attributes, 'fiscal_cest'),
                                $profile->cest ?? null,
                            )),
                            'origem_mercadoria' => $this->originOrNull($this->firstFilled(
                                $product->origem_mercadoria,
                                data_get($attributes, 'fiscal_origem'),
                                data_get($attributes, 'importacao_simples.origem_mercadoria'),
                                $profile->origem_mercadoria ?? null,
                            )),
                            'servico_codigo' => null,
                            'cod_classe_tributo' => $this->firstFilled(
                                $product->cod_classe_tributo,
                                data_get($attributes, 'fiscal_tax_classification_code'),
                                $profile->cod_classe_tributo ?? null,
                            ),
                            'ipi_classe' => $this->firstFilled($product->ipi_classe, $profile->ipi_classe ?? null),
                            'ipi_cod_enquadramento' => $this->firstFilled($product->ipi_cod_enquadramento, $profile->ipi_cod_enquadramento ?? null),
                            'ipi_selo_cod' => $this->firstFilled($product->ipi_selo_cod, $profile->ipi_selo_cod ?? null),
                            'cod_iat' => $this->firstFilled($product->cod_iat, data_get($attributes, 'importacao_simples.iat'), $profile->cod_iat ?? null),
                            'cod_ippt' => $this->firstFilled($product->cod_ippt, data_get($attributes, 'importacao_simples.ippt'), $profile->cod_ippt ?? null),
                            'updated_at' => now(),
                        ];
                    }

                    DB::table('produto')->where('id', $product->id)->update($updates);

                    if ($isService && Schema::hasTable('product_fiscal_tags')) {
                        $existingTag = DB::table('product_fiscal_tags')
                            ->where('produto_id', $product->id)
                            ->where('tag', 'SERVICO_ISS')
                            ->exists();

                        if (! $existingTag) {
                            DB::table('product_fiscal_tags')->insert([
                                'id' => (string) Str::uuid(),
                                'produto_id' => $product->id,
                                'tag' => 'SERVICO_ISS',
                                'created_at' => now(),
                                'updated_at' => now(),
                            ]);
                        }
                    }
                }
            });
    }

    private function decodeJson(mixed $value): array
    {
        if (is_array($value)) {
            return $value;
        }

        if (is_object($value)) {
            return (array) $value;
        }

        $decoded = json_decode((string) $value, true);

        return is_array($decoded) ? $decoded : [];
    }

    private function normalizeProdutoTipo(mixed $value): ?string
    {
        $value = mb_strtoupper(trim((string) $value));

        return match ($value) {
            'SERVICO', 'SERVIÇO', 'SERVICE' => 'SERVICO',
            'COMPOSTO', 'COMPOSITE' => 'COMPOSTO',
            'BASICO', 'BÁSICO', 'BASIC' => 'BASICO',
            'MERCADORIA', 'PRODUTO', 'PRODUCT', 'NORMAL' => 'NORMAL',
            default => $value !== '' ? $value : 'NORMAL',
        };
    }

    private function normalizeTipoItem(mixed $value): ?string
    {
        $digits = preg_replace('/\D+/', '', (string) $value);
        if ($digits === '') {
            return null;
        }

        return str_pad(substr($digits, -2), 2, '0', STR_PAD_LEFT);
    }

    private function normalizeNaturezaItem(mixed $value): ?string
    {
        $value = mb_strtoupper(trim((string) $value));

        return match ($value) {
            'SERVICO', 'SERVIÇO', 'SERVICE' => 'SERVICO',
            'PRODUTO', 'PRODUCT' => 'PRODUTO',
            'INSUMO' => 'INSUMO',
            'PATRIMONIO', 'PATRIMÔNIO' => 'PATRIMONIO',
            'EMBALAGEM' => 'EMBALAGEM',
            'MATERIAL_CONSUMO', 'USO_CONSUMO' => 'MATERIAL_CONSUMO',
            'MERCADORIA' => 'MERCADORIA',
            default => $value !== '' ? $value : 'MERCADORIA',
        };
    }

    private function firstFilled(mixed ...$values): ?string
    {
        foreach ($values as $value) {
            $normalized = trim((string) $value);
            if ($normalized !== '') {
                return $normalized;
            }
        }

        return null;
    }

    private function digitsOrNull(mixed $value): ?string
    {
        $digits = preg_replace('/\D+/', '', (string) $value);

        return $digits !== '' ? $digits : null;
    }

    private function originOrNull(mixed $value): ?int
    {
        $digits = preg_replace('/\D+/', '', (string) $value);
        if ($digits === '') {
            return null;
        }

        $origin = (int) substr($digits, 0, 1);

        return $origin >= 0 && $origin <= 8 ? $origin : null;
    }
};
