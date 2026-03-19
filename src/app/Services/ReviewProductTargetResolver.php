<?php

namespace Backpack\Reviews\app\Services;

use App\Models\Product;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class ReviewProductTargetResolver
{
    public function canonicalProduct(Product $product): Product
    {
        $canonicalId = $this->canonicalProductId($product);

        if ((int) $product->getKey() === $canonicalId) {
            return $product;
        }

        $loadedParent = $product->relationLoaded('parent') ? $product->parent : null;
        if ($loadedParent instanceof Product && (int) $loadedParent->getKey() === $canonicalId) {
            return $loadedParent;
        }

        return $this->productLookupQuery()->find($canonicalId) ?? $product;
    }

    public function canonicalProductId(Product|int $product): int
    {
        if (is_int($product)) {
            $resolved = $this->productLookupQuery()->select(['id', 'parent_id'])->find($product);

            return $resolved instanceof Product
                ? $this->canonicalProductId($resolved)
                : $product;
        }

        if (method_exists($product, 'getReviewableKey')) {
            return (int) $product->getReviewableKey();
        }

        return (int) ($product->parent_id ?: $product->getKey());
    }

    public function familyProductIds(Product|int $product): array
    {
        $resolved = is_int($product)
            ? $this->productLookupQuery()->find($product)
            : $product;

        if (!$resolved instanceof Product) {
            return is_int($product) && $product > 0 ? [$product] : [];
        }

        $canonical = $this->canonicalProduct($resolved);
        $children = $canonical->relationLoaded('children')
            ? $canonical->children
            : $canonical->children()
                ->without(['categories', 'ap', 'suppliers', 'parent'])
                ->get(['id', 'parent_id']);

        return collect([$resolved->getKey(), $canonical->getKey()])
            ->concat($children->pluck('id'))
            ->map(fn ($id) => (int) $id)
            ->filter(fn (int $id) => $id > 0)
            ->unique()
            ->values()
            ->all();
    }

    public function uniqueCanonicalProducts(Collection $products): Collection
    {
        return $products
            ->filter(fn ($product) => $product instanceof Product)
            ->map(fn (Product $product) => $this->canonicalProduct($product))
            ->unique(fn (Product $product) => (int) $product->getKey())
            ->values();
    }

    protected function productLookupQuery(): Builder
    {
        return Product::query()->without(['categories', 'ap', 'suppliers', 'parent']);
    }
}
