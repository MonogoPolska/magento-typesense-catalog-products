<?php
declare(strict_types=1);

namespace Monogo\TypesenseCatalogProducts\Services;

use Magento\Directory\Helper\Data;
use Magento\Store\Model\ScopeInterface as ScopeConfig;
use Monogo\TypesenseCore\Services\ConfigService as CoreConfigService;

class ConfigService extends CoreConfigService
{
    /**
     * Config paths
     */
    const TYPESENSE_PRODUCTS_ENABLED = 'typesense_products/settings/enabled';
    const TYPESENSE_PRODUCTS_SCHEMA = 'typesense_products/settings/schema';
    const TYPESENSE_PRODUCTS_INDEX_ALL = 'typesense_products/settings/index_all';
    const TYPESENSE_PRODUCTS_CUSTOMER_GROUPS_ENABLE = 'typesense_products/settings/customer_groups_enable';
    const TYPESENSE_PRODUCTS_SHOW_OUT_OF_STOCK = 'cataloginventory/options/index_child';
    const TYPESENSE_PRODUCTS_EMBEDDINGS_ENABLE = 'typesense_products/embeddings/enable_embeddings';
    const TYPESENSE_PRODUCTS_EMBEDDINGS_ENABLE_CHILDREN = 'typesense_products/embeddings/enable_embeddings_children';
    const TYPESENSE_PRODUCTS_EMBEDDINGS_MODEL_NAME = 'typesense_products/embeddings/embeddings_model_name';
    const TYPESENSE_PRODUCTS_EMBEDDINGS_API_KEY = 'typesense_products/embeddings/embeddings_api_key';
    const TYPESENSE_PRODUCTS_EMBEDDINGS_FIELDS = 'typesense_products/embeddings/embedding_fields';

    /**
     * @param int|null $storeId
     * @return bool|null
     */
    public function isEnabled(?int $storeId = null): ?bool
    {
        return (bool)$this->scopeConfig->getValue(
            self::TYPESENSE_PRODUCTS_ENABLED,
            ScopeConfig::SCOPE_STORE,
            $storeId
        );
    }

    /**
     * @param int|null $storeId
     * @return bool
     */
    public function isCustomerGroupsEnabled(?int $storeId = null): bool
    {
        return $this->scopeConfig->isSetFlag(
            self::TYPESENSE_PRODUCTS_CUSTOMER_GROUPS_ENABLE,
            ScopeConfig::SCOPE_STORE,
            $storeId
        );
    }

    /**
     * @param int|null $storeId
     * @return string|null
     */
    public function getStoreLocale(?int $storeId = null): ?string
    {
        return $this->scopeConfig->getValue(
            Data::XML_PATH_DEFAULT_LOCALE,
            ScopeConfig::SCOPE_STORE,
            $storeId
        );
    }

    /**
     * @param $storeId
     * @return bool
     */
    public function getIndexAll($storeId = null): bool
    {
        return $this->scopeConfig->isSetFlag(
            self::TYPESENSE_PRODUCTS_INDEX_ALL,
            ScopeConfig::SCOPE_STORE,
            $storeId
        );
    }

    /**
     * @param $storeId
     * @return bool
     */
    public function getShowOutOfStock($storeId = null): bool
    {
        return $this->scopeConfig->isSetFlag(
            self::TYPESENSE_PRODUCTS_SHOW_OUT_OF_STOCK,
            ScopeConfig::SCOPE_STORE,
            $storeId
        );
    }

    /**
     * @param $storeId
     * @return bool
     */
    public function isEmbeddingsEnabled($storeId = null): bool
    {
        return $this->scopeConfig->getValue(
                self::TYPESENSE_PRODUCTS_EMBEDDINGS_ENABLE,
                ScopeConfig::SCOPE_STORE,
                $storeId
            ) && !empty($this->getEmbeddingsApiKey($storeId)) && !empty($this->getEmbeddingsModelName($storeId)) && !empty($this->getEmbeddingsFields($storeId));
    }

    /**
     * @param $storeId
     * @return bool
     */
    public function isEmbeddingsEnabledForChildren($storeId = null): bool
    {
        return (bool)$this->scopeConfig->getValue(
            self::TYPESENSE_PRODUCTS_EMBEDDINGS_ENABLE_CHILDREN,
            ScopeConfig::SCOPE_STORE,
            $storeId
        );
    }

    /**
     * @param $storeId
     * @return string|null
     */
    public function getEmbeddingsApiKey($storeId = null): ?string
    {
        $value = $this->scopeConfig->getValue(
            self::TYPESENSE_PRODUCTS_EMBEDDINGS_API_KEY,
            ScopeConfig::SCOPE_STORE,
            $storeId
        );
        return $this->encryptor->decrypt($value);
    }

    /**
     * @param $storeId
     * @return string|null
     */
    public function getEmbeddingsModelName($storeId = null): ?string
    {
        return $this->scopeConfig->getValue(
            self::TYPESENSE_PRODUCTS_EMBEDDINGS_MODEL_NAME,
            ScopeConfig::SCOPE_STORE,
            $storeId
        );
    }

    /**
     * @param $storeId
     * @return array
     */
    public function getEmbeddingsFields($storeId = null): array
    {
        $attributes = [];
        $attrs = $this->unserialize($this->scopeConfig->getValue(
            self::TYPESENSE_PRODUCTS_EMBEDDINGS_FIELDS,
            ScopeConfig::SCOPE_STORE,
            $storeId
        ));
        if (is_array($attrs)) {
            foreach ($attrs as $attr) {
                $attributes[] = $attr['name'];
            }
            return $attributes;
        }
        return [];
    }

    /**
     * @param $storeId
     * @return array
     */
    public function getSchema($storeId = null): array
    {
        $attributes = [];
        $booleanProperties = ['facet', 'optional', 'index', 'infix', 'sort'];
        $attrs = $this->unserialize($this->scopeConfig->getValue(
            self::TYPESENSE_PRODUCTS_SCHEMA,
            ScopeConfig::SCOPE_STORE,
            $storeId
        ));
        if (is_array($attrs)) {

            foreach ($attrs as $attr) {
                foreach ($attr as $key => $item) {
                    if (in_array($key, $booleanProperties)) {
                        $attr[$key] = (bool)$item;
                    }
                }
                if (!$attr['index']) {
                    $attr['optional'] = true;
                }
                $attributes[$attr['name']] = $attr;
            }
            return $attributes;
        }
        return [];
    }
}
