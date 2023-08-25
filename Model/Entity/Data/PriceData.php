<?php
declare(strict_types=1);

namespace Monogo\TypesenseCatalogProducts\Model\Entity\Data;

use Magento\Catalog\Api\Data\ProductInterface;
use Magento\CatalogGraphQl\Model\Resolver\Product\Price\Discount;
use Magento\CatalogGraphQl\Model\Resolver\Product\Price\ProviderPool as PriceProviderPool;
use Magento\Framework\Pricing\SaleableInterface;
use Magento\Store\Api\Data\StoreInterface;

use Magento\Catalog\Model\Product;

class PriceData
{
    /**
     * @var Discount
     */
    private Discount $discount;

    /**
     * @var PriceProviderPool
     */
    private PriceProviderPool $priceProviderPool;

    /**
     * @param PriceProviderPool $priceProviderPool
     * @param Discount $discount
     */
    public function __construct(
        PriceProviderPool $priceProviderPool,
        Discount $discount
    ) {
        $this->priceProviderPool = $priceProviderPool;
        $this->discount = $discount;
    }

    /**
     * @param Product $product
     * @return array
     */
    public function getPriceRange(Product $product):array
    {
        $store = $product->getStore();
        $returnArray = [];

        $returnArray['minimum_price'] = $this->canShowPrice($product) ?
            $this->getMinimumProductPrice($product, $store) : $this->formatEmptyResult();
        $returnArray['maximum_price'] = $this->canShowPrice($product) ?
            $this->getMaximumProductPrice($product, $store) : $this->formatEmptyResult();

        return $returnArray;
    }

    /**
     * Get formatted minimum product price
     *
     * @param SaleableInterface $product
     * @param StoreInterface $store
     * @return array
     */
    private function getMinimumProductPrice(SaleableInterface $product, StoreInterface $store): array
    {
        $priceProvider = $this->priceProviderPool->getProviderByProductType($product->getTypeId());
        return $this->formatPrice(
            (float)$priceProvider->getMinimalRegularPrice($product)->getValue(),
            (float)$priceProvider->getMinimalFinalPrice($product)->getValue(),
            $store
        );
    }

    /**
     * Get formatted maximum product price
     *
     * @param SaleableInterface $product
     * @param StoreInterface $store
     * @return array
     */
    private function getMaximumProductPrice(SaleableInterface $product, StoreInterface $store): array
    {
        $priceProvider = $this->priceProviderPool->getProviderByProductType($product->getTypeId());
        return $this->formatPrice(
            (float)$priceProvider->getMaximalRegularPrice($product)->getValue(),
            (float)$priceProvider->getMaximalFinalPrice($product)->getValue(),
            $store
        );
    }

    /**
     * Format price for GraphQl output
     *
     * @param float $regularPrice
     * @param float $finalPrice
     * @param StoreInterface $store
     * @return array
     */
    private function formatPrice(float $regularPrice, float $finalPrice, StoreInterface $store): array
    {
        return [
            'regular_price' => [
                'value' => $regularPrice,
                'currency' => $store->getCurrentCurrencyCode(),
            ],
            'final_price' => [
                'value' => $finalPrice,
                'currency' => $store->getCurrentCurrencyCode(),
            ],
            'discount' => $this->discount->getDiscountByDifference($regularPrice, $finalPrice),
        ];
    }

    /**
     * Check if the product is allowed to show price
     *
     * @param ProductInterface $product
     * @return bool
     */
    private function canShowPrice(ProductInterface $product): bool
    {
        return $product->hasData('can_show_price') ? $product->getData('can_show_price') : true;
    }

    /**
     * Format empty result
     *
     * @return array
     */
    private function formatEmptyResult(): array
    {
        return [
            'regular_price' => [
                'value' => null,
                'currency' => null,
            ],
            'final_price' => [
                'value' => null,
                'currency' => null,
            ],
            'discount' => null,
        ];
    }
}
