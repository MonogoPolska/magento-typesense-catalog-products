<?php
declare(strict_types=1);

namespace Monogo\TypesenseCatalogProducts\Model\Entity\Data;

use Magento\Catalog\Api\Data\ProductInterface;
use Magento\Catalog\Model\Product;
use Magento\Catalog\Model\ResourceModel\Product\Collection as ProductCollection;
use Magento\CatalogInventory\Helper\Stock;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\EntityManager\MetadataPool;
use Magento\InventoryCatalog\Model\GetStockIdForCurrentWebsite;
use Magento\InventoryCatalogApi\Api\DefaultStockProviderInterface;
use Magento\InventorySalesApi\Api\AreProductsSalableInterface;
use Monogo\TypesenseCatalogProducts\Services\ConfigService;

class StockData
{
    /**
     * @var Stock
     */
    protected Stock $stockHelper;

    /**
     * @var ConfigService
     */
    protected ConfigService $configService;

    /**
     * @var DefaultStockProviderInterface
     */
    private DefaultStockProviderInterface $defaultStockProvider;

    /**
     * @var GetStockIdForCurrentWebsite
     */
    private GetStockIdForCurrentWebsite $getStockIdForCurrentWebsite;

    /**
     * @var AreProductsSalableInterface
     */
    private AreProductsSalableInterface $areProductsSalable;

    /**
     * @var ResourceConnection
     */
    private ResourceConnection $resourceConnection;

    /**
     * @var MetadataPool
     */
    private MetadataPool $metadataPool;

    /**
     * @var int|null
     */
    private ?int $stockId = null;

    /**
     * @var string
     */
    private string $linkField = "";

    /**
     * @param ConfigService $configService
     * @param Stock $stockHelper
     * @param DefaultStockProviderInterface $defaultStockProvider
     * @param ResourceConnection $resourceConnection
     * @param GetStockIdForCurrentWebsite $getStockIdForCurrentWebsite
     * @param AreProductsSalableInterface $areProductsSalable
     * @param MetadataPool $metadataPool
     */
    public function __construct(
        ConfigService                 $configService,
        Stock                         $stockHelper,
        DefaultStockProviderInterface $defaultStockProvider,
        ResourceConnection            $resourceConnection,
        GetStockIdForCurrentWebsite   $getStockIdForCurrentWebsite,
        AreProductsSalableInterface   $areProductsSalable,
        MetadataPool                  $metadataPool
    )
    {
        $this->configService = $configService;
        $this->stockHelper = $stockHelper;
        $this->defaultStockProvider = $defaultStockProvider;
        $this->resourceConnection = $resourceConnection;
        $this->getStockIdForCurrentWebsite = $getStockIdForCurrentWebsite;
        $this->areProductsSalable = $areProductsSalable;
        $this->metadataPool = $metadataPool;
    }

    /**
     * @param ProductCollection $products
     * @param int|null $storeId
     * @return void
     */
    public function addStockFilter(ProductCollection $products, ?int $storeId): void
    {
        if ($this->configService->getShowOutOfStock($storeId) === false) {
            $this->stockHelper->addInStockFilterToCollection($products);
        }
    }

    /**
     * @param array|ProductCollection $products
     * @param int|null $storeId
     * @return void
     */
    public function addStockDataToCollection(array|ProductCollection $products, ?int $storeId): void
    {
        $stockData = $this->getStockData($products, $storeId);

        /** @var Product $product */
        foreach ($products as $product) {
            if (isset($stockData[$product->getSku()])) {
                $product->addData($stockData[$product->getSku()]);
            }
        }
    }

    /**
     * @param array|ProductCollection $products
     * @param int|null $storeId
     * @return array
     */
    public function getStockData(array|ProductCollection $products, ?int $storeId): array
    {
        $result = [];
        $listSku = $this->getSkuList($products);
        $stocksInformation = $this->getProductStockInformation($listSku, $storeId);
        $productsReservation = $this->getProductReservation($listSku);

        foreach ($stocksInformation as $stockInformation) {
            $sku = $stockInformation['sku'];
            $isSalable = $stockInformation['stock_status'];
            $quantity = $stockInformation['quantity'];
            $minQty = $stockInformation['min_qty'];

            $productReservationQty = $productsReservation[$sku] ?? 0;

            $result[$sku]['stock'] = [
                'sku' => $sku,
                'salable_qty' => $quantity + $productReservationQty - $minQty,
                'stock_status' => $isSalable ? 'IN_STOCK' : 'OUT_OF_STOCK',
                'is_in_stock' => $stockInformation['is_in_stock'],
                'max_sale_qty' => $stockInformation['max_sale_qty'],
                'min_sale_qty' => $minQty,
                'qty' => $quantity,
                'stock_qty' => $stockInformation['stock_qty'],
                'reservation_qty' => $productReservationQty,
            ];
        }
        return $result;
    }

