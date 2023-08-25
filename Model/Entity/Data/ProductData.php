<?php
declare(strict_types=1);

namespace Monogo\TypesenseCatalogProducts\Model\Entity\Data;

use Magento\Bundle\Model\Product\Type as BundleProductType;
use Magento\Catalog\Model\Product;
use Magento\Catalog\Model\Product\Attribute\Source\Status;
use Magento\Catalog\Model\Product\Type;
use Magento\Catalog\Model\Product\Type\AbstractType;
use Magento\Catalog\Model\Product\Visibility;
use Magento\Catalog\Model\ResourceModel\Product\Collection as ProductCollection;
use Magento\Catalog\Model\ResourceModel\Product\CollectionFactory as ProductCollectionFactory;
use Magento\ConfigurableProduct\Model\Product\Type\Configurable;
use Magento\Framework\Event\ManagerInterface;
use Magento\Framework\Exception\LocalizedException;
use Monogo\TypesenseCatalogProducts\Exception\ProductDeletedException;
use Monogo\TypesenseCatalogProducts\Exception\ProductDisabledException;
use Monogo\TypesenseCatalogProducts\Exception\ProductNotVisibleException;
use Monogo\TypesenseCatalogProducts\Exception\ProductOutOfStockException;
use Monogo\TypesenseCatalogProducts\Services\ConfigService;

class ProductData
{
    /**
     * @var ProductCollectionFactory
     */
    protected ProductCollectionFactory $productCollectionFactory;

    /**
     * @var Visibility
     */
    protected Visibility $visibility;

    /**
     * @var ConfigService
     */
    protected ConfigService $configService;

    /**
     * @var ManagerInterface
     */
    protected ManagerInterface $eventManager;

    /**
     * @var CategoryData
     */
    protected CategoryData $categoryData;

    /**
     * @var PriceData
     */
    protected PriceData $priceData;

    /**
     * @var AttributeData
     */
    protected AttributeData $attributeData;

    /**
     * @var StockData
     */
    protected StockData $stockData;

    /**
     * @var ImageData
     */
    protected ImageData $imageData;

    /**
     * @var Type
     */
    protected Type $productType;

    /**
     * @var array
     */
    protected array $facets = [];

    /**
     * @var AbstractType[]
     */
    protected array $compositeTypes;

    /**
     * @param ConfigService $configService
     * @param ManagerInterface $eventManager
     * @param Visibility $visibility
     * @param CategoryData $categoryData
     * @param AttributeData $attributeData
     * @param PriceData $priceData
     * @param StockData $stockData
     * @param Type $productType
     * @param ProductCollectionFactory $productCollectionFactory
     * @param ImageData $imageData
     */
    public function __construct(
        ConfigService            $configService,
        ManagerInterface         $eventManager,
        Visibility               $visibility,
        CategoryData             $categoryData,
        AttributeData            $attributeData,
        PriceData                $priceData,
        StockData                $stockData,
        Type                     $productType,
        ProductCollectionFactory $productCollectionFactory,
        ImageData                $imageData
    )
    {
        $this->configService = $configService;
        $this->eventManager = $eventManager;
        $this->visibility = $visibility;
        $this->categoryData = $categoryData;
        $this->attributeData = $attributeData;
        $this->priceData = $priceData;
        $this->stockData = $stockData;
        $this->productType = $productType;
        $this->productCollectionFactory = $productCollectionFactory;
        $this->imageData = $imageData;
    }

    /**
     * @param int|null $storeId
     * @param array|null $productIds
     * @param bool $onlyEnabled
     * @param bool $includeNotVisibleIndividually
     * @return ProductCollection
     * @throws LocalizedException
     */
    public function getProductCollection(
        ?int   $storeId,
        ?array $productIds = null,
        bool   $onlyEnabled = true,
        bool   $includeNotVisibleIndividually = false
    ): ProductCollection
    {
        $productCollection = $this->productCollectionFactory->create();

        $products = $productCollection
            ->setStoreId($storeId)
            ->addStoreFilter($storeId)
            ->distinct(true);

        if (!empty($productIds)) {
            $products = $products->addAttributeToFilter('entity_id', ['in' => $productIds]);
        }

        if ($onlyEnabled) {
            $products = $products->addAttributeToFilter('status', ['=' => Status::STATUS_ENABLED]);

            if ($includeNotVisibleIndividually === false) {
                $products = $products
                    ->addAttributeToFilter('visibility', ['in' => $this->visibility->getVisibleInSiteIds()]);
            } else {
                $products = $products
                    ->addAttributeToFilter('visibility', ['nin' => $this->visibility->getVisibleInSiteIds()]);
            }
            $this->stockData->addStockFilter($products, $storeId);
        }

        $this->attributeData->addMandatoryAttributes($products);
        $this->stockData->addStockDataToCollection($products, $storeId);
        $this->imageData->addImageDataToCollection($products);

        $this->eventManager->dispatch(
            'typesense_after_create_products_collection',
            [
                'store' => $storeId,
                'collection' => $products,
                'only_enabled' => $onlyEnabled,
                'include_not_visible_individually' => $includeNotVisibleIndividually,
            ]
        );

        return $products;
    }

