<?php
declare(strict_types=1);

namespace Monogo\TypesenseCatalogProducts\Model\Entity;

use Exception;
use Magento\Catalog\Model\Product;
use Magento\Catalog\Model\Product\Visibility;
use Magento\Catalog\Model\ResourceModel\Product\Collection as ProductCollection;
use Magento\Catalog\Model\ResourceModel\Product\CollectionFactory as ProductCollectionFactory;
use Magento\Catalog\Pricing\Price\FinalPrice;
use Magento\Catalog\Pricing\Price\RegularPrice;
use Magento\Catalog\Pricing\Price\SpecialPrice;
use Magento\Cms\Model\Template\FilterProvider;
use Magento\Framework\DataObject;
use Magento\Framework\Event\ManagerInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\UrlFactory;
use Magento\Store\Model\StoreManagerInterface;
use Monogo\TypesenseCatalogProducts\Adapter\IndexManager;
use Monogo\TypesenseCatalogProducts\Model\Entity\Data\ImageData;
use Monogo\TypesenseCatalogProducts\Model\Entity\Data\OptionsData;
use Monogo\TypesenseCatalogProducts\Model\Entity\Data\PriceData;
use Monogo\TypesenseCatalogProducts\Model\Entity\Data\ProductData;
use Monogo\TypesenseCatalogProducts\Model\Entity\Data\RelatedData;
use Monogo\TypesenseCatalogProducts\Model\Entity\Data\StockData;
use Monogo\TypesenseCatalogProducts\Model\Entity\Data\VariantsData;
use Monogo\TypesenseCatalogProducts\Services\ConfigService;
use Monogo\TypesenseCore\Model\Entity\DataProvider as DataProviderCore;

class DataProvider extends DataProviderCore
{
    /**
     * @var ManagerInterface
     */
    private ManagerInterface $eventManager;

    /**
     * @var ProductCollectionFactory
     */
    private ProductCollectionFactory $productCollectionFactory;

    /**
     * @var ConfigService
     */
    private ConfigService $configService;

    /**
     * @var FilterProvider
     */
    private FilterProvider $filterProvider;

    /**
     * @var UrlFactory
     */
    private UrlFactory $frontendUrlFactory;

    /**
     * @var IndexManager
     */
    private IndexManager $indexManager;

    /**
     * @var ProductData
     */
    private ProductData $productData;

    /**
     * @var Visibility
     */
    private Visibility $visibility;

    /**
     * @var PriceData
     */
    private PriceData $priceData;

    /**
     * @var ImageData
     */
    private ImageData $imageData;

    /**
     * @var StockData
     */
    private StockData $stockData;

    /**
     * @var RelatedData
     */
    private RelatedData $relatedData;

    /**
     * @var OptionsData
     */
    private OptionsData $optionsData;

    /**
     * @var VariantsData
     */
    private VariantsData $variantsData;

    /**
     * @param ManagerInterface $eventManager
     * @param ProductCollectionFactory $productCollectionFactory
     * @param ConfigService $configService
     * @param FilterProvider $filterProvider
     * @param StoreManagerInterface $storeManager
     * @param UrlFactory $frontendUrlFactory
     * @param IndexManager $indexManager
     * @param ProductData $productData
     * @param Visibility $visibility
     * @param PriceData $priceData
     * @param ImageData $imageData
     * @param StockData $stockData
     * @param RelatedData $relatedData
     * @param OptionsData $optionsData
     * @param VariantsData $variantsData
     */
    public function __construct(
        ManagerInterface         $eventManager,
        ProductCollectionFactory $productCollectionFactory,
        ConfigService            $configService,
        FilterProvider           $filterProvider,
        StoreManagerInterface    $storeManager,
        UrlFactory               $frontendUrlFactory,
        IndexManager             $indexManager,
        ProductData              $productData,
        Visibility               $visibility,
        PriceData                $priceData,
        ImageData                $imageData,
        StockData                $stockData,
        RelatedData              $relatedData,
        OptionsData              $optionsData,
        VariantsData             $variantsData
    )
    {
        parent::__construct($configService, $storeManager);
        $this->eventManager = $eventManager;
        $this->productCollectionFactory = $productCollectionFactory;
        $this->configService = $configService;
        $this->filterProvider = $filterProvider;
        $this->frontendUrlFactory = $frontendUrlFactory;
        $this->indexManager = $indexManager;
        $this->productData = $productData;
        $this->visibility = $visibility;
        $this->priceData = $priceData;
        $this->imageData = $imageData;
        $this->stockData = $stockData;
        $this->relatedData = $relatedData;
        $this->optionsData = $optionsData;
        $this->variantsData = $variantsData;
    }

    /**
     * @return string
     */
    public function getIndexNameSuffix(): string
    {
        return '_products';
    }

    /**
     * @param int|null $storeId
     * @param array|null $dataIds
     * @return ProductCollection|null
     * @throws LocalizedException
     */
    public function getCollection(?int $storeId, array $dataIds = null): ?ProductCollection
    {
        $productCollection = $this->productData->getProductCollection($storeId, $dataIds, true, false);
        $this->optionsData->setProducts($productCollection);
        $this->variantsData->setProducts($productCollection);
        return $productCollection;
    }

