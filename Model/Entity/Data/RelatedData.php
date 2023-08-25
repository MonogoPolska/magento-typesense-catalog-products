<?php
declare(strict_types=1);

namespace Monogo\TypesenseCatalogProducts\Model\Entity\Data;

use Magento\Catalog\Model\Product;
use Monogo\TypesenseCatalogProducts\Services\ConfigService;

class RelatedData
{
    /**
     * @var ConfigService
     */
    protected ConfigService $configService;

    /**
     * @param ConfigService $configService
     */
    public function __construct(
        ConfigService $configService
    )
    {
        $this->configService = $configService;
    }

    /**
     * @param Product $product
     * @return array
     */
    public function getRelatedProducts(Product $product): array
    {
        return $product->getRelatedProductIds();
    }

    /**
     * @param Product $product
     * @return array
     */
    public function getUpSellProducts(Product $product): array
    {
        return $product->getUpSellProductIds();
    }

    /**
     * @param Product $product
     * @return array
     */
    public function getCrossSellProducts(Product $product): array
    {
        return $product->getCrossSellProductIds();
    }

}
