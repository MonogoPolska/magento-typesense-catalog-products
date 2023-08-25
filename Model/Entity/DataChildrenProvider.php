<?php
declare(strict_types=1);

namespace Monogo\TypesenseCatalogProducts\Model\Entity;

use Magento\Catalog\Model\Product\Visibility;
use Magento\Catalog\Model\ResourceModel\Product\Collection as ProductCollection;
use Magento\Catalog\Model\ResourceModel\Product\CollectionFactory as ProductCollectionFactory;
use Magento\Cms\Model\Template\FilterProvider;
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

class DataChildrenProvider extends DataProvider
{
    /**
     * @var ProductData
     */
    private ProductData $productData;

    /**
     * @var ProductCollectionFactory
     */
    private ProductCollectionFactory $productCollectionFactory;

    /**
     * @var ConfigService
     */
    private ConfigService $configService;

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
        parent::__construct(
            $eventManager,
            $productCollectionFactory,
            $configService,
            $filterProvider,
            $storeManager,
            $frontendUrlFactory,
            $indexManager,
            $productData,
            $visibility,
            $priceData,
            $imageData,
            $stockData,
            $relatedData,
            $optionsData,
            $variantsData
        );
        $this->productData = $productData;
        $this->productCollectionFactory = $productCollectionFactory;
        $this->configService = $configService;
    }

    /**
     * @return string
     */
    public function getIndexNameSuffix(): string
    {
        return '_products_children';
    }

    /**
     * @param int|null $storeId
     * @param array|null $dataIds
     * @return ProductCollection
     * @throws LocalizedException
     */
    public function getCollection(?int $storeId, array $dataIds = null): ?ProductCollection
    {
        if ($this->configService->getIndexAll($storeId)) {
            return $this->productData->getProductCollection($storeId, $dataIds, true, true);
        }
        return null;
    }
}
