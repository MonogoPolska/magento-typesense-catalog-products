<?php
declare(strict_types=1);

namespace Monogo\TypesenseCatalogProducts\Model\Indexer;

use Magento\Framework\Message\ManagerInterface;
use Monogo\TypesenseCatalogProducts\Model\Entity\DataChildrenProvider;
use Monogo\TypesenseCatalogProducts\Services\ConfigService;
use Monogo\TypesenseCore\Model\Indexer\DataIndexer as DataIndexerCore;
use Monogo\TypesenseCore\Model\Queue;
use Symfony\Component\Console\Output\ConsoleOutput;

class DataChildrenIndexer extends DataIndexerCore
{
    /**
     * @param DataChildrenProvider $dataProvider
     * @param IndexerChildrenRunner $indexerRunner
     * @param Queue $queue
     * @param ConfigService $configService
     * @param ManagerInterface $messageManager
     * @param ConsoleOutput $output
     */
    public function __construct(
        DataChildrenProvider     $dataProvider,
        IndexerChildrenRunner    $indexerRunner,
        Queue            $queue,
        ConfigService    $configService,
        ManagerInterface $messageManager,
        ConsoleOutput    $output
    )
    {
        parent::__construct(
            $dataProvider,
            $indexerRunner,
            $queue,
            $configService,
            $messageManager,
            $output
        );
    }
}
