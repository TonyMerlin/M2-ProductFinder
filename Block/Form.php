<?php
declare(strict_types=1);

namespace Merlin\ProductFinder\Block;

use Magento\Catalog\Model\ResourceModel\Category\CollectionFactory as CategoryCollectionFactory;
use Magento\Catalog\Model\ResourceModel\Product\CollectionFactory as ProductCollectionFactory;
use Magento\Eav\Api\AttributeRepositoryInterface;
use Magento\Eav\Api\Data\AttributeOptionInterface;
use Magento\Eav\Model\Config as EavConfig;
use Magento\Eav\Model\Entity\TypeFactory as EntityTypeFactory;
use Magento\Eav\Model\ResourceModel\Entity\Attribute\Set\CollectionFactory as AttributeSetCollectionFactory;
use Magento\Framework\App\CacheInterface;
use Magento\Framework\View\Element\Template;
use Magento\Store\Model\ScopeInterface;
use Magento\Store\Model\StoreManagerInterface;
use Merlin\ProductFinder\Helper\Data;

class Form extends Template
{
    /** @var string */
    protected $_template = 'Merlin_ProductFinder::form.phtml';

    private Data $helper;
    private CategoryCollectionFactory $categoryCollectionFactory;
    private EavConfig $eavConfig;
    private StoreManagerInterface $storeManager;
    private AttributeSetCollectionFactory $attrSetCollectionFactory;
    private EntityTypeFactory $entityTypeFactory;
    private AttributeRepositoryInterface $attributeRepository;
    private ProductCollectionFactory $productCollectionFactory;
    private CacheInterface $cache;