    /**
     * @param int|null $storeId
     * @param array|null $dataIds
     * @return array
     * @throws Exception
     */
    public function getData(?int $storeId, array $dataIds = null): array
    {
        if ($dataIds) {
            $dataIds = array_unique(array_merge($dataIds, $this->productData->getParentProductIds($dataIds)));
        }

        $productCollection = $this->getCollection($storeId, $dataIds);

        if (is_null($productCollection)) {
            return [];
        }

        $dataIdsToRemove = $dataIds ? array_flip($dataIds) : [];
        $products = [];

        /** @var Product $product */
        foreach ($productCollection as $product) {
            $product->setStoreId($storeId);
            $subProductIds = [];
            $subProducts = $this->productData->getSubProducts($product);
            foreach ($subProducts as $subProduct) {
                $subProductIds[] = $subProduct->getId();
            }

            $this->productData->addCategoryData($product);

            $productObject = $this->getInitialDataModel($product, $subProductIds);
            $this->productData->getProductAttributes($product, $subProducts, $storeId, $productObject);

            $this->prepareContentData($product, $productObject);

            $transport = new DataObject($productObject);
            $this->eventManager->dispatch(
                'typesense_after_create_product_object',
                ['product' => $transport, 'productObject' => $product]
            );
            $productObject = $transport->getData();

            if (isset($dataIdsToRemove[$product->getId()])) {
                unset($dataIdsToRemove[$product->getId()]);
            }
            $products['toIndex'][] = $productObject;
        }
        $products['toRemove'] = array_unique(array_keys($dataIdsToRemove));
        return $products;
    }

    /**
     * @param Product $product
     * @param array $subProductIds
     * @return array
     * @throws LocalizedException
     */
    public function getInitialDataModel(Product $product, array $subProductIds): array
    {
        return [
            'id' => $product->getId(),
            'uid' => base64_encode((string)$product->getEntityId()),
            'sku' => $product->getSku(),
            'entity_id' => $product->getId(),
            'store_id' => $product->getStoreId(),
            'status' => $product->getStatus(),
            'visibility' => $product->getVisibility(),
            'visibility_label' => (string)$this->visibility->getOptionArray()[$product->getVisibility()],
            'name' => $product->getName(),
            'url' => $product->getProductUrl(),
            'url_key' => $product->getUrlKey(),
            'type_id' => $product->getTypeId(),
            'subproducts' => $subProductIds,
            'parent_ids' => $this->productData->getParentProductIds([$product->getId()]),
            'meta_title' => $product->getMetaTitle(),
            'meta_description' => $product->getMetaDescription(),
            'meta_keywords' => $product->getKeywords(),
            'categories' => $product->getCategories(),
            'categories_label' => __('Category'),
            'category_ids' => $product->getCategoryIds(),
            'category_uid' => $this->getCategoryUids($product->getCategoryIds()),
            'price_range' => $this->priceData->getPriceRange($product),
            'media_gallery' => $this->imageData->getMediaGallery($product),
            'stock_status' => $this->stockData->getStockStatus($product),
            'stock' => $product->getStock(),
            'related_products_ids' => $this->relatedData->getRelatedProducts($product),
            'upsell_products_ids' => $this->relatedData->getUpSellProducts($product),
            'crossell_products_ids' => $this->relatedData->getCrossSellProducts($product),
            'configurable_options' => $this->optionsData->getOptions($product),
            'variants' => $this->variantsData->getVariants($product),
            'price' => $product->getPriceInfo()->getPrice(RegularPrice::PRICE_CODE)->getValue(),
            'final_price' => $product->getPriceInfo()->getPrice(FinalPrice::PRICE_CODE)->getValue(),
            'special_price' => $product->getPriceInfo()->getPrice(SpecialPrice::PRICE_CODE)->getValue(),
        ];
    }

    /**
     * @param Product $product
     * @param array $productObject
     * @return void
     * @throws Exception
     */
    public function prepareContentData(Product $product, array &$productObject): void
    {
        if (!empty($product->getDescription())) {
            $description = $this->filterProvider->getBlockFilter()->filter($product->getDescription());
            $productObject['description'] = $description;
            $productObject['description_stripped'] = $this->strip($description, ['script', 'style']);
        }

        if (!empty($product->getShortDescription())) {
            $description = $this->filterProvider->getBlockFilter()->filter($product->getShortDescription());
            $productObject['short_description'] = $description;
            $productObject['short_description_stripped'] = $this->strip($description, ['script', 'style']);
        }
    }

    /**
     * @param array $categoryIds
     * @return array
     */
    public function getCategoryUids(array $categoryIds): array
    {
        $uids = [];
        foreach ($categoryIds as $categoryId) {
            $uids[] = base64_encode((string)$categoryId);
        }
        return $uids;
    }
}
