<?php
declare(strict_types=1);

namespace Merlin\ProductFinder\Block;

use Magento\Catalog\Model\ResourceModel\Category\CollectionFactory as CategoryCollectionFactory;
use Magento\Eav\Api\AttributeRepositoryInterface;
use Magento\Eav\Api\Data\AttributeOptionInterface;
use Magento\Eav\Model\Config as EavConfig;
use Magento\Eav\Model\Entity\TypeFactory as EntityTypeFactory;
use Magento\Eav\Model\ResourceModel\Entity\Attribute\Set\CollectionFactory as AttributeSetCollectionFactory;
use Magento\Framework\View\Element\Template;
use Magento\Store\Model\ScopeInterface;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Catalog\Model\ResourceModel\Product\CollectionFactory as ProductCollectionFactory;
use Merlin\ProductFinder\Helper\Data;

class Form extends Template
{
    protected $_template = 'Merlin_ProductFinder::form.phtml';

    private Data $helper;
    private CategoryCollectionFactory $categoryCollectionFactory;
    private EavConfig $eavConfig;
    private StoreManagerInterface $storeManager;
    private AttributeSetCollectionFactory $attrSetCollectionFactory;
    private EntityTypeFactory $entityTypeFactory;
    private AttributeRepositoryInterface $attributeRepository;

    public function __construct(
        Template\Context $context,
        Data $helper,
        CategoryCollectionFactory $categoryCollectionFactory,
        EavConfig $eavConfig,
        StoreManagerInterface $storeManager,
        AttributeSetCollectionFactory $attrSetCollectionFactory,
        EntityTypeFactory $entityTypeFactory,
        AttributeRepositoryInterface $attributeRepository,
        ProductCollectionFactory $productCollectionFactory,
        array $data = []
    ) {
        parent::__construct($context, $data);
        $this->helper                    = $helper;
        $this->categoryCollectionFactory = $categoryCollectionFactory;
        $this->eavConfig                 = $eavConfig;
        $this->storeManager              = $storeManager;
        $this->attrSetCollectionFactory  = $attrSetCollectionFactory;
        $this->entityTypeFactory         = $entityTypeFactory;
        $this->attributeRepository       = $attributeRepository;
        $this->productCollectionFactory  = $productCollectionFactory;
    }

    /* ==================== Basic passthroughs ==================== */

    public function getPreHtml(): string
    {
        return (string) $this->helper->getConfig('layout/pre_html');
    }

    public function getPostHtml(): string
    {
        return (string) $this->helper->getConfig('layout/post_html');
    }

    public function getConfig($path)
    {
        return $this->helper->getConfig($path);
    }

    /* ==================== Attribute sets from config ==================== */
    /**
     * Reads allowed attribute sets from:
     *   merlin_productfinder/general/allowed_attribute_sets
     * Scope fallback STORE -> WEBSITE -> DEFAULT.
     * Returns [attribute_set_id => attribute_set_name].
     */
    public function getAllowedAttributeSets(): array
    {
        $csv = (string) $this->_scopeConfig->getValue(
            'merlin_productfinder/general/allowed_attribute_sets',
            ScopeInterface::SCOPE_STORE
        );
        if ($csv === '') {
            $csv = (string) $this->_scopeConfig->getValue(
                'merlin_productfinder/general/allowed_attribute_sets',
                ScopeInterface::SCOPE_WEBSITE
            );
        }
        if ($csv === '') {
            $csv = (string) $this->_scopeConfig->getValue(
                'merlin_productfinder/general/allowed_attribute_sets'
            );
        }

        $ids = array_values(
            array_filter(
                array_map('intval', array_map('trim', explode(',', $csv)))
            )
        );

        $entityType   = $this->entityTypeFactory->create()->loadByCode('catalog_product');
        $entityTypeId = (int) $entityType->getId();

        $col = $this->attrSetCollectionFactory->create();
        $col->addFieldToFilter('entity_type_id', $entityTypeId);
        if (!empty($ids)) {
            $col->addFieldToFilter('attribute_set_id', ['in' => $ids]);
        }
        $col->setOrder('sort_order', 'ASC');

        $out = [];
        foreach ($col as $set) {
            $out[(int) $set->getAttributeSetId()] = (string) $set->getAttributeSetName();
        }
        return $out;
    }

