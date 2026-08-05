<?php
declare(strict_types=1);

namespace Monogo\TypesenseCatalogProducts\Controller\Adminhtml\Filters;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\Controller\ResultFactory;
use Magento\Framework\Controller\ResultInterface;
use Magento\Rule\Model\Condition\AbstractCondition;
use Monogo\TypesenseCatalogProducts\Model\RuleFactory;
use Monogo\TypesenseCatalogProducts\Services\ConfigService;

/**
 * Returns the markup of a single condition row requested by the rule tree javascript
 */
class Conditions extends Action implements HttpPostActionInterface
{
    /**
     * @var string
     */
    const ADMIN_RESOURCE = 'Monogo_Typesense::config';

    /**
     * Only fields known to belong to the indexer filters group may be addressed
     *
     * @var string[]
     */
    const ALLOWED_FIELDS = [
        ConfigService::TYPESENSE_PRODUCTS_FILTERS_FIELD_CONDITIONS,
        ConfigService::TYPESENSE_PRODUCTS_FILTERS_FIELD_CHILDREN_CONDITIONS,
    ];

    /**
     * @var RuleFactory
     */
    private RuleFactory $ruleFactory;

    /**
     * @param Context $context
     * @param RuleFactory $ruleFactory
     */
    public function __construct(
        Context     $context,
        RuleFactory $ruleFactory
    )
    {
        $this->ruleFactory = $ruleFactory;
        parent::__construct($context);
    }

    /**
     * @return ResultInterface
     */
    public function execute()
    {
        $result = $this->resultFactory->create(ResultFactory::TYPE_RAW);

        $typeData = explode('|', str_replace('-', '/', (string)$this->getRequest()->getParam('type', '')));
        $className = $typeData[0];

        if (!$this->isAllowedConditionClass($className)) {
            return $result->setContents('');
        }

        $field = $this->getFieldId();
        $rule = $this->ruleFactory->create()->setConditionsPrefix($field);

        $condition = $this->_objectManager->create($className)
            ->setId($this->getRequest()->getParam('id'))
            ->setType($className)
            ->setRule($rule)
            ->setPrefix($field);

        if (!empty($typeData[1])) {
            $condition->setAttribute($typeData[1]);
        }

        if (!$condition instanceof AbstractCondition) {
            return $result->setContents('');
        }

        $condition->setElementName($this->getElementName($field));
        $condition->setJsFormObject($this->getRequest()->getParam('form'));

        return $result->setContents($condition->asHtmlRecursive());
    }

    /**
     * @return string
     */
    private function getFieldId(): string
    {
        $field = (string)$this->getRequest()->getParam('field');

        return in_array($field, self::ALLOWED_FIELDS, true)
            ? $field
            : ConfigService::TYPESENSE_PRODUCTS_FILTERS_FIELD_CONDITIONS;
    }

    /**
     * @param string $field
     * @return string
     */
    private function getElementName(string $field): string
    {
        return sprintf(
            'groups[%s][fields][%s][value]',
            ConfigService::TYPESENSE_PRODUCTS_FILTERS_GROUP,
            $field
        );
    }

    /**
     * @param string $className
     * @return bool
     */
    private function isAllowedConditionClass(string $className): bool
    {
        return $className !== ''
            && str_starts_with($className, 'Monogo\\TypesenseCatalogProducts\\Model\\Rule\\Condition\\')
            && class_exists($className);
    }
}
