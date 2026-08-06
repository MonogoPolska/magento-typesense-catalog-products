<?php
declare(strict_types=1);

namespace Monogo\TypesenseCatalogProducts\Services;

use Magento\Catalog\Api\Data\ProductInterface;
use Magento\Catalog\Model\ResourceModel\Product\Collection as ProductCollection;
use Magento\Catalog\Model\ResourceModel\Product\CollectionFactory as ProductCollectionFactory;
use Magento\Framework\DB\Select;
use Magento\Framework\EntityManager\MetadataPool;
use Magento\Rule\Model\Condition\Sql\Builder as SqlBuilder;
use Monogo\TypesenseCatalogProducts\Model\Rule\Condition\Combine;
use Monogo\TypesenseCatalogProducts\Model\RuleFactory;
use Monogo\TypesenseCore\Logger\Logger;

/**
 * Translates the configured condition tree into SQL filters on the indexer product collection
 */
class IndexerFilterService
{
    const FILTER_TYPE_PRODUCTS = 'products';

    const FILTER_TYPE_CHILDREN = 'children';

    /**
     * Alias of the relation table used to let parents of matching children through
     */
    const RELATION_TABLE_ALIAS = 'typesense_filter_relation';

    /**
     * @var ConfigService
     */
    private ConfigService $configService;

    /**
     * @var RuleFactory
     */
    private RuleFactory $ruleFactory;

    /**
     * @var SqlBuilder
     */
    private SqlBuilder $sqlBuilder;

    /**
     * @var ProductCollectionFactory
     */
    private ProductCollectionFactory $productCollectionFactory;

    /**
     * @var MetadataPool
     */
    private MetadataPool $metadataPool;

    /**
     * @var Logger
     */
    private Logger $logger;

    /**
     * @param ConfigService $configService
     * @param RuleFactory $ruleFactory
     * @param SqlBuilder $sqlBuilder
     * @param ProductCollectionFactory $productCollectionFactory
     * @param MetadataPool $metadataPool
     * @param Logger $logger
     */
    public function __construct(
        ConfigService            $configService,
        RuleFactory              $ruleFactory,
        SqlBuilder               $sqlBuilder,
        ProductCollectionFactory $productCollectionFactory,
        MetadataPool             $metadataPool,
        Logger                   $logger
    )
    {
        $this->configService = $configService;
        $this->ruleFactory = $ruleFactory;
        $this->sqlBuilder = $sqlBuilder;
        $this->productCollectionFactory = $productCollectionFactory;
        $this->metadataPool = $metadataPool;
        $this->logger = $logger;
    }

    /**
     * @param ProductCollection $collection
     * @param int|null $storeId
     * @param string $filterType
     * @return void
     * @throws \Exception
     */
    public function apply(
        ProductCollection $collection,
        ?int              $storeId,
        string            $filterType = self::FILTER_TYPE_PRODUCTS
    ): void
    {
        if (!$this->configService->isIndexerFiltersEnabled($storeId)) {
            return;
        }

        $conditions = $this->getConditions($storeId, $filterType);

        if ($conditions === null) {
            return;
        }

        $includeParents = $filterType === self::FILTER_TYPE_PRODUCTS
            && $this->configService->includeParentsOfMatchingChildren($storeId);

        try {
            if ($includeParents) {
                $this->applyIncludingParents($collection, $conditions, $storeId);

                return;
            }

            $conditions->collectValidatedAttributes($collection);
            $this->sqlBuilder->attachConditionToCollection($collection, $conditions);
        } catch (\Exception $e) {
            $this->logger->error(
                sprintf(
                    'Typesense indexer filters could not be applied for store %s: %s',
                    $storeId ?? 'default',
                    $e->getMessage()
                )
            );

            throw $e;
        }
    }

    /**
     * @param int|null $storeId
     * @param string $filterType
     * @return Combine|null
     */
    private function getConditions(?int $storeId, string $filterType): ?Combine
    {
        $conditionsData = $filterType === self::FILTER_TYPE_CHILDREN
            ? $this->configService->getIndexerFilterChildrenConditions($storeId)
            : $this->configService->getIndexerFilterConditions($storeId);

        if (empty($conditionsData)) {
            return null;
        }

        $rule = $this->ruleFactory->create();
        $rule->loadPost($conditionsData);

        /** @var Combine $conditions */
        $conditions = $rule->getConditions();

        return empty($conditions->getConditions()) ? null : $conditions;
    }

    /**
     * Products are matched on their own values, composite products additionally on the values of their children
     *
     * @param ProductCollection $collection
     * @param Combine $conditions
     * @param int|null $storeId
     * @return void
     * @throws \Exception
     */
    private function applyIncludingParents(
        ProductCollection $collection,
        Combine           $conditions,
        ?int              $storeId
    ): void
    {
        $matchSql = $this->buildMatchingIdsSelect($conditions, $storeId)->assemble();
        $connection = $collection->getConnection();

        $parentSelect = $connection->select()
            ->from(
                [self::RELATION_TABLE_ALIAS => $collection->getTable('catalog_product_relation')],
                ['parent_id']
            )
            ->where(self::RELATION_TABLE_ALIAS . '.child_id IN (' . $matchSql . ')');

        $collection->getSelect()->where(
            sprintf(
                'e.entity_id IN (%s) OR e.%s IN (%s)',
                $matchSql,
                $this->getLinkField(),
                $parentSelect->assemble()
            )
        );
    }

    /**
     * @param Combine $conditions
     * @param int|null $storeId
     * @return Select
     */
    private function buildMatchingIdsSelect(Combine $conditions, ?int $storeId): Select
    {
        $matchCollection = $this->productCollectionFactory->create();
        $matchCollection->setStoreId($storeId)
            ->addStoreFilter($storeId);

        $conditions->collectValidatedAttributes($matchCollection);
        $this->sqlBuilder->attachConditionToCollection($matchCollection, $conditions);

        // Flushes pending collection filters into the select before it gets reused as a subquery
        $matchCollection->getSelectCountSql();

        $select = clone $matchCollection->getSelect();
        $select->reset(Select::ORDER)
            ->reset(Select::LIMIT_COUNT)
            ->reset(Select::LIMIT_OFFSET)
            ->reset(Select::GROUP)
            ->reset(Select::COLUMNS)
            ->columns(['entity_id' => 'e.entity_id']);

        return $select;
    }

    /**
     * @return string
     * @throws \Exception
     */
    private function getLinkField(): string
    {
        return $this->metadataPool->getMetadata(ProductInterface::class)->getLinkField();
    }
}