    /**
     * Collect all mapped attribute codes from the per-attribute-set profiles
     * (sections + extras) and preload their options for the current store.
     *
     * @return array<string, array<int, array{value:string,label:string}>>
     */
    public function getPreloadedOptionsFromProfiles(): array
    {
        $profiles = $this->getAttributeSetProfiles();
        if (!$profiles || !is_array($profiles)) {
            return [];
        }

        $codes = [];
        foreach ($profiles as $profile) {
            if (!is_array($profile)) {
                continue;
            }

            // sections + map: logical -> attribute_code
            $sections = isset($profile['sections']) && is_array($profile['sections'])
                ? $profile['sections'] : [];

            $map = isset($profile['map']) && is_array($profile['map'])
                ? $profile['map'] : [];

            foreach ($sections as $logical) {
                $logical = (string)$logical;
                if ($logical === '') {
                    continue;
                }
                $mapped = $map[$logical] ?? $map[strtolower($logical)] ?? null;
                if ($mapped) {
                    $codes[] = (string)$mapped;
                }
            }

            // extras: key -> attribute_code
            $extras = isset($profile['extras']) && is_array($profile['extras'])
                ? $profile['extras'] : [];

            foreach ($extras as $attrCode) {
                if ($attrCode) {
                    $codes[] = (string)$attrCode;
                }
            }
        }

        $codes = array_values(array_unique(array_filter(array_map('trim', $codes))));
        if (!$codes) {
            return [];
        }

        return $this->preloadOptionsByCodes($codes);
    }

    /* ==================== Per-attribute-set profiles JSON ==================== */
    /**
     * Reads profiles JSON from:
     *   merlin_productfinder/general/attribute_set_profiles
     * Scope fallback STORE -> WEBSITE -> DEFAULT.
     * Treats "" and "[]" as empty so we can inherit from broader scopes.
     * Returns associative array keyed by attribute_set_id.
     */
    public function getAttributeSetProfiles(): array
    {
        $path = 'merlin_productfinder/general/attribute_set_profiles';

        $isEmpty = static function (?string $raw): bool {
            if ($raw === null) return true;
            $raw = trim($raw);
            if ($raw === '') return true;
            if ($raw === '[]') return true;
            return false;
        };

        $candidates = [
            $this->_scopeConfig->getValue($path, ScopeInterface::SCOPE_STORE),
            $this->_scopeConfig->getValue($path, ScopeInterface::SCOPE_WEBSITE),
            $this->_scopeConfig->getValue($path), // default
        ];

        foreach ($candidates as $raw) {
            if ($isEmpty($raw)) {
                continue;
            }
            try {
                $data = json_decode((string)$raw, true, 512, JSON_THROW_ON_ERROR);
                if (is_array($data) && !empty($data)) {
                    return $data;
                }
            } catch (\Throwable $e) {
                // try next scope
            }
        }

        return [];
    }

