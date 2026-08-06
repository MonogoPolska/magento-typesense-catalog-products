<?php
declare(strict_types=1);

namespace Monogo\TypesenseCatalogProducts\Model;

use Magento\Framework\Api\AttributeValueFactory;
use Magento\Framework\Api\ExtensionAttributesFactory;
use Magento\Framework\Data\Collection\AbstractDb;
use Magento\Framework\Data\FormFactory;
use Magento\Framework\Model\Context;
use Magento\Framework\Model\ResourceModel\AbstractResource;
use Magento\Framework\Registry;
use Magento\Framework\Serialize\Serializer\Json;
use Magento\Framework\Stdlib\DateTime\TimezoneInterface;
use Magento\Rule\Model\AbstractModel;
use Monogo\TypesenseCatalogProducts\Model\Rule\Condition\CombineFactory;

/**
 * Container for the indexer filter conditions tree
 *
 * The rule is never persisted on its own, it only provides the form and the conditions instance required by the
 * Magento rule condition classes. Conditions are stored in the module configuration.
 */
class Rule extends AbstractModel
{
    /**
     * @var CombineFactory
     */
    private CombineFactory $conditionsFactory;

    /**
     * @var string
     */
    private string $conditionsPrefix = 'conditions';

    /**
     * @param Context $context
     * @param Registry $registry
     * @param FormFactory $formFactory
     * @param TimezoneInterface $localeDate
     * @param CombineFactory $conditionsFactory
     * @param AbstractResource|null $resource
     * @param AbstractDb|null $resourceCollection
     * @param array $data
     * @param ExtensionAttributesFactory|null $extensionFactory
     * @param AttributeValueFactory|null $customAttributeFactory
     * @param Json|null $serializer
     */
    public function __construct(
        Context                    $context,
        Registry                   $registry,
        FormFactory                $formFactory,
        TimezoneInterface          $localeDate,
        CombineFactory              $conditionsFactory,
        ?AbstractResource           $resource = null,
        ?AbstractDb                 $resourceCollection = null,
        array                       $data = [],
        ?ExtensionAttributesFactory $extensionFactory = null,
        ?AttributeValueFactory      $customAttributeFactory = null,
        ?Json                       $serializer = null
    )
    {
        $this->conditionsFactory = $conditionsFactory;
        parent::__construct(
            $context,
            $registry,
            $formFactory,
            $localeDate,
            $resource,
            $resourceCollection,
            $data,
            $extensionFactory,
            $customAttributeFactory,
            $serializer
        );
    }

    /**
     * Several condition trees are rendered on the same configuration page, a distinct prefix keeps their generated
     * input names and DOM identifiers apart. Has to be set before the conditions are accessed for the first time.
     *
     * @param string $prefix
     * @return $this
     */
    public function setConditionsPrefix(string $prefix): self
    {
        $this->conditionsPrefix = $prefix;

        return $this;
    }

    /**
     * @return string
     */
    public function getConditionsPrefix(): string
    {
        return $this->conditionsPrefix;
    }

    /**
     * @return Rule\Condition\Combine
     */
    public function getConditionsInstance()
    {
        return $this->conditionsFactory->create();
    }

    /**
     * @return null
     */
    public function getActionsInstance()
    {
        return null;
    }

    /**
     * @param \Magento\Rule\Model\Condition\Combine|null $conditions
     * @return $this
     */
    protected function _resetConditions($conditions = null)
    {
        if ($conditions === null) {
            $conditions = $this->getConditionsInstance();
        }

        $conditions->setRule($this)->setId('1')->setPrefix($this->conditionsPrefix);
        $this->setConditions($conditions);

        return $this;
    }
}
