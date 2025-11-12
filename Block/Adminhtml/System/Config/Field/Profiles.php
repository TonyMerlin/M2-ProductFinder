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
    /** Use the template renderer instead of echoing HTML inline */
    protected $_template = 'Merlin_ProductFinder::system/config/profiles.phtml';

    private AttributeSetCollectionFactory $setCollectionFactory;
    private EavConfig $eavConfig;

    public function __construct(
        Context $context,
        AttributeSetCollectionFactory $setCollectionFactory,
        EavConfig $eavConfig,
        array $data = []
    ) {
        parent::__construct($context, $data);
        $this->setCollectionFactory = $setCollectionFactory;
        $this->eavConfig            = $eavConfig;
    }

    /** Magento calls this to render the field; delegate to the template */
    protected function _getElementHtml(AbstractElement $element)
    {
        $this->setElement($element);
        return $this->_toHtml();
    }

    /**
     * Template helper: attribute sets (sorted A→Z)
     * @return array<int, array{id:int,name:string}>
     */
    public function getProductAttributeSets(): array
    {
        $entityTypeId = (int)$this->eavConfig->getEntityType(Product::ENTITY)->getEntityTypeId();
        $col = $this->setCollectionFactory->create();
        $col->setEntityTypeFilter($entityTypeId)
            ->setOrder('attribute_set_name', 'ASC');

        $out = [];
        foreach ($col as $set) {
            $out[] = [
                'id'   => (int)$set->getAttributeSetId(),
                'name' => (string)$set->getAttributeSetName(),
            ];
        }
        return $out;
    }

    /** Pass the textarea’s HTML id (so JS reads/writes the real config value) */
    public function getHtmlId(): string
    {
        return $this->getElement()->getHtmlId();
    }

    /** Convenience if you need the form field name in the template */
    public function getName(): string
    {
        return $this->getElement()->getName();
    }

    /** (Optional) Current JSON value if you want to pre-fill anything */
    public function getProfilesJson(): string
    {
        $val = (string)$this->getElement()->getData('value');
        return $val !== '' ? $val : '{}';
    }

    /** URL for image AJAX upload used by profiles.js */
    public function getImageUploadUrl(): string
    {
        return $this->getUrl('productfinder/profile_image/upload');
    }
}
