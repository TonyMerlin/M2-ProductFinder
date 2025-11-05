<?php
declare(strict_types=1);

namespace Merlin\ProductFinder\Block\Adminhtml\System\Config\Field;

use Magento\Backend\Block\Template\Context;
use Magento\Config\Block\System\Config\Form\Field;
use Magento\Framework\Data\Form\Element\AbstractElement;
use Magento\Eav\Model\Config as EavConfig;
use Magento\Eav\Model\ResourceModel\Entity\Attribute\Set\CollectionFactory as AttributeSetCollectionFactory;
use Magento\Catalog\Model\Product;

class Profiles extends Field
{
    protected AttributeSetCollectionFactory $setCollectionFactory;
    protected EavConfig $eavConfig;

    public function __construct(
        Context $context,
        AttributeSetCollectionFactory $setCollectionFactory,
        EavConfig $eavConfig,
        array $data = []
    ) {
        parent::__construct($context, $data);
        $this->setCollectionFactory = $setCollectionFactory;
        $this->eavConfig = $eavConfig;
    }

    protected function _getElementHtml(AbstractElement $element)
    {
        // Underlying textarea = actual config field (must remain in DOM)
        $textareaHtml = parent::_getElementHtml($element);

        // Current JSON (escaped); when empty at this scope, keep "{}"
        $initialJson = (string)$element->getEscapedValue();
        if ($initialJson === '') {
            $initialJson = '{}';
        }
        $initialEsc = $this->escapeHtmlAttr($initialJson);

        $fieldId = $element->getHtmlId();

        // Build attribute-set <option>s server-side so the select is usable even if JS fails.
        $optionsHtml = $this->renderAttributeSetOptions();

        return <<<HTML
<div class="merlin-pf-profiles"
     data-field-id="{$fieldId}"
     data-initial="{$initialEsc}">

    <div class="admin__field field">
        <div class="admin__field-control">

            <div class="merlin-pf-toolbar" style="margin-bottom:8px;">
                <button type="button" class="action-default" data-mpf-new-profile>New/Reset</button>
                <button type="button" class="action-default" data-mpf-add-section>Add Section</button>
                <button type="button" class="action-default" data-mpf-add-extra>Add Extra Attribute</button>
                <button type="button" class="action-default" data-mpf-save-json>Save to JSON field</button>
            </div>

            <div class="merlin-pf-row" style="margin-bottom:8px;">
                <label style="display:inline-block; width:160px;">Attribute Set</label>
                <select data-mpf-attrset style="min-width:260px;">
                    <option value="">-- Select Attribute Set --</option>
                    {$optionsHtml}
                </select>
            </div>

            <div data-mpf-sections></div>

            <!-- Keep original textarea so scope inheritance & saving work -->
            <div style="margin-top:10px;">{$textareaHtml}</div>
        </div>
    </div>
</div>

<script type="text/x-magento-init">
{
  ".merlin-pf-profiles": {
    "Merlin_ProductFinder/js/profiles": {}
  }
}
</script>
HTML;
    }

    private function renderAttributeSetOptions(): string
    {
        $entityTypeId = (int) $this->eavConfig->getEntityType(Product::ENTITY)->getEntityTypeId();
        $col = $this->setCollectionFactory->create();
        $col->setEntityTypeFilter($entityTypeId)
            ->setOrder('attribute_set_name', 'ASC');

        $buf = [];
        foreach ($col as $set) {
            $id   = (int)$set->getAttributeSetId();
            $name = (string)$set->getAttributeSetName();
            $buf[] = '<option value="' . $id . '">' . $this->escapeHtml($name) . '</option>';
        }
        return implode('', $buf);
    }
}
