<?php
declare(strict_types=1);

namespace Monogo\TypesenseCatalogProducts\Adapter;

use Http\Client\Exception;
use Monogo\TypesenseCatalogProducts\Services\ConfigService;
use Monogo\TypesenseCore\Adapter\Client;
use Monogo\TypesenseCore\Adapter\IndexManager as IndexManagerCore;
use Monogo\TypesenseCore\Exceptions\ConnectionException;
use Monogo\TypesenseCore\Services\LogService;
use Typesense\Exceptions\ConfigError;

class IndexManager extends IndexManagerCore
{
    /**
     * @var ConfigService
     */
    protected ConfigService $configService;

    /**
     * @param Client $client
     * @param LogService $logService
     * @param ConfigService $configService
     * @throws ConfigError
     * @throws ConnectionException
     * @throws Exception
     */
    public function __construct(Client $client, LogService $logService, ConfigService $configService)
    {
        parent::__construct($client, $logService);
        $this->configService = $configService;
    }

    /**
     * @param string $name
     * @return array
     */
    public function getIndexSchema(string $name): array
    {
        return [
            'name' => $name,
            'fields' => $this->getFormattedFields(),
            'default_sorting_field' => 'entity_id',
            'enable_nested_fields' => true
        ];
    }

    /**
     * @return array
     */
    public function getFormattedFields(): array
    {
        $formattedFields = [];
        $fields = $this->getIndexFields();
        foreach ($fields as $field) {
            $formattedFields[] = $field;
        }
        return $formattedFields;
    }

    /**
     * @return array
     */
    public function getIndexFields(): array
    {
        $defaultSchema = $this->getDefaultSchema();
        $configSchema = $this->configService->getSchema();
        return array_merge($defaultSchema, $configSchema);
    }

    /**
     * @return array[]
     */
    public function getDefaultSchema(): array
    {
        return [
            'entity_id' => ['name' => 'entity_id', 'type' => 'int32', 'optional' => false, 'index' => true],
            'uid' => ['name' => 'uid', 'type' => 'string', 'optional' => false, 'index' => true],
            'sku' => ['name' => 'sku', 'type' => 'string', 'optional' => false, 'index' => true],
            'store_id' => ['name' => 'store_id', 'type' => 'int32', 'optional' => true, 'index' => false],
            'status' => ['name' => 'status', 'type' => 'int32', 'optional' => true, 'index' => false],
            'visibility' => ['name' => 'visibility', 'type' => 'int32', 'optional' => true, 'index' => false],
            'visibility_label' => ['name' => 'visibility_label', 'type' => 'string', 'optional' => true, 'index' => false],
            'name' => ['name' => 'name', 'type' => 'string', 'optional' => false, 'index' => true],
            'url' => ['name' => 'url', 'type' => 'string', 'optional' => false, 'index' => true],
            'url_key' => ['name' => 'url_key', 'type' => 'string', 'optional' => false, 'index' => true],
            'type_id' => ['name' => 'type_id', 'type' => 'string', 'optional' => true, 'index' => false,],
            'subproducts' => ['name' => 'subproducts', 'type' => 'string[]', 'optional' => true, 'index' => false,],
            'parent_ids' => ['name' => 'parent_ids', 'type' => 'string[]', 'optional' => true, 'index' => false,],
            'description' => ['name' => 'description', 'type' => 'string', 'optional' => true, 'index' => false],
            'description_stripped' => ['name' => 'description_stripped', 'type' => 'string', 'optional' => true, 'index' => true],
            'short_description' => ['name' => 'short_description', 'type' => 'string', 'optional' => true, 'index' => false],
            'short_description_stripped' => ['name' => 'short_description_stripped', 'type' => 'string', 'optional' => true, 'index' => true],
            'meta_title' => ['name' => 'meta_title', 'type' => 'string', 'optional' => true, 'index' => true],
            'meta_keywords' => ['name' => 'meta_keywords', 'type' => 'string', 'optional' => true, 'index' => true],
            'meta_description' => ['name' => 'meta_description', 'type' => 'string', 'optional' => true, 'index' => true],
            'category_ids' => ['name' => 'category_ids', 'type' => 'string[]', 'optional' => true, 'index' => true],
            'category_uid' => ['name' => 'category_uid', 'type' => 'string[]', 'optional' => true, 'index' => true, 'facet' => true],
            'stock_status' => ['name' => 'stock_status', 'type' => 'string', 'optional' => false, 'index' => true, 'facet' => true],
            'related_products_ids' => ['name' => 'related_products_ids', 'type' => 'string[]', 'optional' => true, 'index' => false],
            'upsell_products_ids' => ['name' => 'upsell_products_ids', 'type' => 'string[]', 'optional' => true, 'index' => false],
            'crossell_products_ids' => ['name' => 'crossell_products_ids', 'type' => 'string[]', 'optional' => true, 'index' => false],
            'final_price' => ['name' => 'final_price', 'type' => 'float', 'optional' => false, 'index' => true, 'sort' => true],
        ];
    }
}
