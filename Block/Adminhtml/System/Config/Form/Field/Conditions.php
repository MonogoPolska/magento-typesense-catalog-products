<?php
declare(strict_types=1);

namespace Monogo\TypesenseCatalogProducts\Block\Adminhtml\System\Config\Form\Field;

use Magento\Backend\Block\Template\Context;
use Magento\Config\Block\System\Config\Form\Field;
use Magento\Framework\Data\Form\Element\AbstractElement;
use Magento\Framework\Data\Form\Element\Factory as ElementFactory;
use Magento\Framework\View\Helper\SecureHtmlRenderer;
use Magento\Rule\Block\Conditions as ConditionsRenderer;
use Monogo\TypesenseCatalogProducts\Model\Rule;
use Monogo\TypesenseCatalogProducts\Model\RuleFactory;

/**
 * Renders the indexer filter conditions tree inside the system configuration form
 */
class Conditions extends Field
{
    /**
     * @var string
     */
    protected $_template = 'Monogo_TypesenseCatalogProducts::system/config/conditions.phtml';

    /**
     * @var ElementFactory
     */
    private ElementFactory $elementFactory;

    /**
     * @var ConditionsRenderer
     */
    private ConditionsRenderer $conditionsRenderer;

    /**
     * @var RuleFactory
     */
    private RuleFactory $ruleFactory;

    /**
     * @var AbstractElement|null
     */
    private ?AbstractElement $element = null;

    /**
     * @var Rule|null
     */
    private ?Rule $rule = null;

    /**
     * @param Context $context
     * @param ElementFactory $elementFactory
     * @param ConditionsRenderer $conditionsRenderer
     * @param RuleFactory $ruleFactory
     * @param array $data
     * @param SecureHtmlRenderer|null $secureRenderer
     */
    public function __construct(
        Context             $context,
        ElementFactory      $elementFactory,
        ConditionsRenderer  $conditionsRenderer,
        RuleFactory         $ruleFactory,
        array               $data = [],
        ?SecureHtmlRenderer $secureRenderer = null
    )
    {
        $this->elementFactory = $elementFactory;
        $this->conditionsRenderer = $conditionsRenderer;
        $this->ruleFactory = $ruleFactory;
        parent::__construct($context, $data, $secureRenderer);
    }

    /**
     * The block is a layout singleton shared by every field using it, so the state is rebuilt on each render
     *
     * @param AbstractElement $element
     * @return string
     */
    protected function _getElementHtml(AbstractElement $element)
    {
        $this->element = $element;
        $this->rule = $this->ruleFactory->create();
        $this->rule->setConditionsPrefix($this->getFieldId() ?: 'conditions');

        $value = $this->getStoredConditions($element);
        if (!empty($value['conditions'])) {
            $this->rule->loadPost(['conditions' => $value['conditions']]);
        }

        $this->rule->getConditions()
            ->setElementName((string)$element->getName())
            ->setJsFormObject($this->getJsFormObject());

        return $this->_toHtml();
    }

    /**
     * The backend model already decodes the value, unless it originates from a dumped app/etc/config.php
     *
     * @param AbstractElement $element
     * @return array
     */
    private function getStoredConditions(AbstractElement $element): array
    {
        $value = $element->getValue();

        if (is_string($value) && $value !== '') {
            $value = json_decode($value, true);
        }

        return is_array($value) ? $value : [];
    }

    /**
     * @return AbstractElement|null
     */
    public function getElement(): ?AbstractElement
    {
        return $this->element;
    }

    /**
     * Identifier of the VarienRulesForm instance, also used as the id of the tree wrapper
     *
     * @return string
     */
    public function getJsFormObject(): string
    {
        return $this->element ? $this->element->getHtmlId() . '_rule' : 'typesense_indexer_filter_rule';
    }

    /**
     * @return bool
     */
    public function isDisabled(): bool
    {
        return $this->element && (bool)$this->element->getDisabled();
    }

    /**
     * @return string
     */
    public function getNewChildUrl(): string
    {
        return $this->getUrl(
            'typesense_products/filters/conditions',
            [
                'form' => $this->getJsFormObject(),
                'field' => $this->getFieldId(),
            ]
        );
    }

    /**
     * The element id is the full config path, the field id has to be taken from the generated input name
     *
     * @return string
     */
    private function getFieldId(): string
    {
        $name = $this->element ? (string)$this->element->getName() : '';

        return preg_match('/\[fields]\[([^]]+)]\[value]/', $name, $matches) ? $matches[1] : '';
    }

    /**
     * @return string
     */
    public function getInputHtml(): string
    {
        $input = $this->elementFactory->create('text');
        $input->setRule($this->rule)->setRenderer($this->conditionsRenderer);

        return $input->toHtml();
    }
}