    /**
     * @param array|ProductCollection $products
     * @return array
     */
    public function getSkuList(array|ProductCollection $products): array
    {
        $skuList = [];
        foreach ($products as $product) {
            $skuList[] = $product->getSku();
        }
        return $skuList;
    }

    /**
     * @param array $listSku
     * @param int|null $storeId
     * @return array
     */
    public function getProductStockInformation(array $listSku, ?int $storeId): array
    {
        $defaultStockId = $this->defaultStockProvider->getId();
        $connection = $this->resourceConnection->getConnection();

        $subQuery = $connection->select()->reset()
            ->from('inventory_source_stock_link', null)
            ->columns([
                'source_tmp.source_code'
            ])
            ->joinInner(
                ['source_tmp' => 'inventory_source_item'],
                'source_tmp.source_code = inventory_source_stock_link.source_code' .
                ' AND inventory_source_stock_link.stock_id = ' . $defaultStockId,
                null
            );
        $subQuery = '(' . $subQuery->__toString() . ')';

        $select = $connection->select()->reset()
            ->from(['catalog_product_entity' => 'catalog_product_entity'], null)
            ->columns([
                'catalog_product_entity.sku',
                'css.stock_status',
                'csi.min_qty',
                'csi.is_in_stock',
                'csi.max_sale_qty',
                'stock_qty' => 'csi.qty',
                'quantity' => new \Zend_Db_Expr('IF(catalog_product_entity.type_id = "configurable", (
                    SELECT SUM(child_csi.qty)
                    FROM catalog_product_relation AS cpr
                    JOIN cataloginventory_stock_item AS child_csi ON child_csi.product_id = cpr.child_id
                    WHERE cpr.parent_id = catalog_product_entity.' .  $this->getLinkField() . '
                ), css.qty)')
            ])
            ->joinLeft(
                'inventory_source_item',
                'inventory_source_item.sku = catalog_product_entity.sku' .
                ' AND inventory_source_item.source_code IN ' . $subQuery,
                null
            )
            ->joinLeft(
                ['csi' => 'cataloginventory_stock_item'],
                'csi.product_id = catalog_product_entity.entity_id',
                null
            )
            ->joinLeft(
                ['css' => 'cataloginventory_stock_status'],
                'css.product_id = catalog_product_entity.entity_id',
                null
            )
            ->where('catalog_product_entity.sku IN (?)', $listSku);
        return $connection->fetchAll($select);
    }

    /**
     * @param array $listSku
     * @return array
     */
    public function getProductReservation(array $listSku): array
    {
        $defaultStockId = $this->defaultStockProvider->getId();
        $connection = $this->resourceConnection->getConnection();
        $result = [];

        $subSelect = $connection->select()->reset()
            ->from(['cpe_parent' => 'catalog_product_entity'], null)
            ->columns(['quantity' => 'SUM(ir.quantity)'])
            ->join(
                ['cpr' => 'catalog_product_relation'],
                'cpe_parent.' . $this->getLinkField() . ' = cpr.parent_id', []
            )
            ->join(
                ['child_cpe' => 'catalog_product_entity'],
                'cpr.child_id = child_cpe.entity_id', []
            )
            ->join(
                ['ir' => 'inventory_reservation'],
                'child_cpe.sku = ir.sku', []
            )
            ->where('cpe_parent.sku = cpe.sku')
            ->where('ir.stock_id = ' . $defaultStockId);

        $subSelect = '(' . $subSelect->__toString() . ')';

        $select = $connection->select()->reset()
            ->from(['cpe' => 'catalog_product_entity'], null)
            ->columns([
                'sku' => 'cpe.sku',
                'quantity' => new \Zend_Db_Expr('IF(cpe.type_id = "configurable", (
                        ' . $subSelect . '
					), SUM(ir.quantity))')
            ])
            ->joinLeft(
                ['ir' => 'inventory_reservation'],
                'cpe.sku = ir.sku',
                []
            )
            ->where('cpe.sku IN (?)', $listSku)
            ->group('cpe.sku')
            ->group('cpe.type_id');

        foreach ($connection->fetchPairs($select) as $sku => $quantity) {
            $result[$sku] = $quantity;
        }
        return $result;
    }

    /**
     * @return string
     * @throws \Exception
     */
    public function getLinkField(): string
    {
        if (empty($this->linkField)) {
            $this->linkField = $this->metadataPool->getMetadata(ProductInterface::class)->getLinkField();
        }
        return $this->linkField;
    }

    /**
     * @return int
     */
    public function getStockId(): int
    {
        if (empty($this->stockId)) {
            $this->stockId = $this->getStockIdForCurrentWebsite->execute();
        }
        return $this->stockId;

    }

    /**
     * @param Product $product
     * @return string
     */
    public function getStockStatus(Product $product): string
    {
        $stockId = $this->getStockId();
        $result = $this->areProductsSalable->execute([$product->getSku()], $stockId);
        $result = current($result);
        return $result->isSalable() ? 'IN_STOCK' : 'OUT_OF_STOCK';
    }
}