    /* ==================== Legacy top categories helper (optional) ==================== */
    public function getTopCategories(): array
    {
        $idsCsv = (string) $this->helper->getConfig('general/top_categories');
        if ($idsCsv === '') {
            return [];
        }

        $ids = array_values(
            array_filter(
                array_map('intval', array_map('trim', explode(',', $idsCsv)))
            )
        );
        if (!$ids) {
            return [];
        }

        $col = $this->categoryCollectionFactory->create();
        $col->addAttributeToSelect('name')
            ->addFieldToFilter('entity_id', ['in' => $ids]);

        $out = [];
        foreach ($col as $cat) {
            $out[] = [
                'id'   => (int) $cat->getId(),
                'name' => (string) $cat->getName()
            ];
        }
        return $out;
    }

/**
 * Build a per-attribute-set, per-attribute list of option {value,label}
 * restricted to products that are in stock, enabled and visible.
 *
 * Shape:
 * [
 *   "<setId>" => [
 *      "<attr_code>" => [ [value => "12", label => "Black"], ... ],
 *      ...
 *   ],
 *   ...
 * ]
 */
public function getOptionsBySetFilteredByStock(): array
{
    $profiles = $this->getAttributeSetProfiles();
    if (!$profiles || !is_array($profiles)) {
        return [];
    }

    $websiteId = (int)$this->storeManager->getStore()->getWebsiteId();

    $result = [];

    foreach ($profiles as $setId => $profile) {
        $setId = (string)$setId;
        if (!is_array($profile)) {
            continue;
        }

        // Collect attribute codes used by this profile (sections map + extras)
        $codes = [];
        $sections = isset($profile['sections']) && is_array($profile['sections']) ? $profile['sections'] : [];
        $map      = isset($profile['map']) && is_array($profile['map']) ? $profile['map'] : [];
        foreach ($sections as $logical) {
            $logical = (string)$logical;
            if ($logical === '') continue;
            $mapped = $map[$logical] ?? $map[strtolower($logical)] ?? $logical;
            if ($mapped) $codes[] = (string)$mapped;
        }
        $extras = isset($profile['extras']) && is_array($profile['extras']) ? $profile['extras'] : [];
        foreach ($extras as $extraCode) {
            if ($extraCode) $codes[] = (string)$extraCode;
        }

        $codes = array_values(array_unique(array_filter(array_map('trim', $codes))));
        if (empty($codes)) {
            $result[$setId] = [];
            continue;
        }

        // Build product collection for this set, in-stock, enabled, visible
        $col = $this->productCollectionFactory->create();
        $col->addAttributeToSelect($codes);
        $col->addAttributeToFilter('status', 1);
        $col->addAttributeToFilter('visibility', ['in' => [2,3,4]]);
        $col->addAttributeToFilter('attribute_set_id', (int)$setId);

        // Join legacy stock status (works with MSI-populated view on most setups)
        // Note: using inner join to enforce only in-stock (stock_status = 1)
        $col->getSelect()->join(
            ['css' => $col->getTable('cataloginventory_stock_status')],
            'css.product_id = e.entity_id AND css.website_id = ' . (int)$websiteId,
            ['stock_status']
        )->where('css.stock_status = 1');

        // Collect used option IDs per attribute
        $used = [];
        foreach ($codes as $code) {
            $used[$code] = [];
        }

        foreach ($col as $product) {
            foreach ($codes as $code) {
                $val = $product->getData($code);
                if ($val === null || $val === '' || $val === false) {
                    continue;
                }
                if (is_array($val)) {
                    foreach ($val as $v) {
                        if ($v !== '' && $v !== null) $used[$code][(string)$v] = true;
                    }
                } else {
                    // multi-selects are comma separated strings
                    $parts = explode(',', (string)$val);
                    foreach ($parts as $p) {
                        $p = trim($p);
                        if ($p !== '') $used[$code][$p] = true;
                    }
                }
            }
        }

        // Convert used IDs to [{value,label}] using attribute options
        $result[$setId] = [];
        foreach ($codes as $code) {
            $ids = array_keys($used[$code] ?? []);
            if (empty($ids)) {
                $result[$setId][$code] = [];
                continue;
            }
            // Index options by value for quick filter
            $allOpts = $this->getAttributeOptions($code);
            if (empty($allOpts)) {
                $result[$setId][$code] = [];
                continue;
            }
            $present = [];
            $allowed = array_flip($ids);
            foreach ($allOpts as $opt) {
                $v = isset($opt['value']) ? (string)$opt['value'] : '';
                if (isset($allowed[$v])) {
                    // keep raw label (may include apostrophes etc.)
                    $present[] = ['value' => $v, 'label' => (string)($opt['label'] ?? $v)];
                }
            }
            $result[$setId][$code] = $present;
        }
    }

    return $result;
}

/** Convenience for template to emit compact JSON safely. */
public function getOptionsBySetFilteredByStockJson(): string
{
    $map = $this->getOptionsBySetFilteredByStock();
    try {
        return (string)json_encode($map, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    } catch (\Throwable $e) {
        return '{}';
    }
}

    /* ==================== Attribute options (robust + swatch-safe) ==================== */
    /**
     * Returns store-scoped options for a product attribute code.
     * Tries repository path, source model path, and swatch-safe option collection path.
     * Output: [['value' => 'x', 'label' => 'Y'], ...]
     */
    public function getAttributeOptions(string $attrCode): array
    {
        $attrCode = trim((string)$attrCode);
        if ($attrCode === '') {
            return [];
        }

        $storeId = (int) $this->storeManager->getStore()->getId();

        // Normalizer for both object-based and array-based rows.
        $normalize = static function ($options): array {
            $out = [];
            foreach ((array)$options as $opt) {
                if (is_object($opt) && method_exists($opt, 'getValue')) {
                    $value = (string)$opt->getValue();
                    $label = (string)$opt->getLabel();
                } else {
                    $value = isset($opt['value']) ? (string)$opt['value'] : '';
                    $label = isset($opt['label']) ? (string)$opt['label'] : '';
                }

                // Skip pure placeholders only
                $isPlaceholder = ($value === '' || $value === null) ||
                                 (trim($label) === '' && $value === '0') ||
                                 (stripos($label, 'please select') !== false);

                if (!$isPlaceholder) {
                    $out[] = ['value' => $value, 'label' => ($label !== '' ? $label : $value)];
                }
            }
            return $out;
        };

        // 1) Repository path
        try {
            $attribute = $this->attributeRepository->get('catalog_product', $attrCode);
            if (method_exists($attribute, 'setStoreId')) {
                $attribute->setStoreId($storeId);
            }
            $options = $attribute->getOptions();
            $norm = $normalize($options ?? []);
            if (!empty($norm)) {
                return $norm;
            }
        } catch (\Throwable $e) {
            // continue
        }

        // 2) Source model path
        try {
            $attribute = $this->eavConfig->getAttribute('catalog_product', $attrCode);
            if ($attribute && $attribute->getId()) {
                $attribute->setStoreId($storeId);
                if (!$attribute->getEntityTypeId()) {
                    $attribute->setEntityTypeId(
                        (int)$this->eavConfig->getEntityType('catalog_product')->getEntityTypeId()
                    );
                }
                $options = $attribute->getSource()->getAllOptions(false);
                $norm = $normalize($options ?? []);
                if (!empty($norm)) {
                    return $norm;
                }
            }
        } catch (\Throwable $e) {
            // continue
        }

        // 3) Swatch-safe fallback (direct option table query via collection)
        try {
            /** @var \Magento\Eav\Model\ResourceModel\Entity\Attribute\Option\CollectionFactory $optColFactory */
            $optColFactory = \Magento\Framework\App\ObjectManager::getInstance()
                ->get(\Magento\Eav\Model\ResourceModel\Entity\Attribute\Option\CollectionFactory::class);

            $attribute = $this->eavConfig->getAttribute('catalog_product', $attrCode);
            if ($attribute && $attribute->getId()) {
                $collection = $optColFactory->create()
                    ->setPositionOrder('asc')
                    ->setAttributeFilter((int)$attribute->getId())
                    ->setStoreFilter($storeId)
                    ->load();

                $rows = [];
                foreach ($collection as $opt) {
                    $rows[] = [
                        'value' => (string)$opt->getId(),
                        'label' => (string)$opt->getValue(),
                    ];
                }
                $norm = $normalize($rows);
                if (!empty($norm)) {
                    return $norm;
                }
            }
        } catch (\Throwable $e) {
            // final fallback
        }

        return [];
    }

    /**
     * Preload multiple attribute codes at once.
     * @param string[] $codes
     * @return array<string, array<int, array{value:string,label:string}>>
     */
    public function preloadOptionsByCodes(array $codes): array
    {
        $codes = array_values(array_unique(array_filter(array_map('trim', $codes))));
        $out   = [];
        foreach ($codes as $code) {
            $out[$code] = $this->getAttributeOptions($code);
        }
        return $out;
    }
}
