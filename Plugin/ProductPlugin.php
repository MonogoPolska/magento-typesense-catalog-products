<?php
declare(strict_types=1);

namespace Monogo\TypesenseCatalogProducts\Plugin;

use Magento\Catalog\Model\ResourceModel\Product;
use Magento\Framework\Indexer\IndexerInterface;
use Magento\Framework\Indexer\IndexerRegistry;
use Magento\Framework\Model\AbstractModel;
use Monogo\TypesenseCatalogProducts\Services\ConfigService;

class ProductPlugin
{
    /**
     * @var IndexerInterface
     */
    private IndexerInterface $indexer;

    /**
     * @var IndexerInterface
     */
    private IndexerInterface $indexerChildren;

    /**
     * @var ConfigService
     */
    private ConfigService $configService;

    /**
     * @param IndexerRegistry $indexerRegistry
     * @param ConfigService $configService
     */
    public function __construct(
        IndexerRegistry $indexerRegistry,
        ConfigService   $configService
    )
    {
        $this->indexer = $indexerRegistry->get('typesense_products');
        $this->indexerChildren = $indexerRegistry->get('typesense_products_children');
        $this->configService = $configService;
    }

    /**
     * @param Product $productResource
     * @param AbstractModel $product
     * @return AbstractModel[]
     */
    public function beforeSave(Product $productResource, AbstractModel $product)
    {
        if (!$this->configService->isConfigurationValid()) {
            return [$product];
        }

        $productResource->addCommitCallback(function () use ($product) {
            if (!$this->indexer->isScheduled()) {
                $this->indexer->reindexRow($product->getId());
            }
            if (!$this->indexerChildren->isScheduled()) {
                $this->indexerChildren->reindexRow($product->getId());
            }
        });

        return [$product];
    }

    /**
     * @param Product $productResource
     * @param AbstractModel $product
     * @return AbstractModel[]
     */
    public function beforeDelete(Product $productResource, AbstractModel $product)
    {
        if (!$this->configService->isConfigurationValid()) {
            return [$product];
        }

        $productResource->addCommitCallback(function () use ($product) {
            if (!$this->indexer->isScheduled()) {
                $this->indexer->reindexRow($product->getId());
            }
            if (!$this->indexerChildren->isScheduled()) {
                $this->indexerChildren->reindexRow($product->getId());
            }
        });

        return [$product];
    }
}