    /**
     * @param Product $product
     * @param array $subProducts
     * @param int|null $storeId
     * @param array $data
     * @return void
     * @throws LocalizedException
     */
    public function getProductAttributes(Product $product, array $subProducts, ?int $storeId, array &$data): void
    {
        $additionalAttributes = $this->configService->getSchema($storeId);
        $this->attributeData->addAdditionalAttributes($data, $additionalAttributes, $product, $subProducts);
    }

    /**
     * @param Product $product
     * @return void
     * @throws LocalizedException
     */
    public function addCategoryData(Product $product): void
    {
        $this->categoryData->addCategoryData($product);
    }

    /**
     * @param Product $product
     * @return array
     */
    public function getSubProducts(Product $product): array
    {
        $type = $product->getTypeId();

        if (!in_array($type, ['bundle', 'grouped', 'configurable'], true)) {
            return [];
        }

        $storeId = $product->getStoreId();
        $typeInstance = $product->getTypeInstance();

        if ($typeInstance instanceof Configurable) {
            $subProducts = $typeInstance->getUsedProductCollection($product)->getItems();
        } elseif ($typeInstance instanceof BundleProductType) {
            $subProducts = $typeInstance->getSelectionsCollection($typeInstance->getOptionsIds($product), $product)->getItems();
        } else { // Grouped product
            $subProducts = $typeInstance->getAssociatedProducts($product);
        }

        $this->stockData->addStockDataToCollection($subProducts, $product->getStoreId());

        /**
         * @var Product $subProduct
         */
        foreach ($subProducts as $index => $subProduct) {
            try {
                $this->canProductBeReindexed($subProduct, $storeId, true);
            } catch (\Exception $e) {
                unset($subProducts[$index]);
            }
        }

        return $subProducts;
    }

    /**
     * @param Product $product
     * @param int|null $storeId
     * @param bool $isChildProduct
     * @return bool
     */
    public function canProductBeReindexed(Product $product, ?int $storeId, bool $isChildProduct = false): bool
    {
        if ($product->isDeleted() === true) {
            throw (new ProductDeletedException())
                ->withProduct($product)
                ->withStoreId($storeId);
        }
        if ($product->getStatus() == Status::STATUS_DISABLED) {
            throw (new ProductDisabledException())
                ->withProduct($product)
                ->withStoreId($storeId);
        }
        if ($isChildProduct === false && !in_array($product->getVisibility(), [
                Visibility::VISIBILITY_BOTH,
                Visibility::VISIBILITY_IN_SEARCH,
                Visibility::VISIBILITY_IN_CATALOG,
            ])) {
            throw (new ProductNotVisibleException())
                ->withProduct($product)
                ->withStoreId($storeId);
        }
        $isInStock = true;
        if (!$this->configService->getShowOutOfStock($storeId)) {
            $isInStock = $this->productIsInStock($product);
        }

        if (!$isInStock) {
            throw (new ProductOutOfStockException())
                ->withProduct($product)
                ->withStoreId($storeId);
        }
        return true;
    }

    /**
     * @param Product $product
     * @return bool
     */
    public function productIsInStock(Product $product): bool
    {
        $stockItem = $product->getData('stock');
        return $product->isSaleable() && $stockItem['is_in_stock'];
    }


    /**
     * @param array $productIds
     * @return array
     */
    public function getParentProductIds(array $productIds): array
    {
        $parentIds = [];
        foreach ($this->getCompositeTypes() as $typeInstance) {
            $parentIds = array_merge($parentIds, $typeInstance->getParentIdsByChild($productIds));
        }

        return $parentIds;
    }

    /**
     * @return AbstractType[]
     */
    protected function getCompositeTypes(): array
    {
        if (empty($this->compositeTypes)) {
            $productEmulator = new \Magento\Framework\DataObject();
            foreach ($this->productType->getCompositeTypes() as $typeId) {
                $productEmulator->setTypeId($typeId);
                $this->compositeTypes[$typeId] = $this->productType->factory($productEmulator);
            }
        }
        return $this->compositeTypes;
    }
}