    private const CACHE_PREFIX = 'merlin_pf_instock_opts_';
    private const CACHE_TAG    = 'MERLIN_PF_STOCK_OPTS';
    private const DEFAULT_TTL  = 3600; // 1 hour

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
        CacheInterface $cache,
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
        $this->cache                     = $cache;
    }

    /* ==================== Basic passthroughs ==================== */

    public function getPreHtml(): string
    {
        return (string)$this->helper->getConfig('layout/pre_html');
    }

    public function getPostHtml(): string
    {
        return (string)$this->helper->getConfig('layout/post_html');
    }

    public function getConfig($path)
    {
        return $this->helper->getConfig($path);
    }
    /* ==================== Enabled or not from config ==================== */

    protected function _toHtml()
   {
    try {
        // Respect the on/off switch in system config
        if (!$this->helper->isEnabled()) {
            return '';
        }
    } catch (\Throwable $e) {
        // In case helper/config explodes for any reason, fail closed
        return '';
    }

    return parent::_toHtml();
}

    /* ==================== Attribute sets from config ==================== */

    /**
     * Reads merlin_productfinder/general/allowed_attribute_sets (CSV).
     * Scope fallback: STORE -> WEBSITE -> DEFAULT.
     * @return array<int,string> [setId => name]
     */
    public function getAllowedAttributeSets(): array
    {
        $csv = (string)$this->_scopeConfig->getValue(
            'merlin_productfinder/general/allowed_attribute_sets',
            ScopeInterface::SCOPE_STORE
        );
        if ($csv === '') {
            $csv = (string)$this->_scopeConfig->getValue(
                'merlin_productfinder/general/allowed_attribute_sets',
                ScopeInterface::SCOPE_WEBSITE
            );
        }
        if ($csv === '') {
            $csv = (string)$this->_scopeConfig->getValue(
                'merlin_productfinder/general/allowed_attribute_sets'
            );
        }

        $ids = array_values(array_filter(array_map('intval', array_map('trim', explode(',', $csv)))));
        $entityTypeId = (int)$this->entityTypeFactory->create()->loadByCode('catalog_product')->getId();

        $col = $this->attrSetCollectionFactory->create();
        $col->addFieldToFilter('entity_type_id', $entityTypeId);
        if (!empty($ids)) {
            $col->addFieldToFilter('attribute_set_id', ['in' => $ids]);
        }
        $col->setOrder('attribute_set_name', 'ASC');

        $out = [];
        foreach ($col as $set) {
            $out[(int)$set->getAttributeSetId()] = (string)$set->getAttributeSetName();
        }
        return $out;
    }

    /* ==================== Per-attribute-set profiles JSON ==================== */

    /**
     * Reads merlin_productfinder/general/attribute_set_profiles (JSON).
     * Scope fallback: STORE -> WEBSITE -> DEFAULT. Treats "" and "[]" as empty.
     * @return array<string,array>
     */
    public function getAttributeSetProfiles(): array
    {
        $path = 'merlin_productfinder/general/attribute_set_profiles';

        $isEmpty = static function (?string $raw): bool {
            if ($raw === null) return true;
            $raw = trim($raw);
            return ($raw === '' || $raw === '[]');
        };

        $candidates = [
            $this->_scopeConfig->getValue($path, ScopeInterface::SCOPE_STORE),
            $this->_scopeConfig->getValue($path, ScopeInterface::SCOPE_WEBSITE),
            $this->_scopeConfig->getValue($path),
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
        $idsCsv = (string)$this->helper->getConfig('general/top_categories');
        if ($idsCsv === '') {
            return [];
        }

        $ids = array_values(array_filter(array_map('intval', array_map('trim', explode(',', $idsCsv)))));
        if (!$ids) {
            return [];
        }

        $col = $this->categoryCollectionFactory->create();
        $col->addAttributeToSelect('name')
            ->addFieldToFilter('entity_id', ['in' => $ids]);

        $out = [];
        foreach ($col as $cat) {
            $out[] = [
                'id'   => (int)$cat->getId(),
                'name' => (string)$cat->getName(),
            ];
        }
        return $out;
    }

    /* ==================== Caching helpers ==================== */

    /**
     * Build a stable signature based on store/website + used codes per set.
     */
    private function buildProfileSignature(array $profiles, array $setIds): string
    {
        $used = [];
        foreach ($setIds as $sid) {
            $sidKey = (string)$sid;
            $profile = $profiles[$sidKey] ?? $profiles[$sid] ?? null;
            $codes = $this->extractProfileAttributeCodes($profile);
            sort($codes);
            $used[$sidKey] = $codes;
        }
        $payload = [
            'store'   => (int)$this->storeManager->getStore()->getId(),
            'website' => (int)$this->storeManager->getStore()->getWebsiteId(),
            'sets'    => $used,
        ];
        return sha1(json_encode($payload));
    }

    private function makeCacheId(string $signature): string
    {
        return self::CACHE_PREFIX . $signature;
    }

    /**
     * Cached wrapper for in-stock options by set.
     */
    public function getInStockOptionsBySetCached(array $setIds, array $profiles, int $ttl = self::DEFAULT_TTL): array
    {
        $setIds = array_values(array_unique(array_filter(array_map('intval', $setIds))));
        if (!$setIds) {
            return [];
        }

        $sig     = $this->buildProfileSignature($profiles, $setIds);
        $cacheId = $this->makeCacheId($sig);

        $cached = $this->cache->load($cacheId);
        if ($cached !== false) {
            try {
                $decoded = json_decode($cached, true);
                if (is_array($decoded)) {
                    return $decoded;
                }
            } catch (\Throwable $e) {
                // ignore, rebuild fresh
            }
        }

        $fresh = $this->getInStockOptionsBySet($setIds, $profiles);

        try {
            $this->cache->save(
                json_encode($fresh, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                $cacheId,
                [self::CACHE_TAG],
                $ttl
            );
        } catch (\Throwable $e) {
            // ignore cache errors
        }

        return $fresh;
    }

    /* ==================== In-stock option preloading (per set) ==================== */

    /**
     * Build per-attribute-set option lists containing ONLY values that exist on
     * in-stock, enabled, visible products in that set.
     *
     * Output:
     * [
     *   "<setId>" => [
     *     "<attr_code>" => [ [value,label], ... ],
     *     ...
     *   ],
     *   ...
     * ]
     *
     * @param int[] $setIds
     * @param array $profiles
     * @return array<string, array<string, array<int, array{value:string,label:string}>>>
     */
    public function getInStockOptionsBySet(array $setIds, array $profiles): array
    {
        $setIds = array_values(array_unique(array_filter(array_map('intval', $setIds))));
        if (!$setIds) return [];

        $store     = $this->storeManager->getStore();
        $websiteId = (int)$store->getWebsiteId();

        // Figure out MSI stock id if available (safe fallback if MSI not installed)
        $stockTableAlias = null;
        $stockJoinSql    = null;
        $msiTableName    = null;

        try {
            // MSI service is optional â€” resolve lazily
            $om = \Magento\Framework\App\ObjectManager::getInstance();
            if ($om->has(\Magento\InventorySalesApi\Api\GetAssignedStockIdForWebsiteInterface::class)) {
                /** @var \Magento\InventorySalesApi\Api\GetAssignedStockIdForWebsiteInterface $stockResolver */
                $stockResolver = $om->get(\Magento\InventorySalesApi\Api\GetAssignedStockIdForWebsiteInterface::class);
                $stockId       = (int)$stockResolver->execute((string)$store->getWebsite()->getCode());
                if ($stockId > 0) {
                    $stockTableAlias = 'msi_stock';
                    $stockJoinSql    = sprintf(
                        '%1$s.sku = e.sku AND %1$s.is_salable = 1',
                        $stockTableAlias
                    );
                    $msiTableName    = 'inventory_stock_' . $stockId;
                }
            }
        } catch (\Throwable $e) {
            $stockTableAlias = null;
            $stockJoinSql    = null;
            $msiTableName    = null;
        }

        $result = [];

        foreach ($setIds as $sid) {
            $sidKey    = (string)$sid;
            $profile   = $profiles[$sidKey] ?? $profiles[$sid] ?? null;
            $attrCodes = $this->extractProfileAttributeCodes($profile);
            if (!$attrCodes) { $result[$sidKey] = []; continue; }

            $col = $this->productCollectionFactory->create();
            $col->addAttributeToSelect($attrCodes);
            $col->addAttributeToFilter('type_id', 'simple');
            $col->addAttributeToFilter('status', 1);
            // Include not-visible simples so configurable parents still surface options
            $col->addAttributeToFilter('visibility', ['in' => [1,2,3,4]]);

            // When profiles are configured on configurable parents, include their child simples
            // even if those children use a different attribute set. This mirrors the AJAX
            // filtering logic so the initial options align with progressive selections.
            $col->getSelect()
                ->joinLeft(
                    ['cpsl' => $col->getTable('catalog_product_super_link')],
                    'cpsl.product_id = e.entity_id',
                    []
                )
                ->joinLeft(
                    ['parent' => $col->getTable('catalog_product_entity')],
                    'parent.entity_id = cpsl.parent_id',
                    []
                )
                ->where('e.attribute_set_id = ?', $sid)
                ->orWhere('parent.attribute_set_id = ?', $sid)
                ->columns(['parent_id' => 'cpsl.parent_id']);

            $resource   = $col->getResource();
            $css        = $resource->getTable('cataloginventory_stock_status');

            // Try MSI join first (if we discovered a stock table)
            $didJoin = false;
            if (!empty($stockJoinSql) && !empty($msiTableName)) {
                try {
                    $col->getSelect()->joinInner(
                        [$stockTableAlias => $resource->getTable($msiTableName)],
                        $stockJoinSql,
                        []
                    );
                    $didJoin = true;
                } catch (\Throwable $e) {
                    $didJoin = false;
                }
            }

            // Fallback: legacy stock status view (works even with MSI as itâ€™s kept in sync)
            if (!$didJoin) {
                $col->getSelect()->joinInner(
                    ['css' => $css],
                    'css.product_id = e.entity_id AND css.stock_status = 1 AND css.website_id IN (0, ' . (int)$websiteId . ')',
                    []
                );
            }

            // Preload parent attribute values for the codes we care about so we can
            // fall back to the configurable parent when the child set doesn’t include
            // the attribute being checked.
            $parentValues = [];
            $parentIds    = [];
            foreach ($col as $product) {
                $pid = (int)$product->getData('parent_id');
                if ($pid > 0) {
                    $parentIds[$pid] = true;
                }
            }

            if ($parentIds) {
                $parentCol = $this->productCollectionFactory->create();
                $parentCol->addAttributeToSelect($attrCodes);
                $parentCol->addAttributeToFilter('entity_id', ['in' => array_keys($parentIds)]);
                foreach ($parentCol as $parent) {
                    $pid = (int)$parent->getId();
                    foreach ($attrCodes as $code) {
                        $parentValues[$pid][$code] = $parent->getData($code);
                    }
                }
            }

            // Collect used option IDs per attribute code (handles single & multiselect)
            $used = [];
            foreach ($attrCodes as $code) { $used[$code] = []; }

            foreach ($col as $product) {
                foreach ($attrCodes as $code) {
                    $val = $this->getProductAttributeValue($product, $code, $parentValues);
                    if ($val === null || $val === '' || $val === false) continue;

                    if (is_string($val) && strpos($val, ',') !== false) {
                        foreach (explode(',', $val) as $p) {
                            $p = trim($p);
                            if ($p !== '' && $p !== '0') $used[$code][$p] = true;
                        }
                    } elseif (is_array($val)) {
                        foreach ($val as $p) {
                            $p = (string)$p;
                            if ($p !== '' && $p !== '0') $used[$code][$p] = true;
                        }
                    } else {
                        $p = (string)$val;
                        if ($p !== '' && $p !== '0') $used[$code][$p] = true;
                    }
                }
            }

            // Map IDs ? labels (store-scoped), sort by label
            $perSetOut = [];
            foreach ($attrCodes as $code) {
                $ids = array_keys($used[$code] ?? []);
                if (!$ids) { $perSetOut[$code] = []; continue; }

                $labelMap = [];
                foreach ($this->getAttributeOptions($code) as $opt) {
                    $labelMap[(string)$opt['value']] = (string)$opt['label'];
                }

                $opts = [];
                foreach ($ids as $id) {
                    $idStr = (string)$id;
                    $opts[] = ['value' => $idStr, 'label' => $labelMap[$idStr] ?? $idStr];
                }

                usort($opts, static function ($a, $b) {
                    return strcasecmp($a['label'], $b['label']);
                });

                $perSetOut[$code] = $opts;
            }

            $result[$sidKey] = $perSetOut;
        }

        return $result;
    }

    /**
     * Helper: extract all attribute codes referenced by a single profile.
     * @param array|null $profile
     * @return string[]
     */
    private function extractProfileAttributeCodes(?array $profile): array
    {
        if (!$profile || !is_array($profile)) {
            return [];
        }

        $codes = [];

        $sections = isset($profile['sections']) && is_array($profile['sections'])
            ? $profile['sections'] : [];
        $map = isset($profile['map']) && is_array($profile['map'])
            ? $profile['map'] : [];

        foreach ($sections as $logical) {
            $logical = (string)$logical;
            if ($logical === '') {
                continue;
            }
            $mapped = $map[$logical] ?? $map[strtolower($logical)] ?? $logical;
            if ($mapped) {
                $codes[] = (string)$mapped;
            }
        }

        $extras = isset($profile['extras']) && is_array($profile['extras'])
            ? $profile['extras'] : [];

        foreach ($extras as $attrCode) {
            if ($attrCode) {
                $codes[] = (string)$attrCode;
            }
        }

        return array_values(array_unique(array_filter(array_map('trim', $codes))));
    }

    /**
     * Determine the value for an attribute, falling back to the configurable parent
     * if the child product doesn’t carry the attribute in its own set.
     *
     * @param \Magento\Catalog\Model\Product $product
     * @param string $code
     * @param array<int,array<string,mixed>> $parentValues
     * @return mixed
     */
    private function getProductAttributeValue($product, string $code, array $parentValues)
    {
        $value = $product->getData($code);
        if ($value !== null && $value !== '' && $value !== false) {
            return $value;
        }

        $pid = (int)$product->getData('parent_id');
        if ($pid > 0 && isset($parentValues[$pid][$code])) {
            return $parentValues[$pid][$code];
        }

        return $value;
    }

    /* ==================== Attribute options (robust + swatch-safe) ==================== */

    /**
     * Returns store-scoped options for an attribute code.
     * Tries repository, source model, then swatch option collection.
     * @return array<int, array{value:string,label:string}>
     */
    public function getAttributeOptions(string $attrCode): array
    {
        $attrCode = trim((string)$attrCode);
        if ($attrCode === '') {
            return [];
        }

        $storeId = (int)$this->storeManager->getStore()->getId();

        $normalize = static function ($options): array {
            $out = [];
            foreach ((array)$options as $opt) {
                if (is_object($opt) && method_exists($opt, 'getValue')) {
                    /** @var AttributeOptionInterface $opt */
                    $value = (string)$opt->getValue();
                    $label = (string)$opt->getLabel();
                } else {
                    $value = isset($opt['value']) ? (string)$opt['value'] : '';
                    $label = isset($opt['label']) ? (string)$opt['label'] : '';
                }

                $isPlaceholder =
                    ($value === '' || $value === null) ||
                    (trim($label) === '' && $value === '0') ||
                    (stripos($label, 'please select') !== false);

                if (!$isPlaceholder) {
                    $out[] = ['value' => $value, 'label' => ($label !== '' ? $label : $value)];
                }
            }
            return $out;
        };

        // 1) repository
        try {
            $attribute = $this->attributeRepository->get('catalog_product', $attrCode);
            if (method_exists($attribute, 'setStoreId')) {
                $attribute->setStoreId($storeId);
            }
            $norm = $normalize($attribute->getOptions() ?? []);
            if (!empty($norm)) {
                return $norm;
            }
        } catch (\Throwable $e) {
            // fall through
        }

        // 2) source model
        try {
            $attribute = $this->eavConfig->getAttribute('catalog_product', $attrCode);
            if ($attribute && $attribute->getId()) {
                $attribute->setStoreId($storeId);
                if (!$attribute->getEntityTypeId()) {
                    $attribute->setEntityTypeId(
                        (int)$this->eavConfig->getEntityType('catalog_product')->getEntityTypeId()
                    );
                }
                $norm = $normalize($attribute->getSource()->getAllOptions(false) ?? []);
                if (!empty($norm)) {
                    return $norm;
                }
            }
        } catch (\Throwable $e) {
            // fall through
        }

        // 3) swatch-safe fallback
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
