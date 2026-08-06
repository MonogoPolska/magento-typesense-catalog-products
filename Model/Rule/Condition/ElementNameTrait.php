<?php
declare(strict_types=1);

namespace Monogo\TypesenseCatalogProducts\Model\Rule\Condition;

/**
 * Makes the hidden element name prefix configurable at runtime.
 *
 * The conditions tree is rendered inside the system configuration form, so the generated inputs have to be named
 * groups[<group>][fields][<field>][value][...] instead of the rule[...] prefix hardcoded in the Magento base classes.
 */
trait ElementNameTrait
{
    /**
     * @param string $elementName
     * @return $this
     */
    public function setElementName(string $elementName): self
    {
        $this->elementName = $elementName;

        return $this;
    }

    /**
     * @return string
     */
    public function getElementName(): string
    {
        return $this->elementName;
    }
}
