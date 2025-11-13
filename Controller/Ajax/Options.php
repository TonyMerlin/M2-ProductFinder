<?php
declare(strict_types=1);

namespace Merlin\ProductFinder\Controller\Ajax;

use Magento\Catalog\Model\ResourceModel\Product\CollectionFactory as ProductCollectionFactory;
use Magento\Eav\Api\AttributeRepositoryInterface;
use Magento\Eav\Model\Config as EavConfig;
use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Store\Model\StoreManagerInterface;

class Options extends Action
{
    private JsonFactory $resultJsonFactory;
    private ProductCollectionFactory $productCollectionFactory;
    private StoreManagerInterface $storeManager;
    private AttributeRepositoryInterface $attributeRepository;
    private EavConfig $eavConfig;

    public function __construct(
        Context $context,
        JsonFactory $resultJsonFactory,
        ProductCollectionFactory $productCollectionFactory,
        StoreManagerInterface $storeManager,
        AttributeRepositoryInterface $attributeRepository,
        EavConfig $eavConfig
    ) {
        parent::__construct($context);
        $this->resultJsonFactory        = $resultJsonFactory;
        $this->productCollectionFactory = $productCollectionFactory;
        $this->storeManager             = $storeManager;
        $this->attributeRepository      = $attributeRepository;
        $this->eavConfig                = $eavConfig;
    }

    public function execute()
    {
        $r   = $this->getRequest();
        $res = $this->resultJsonFactory->create();

        try {
            $setId   = (int)($r->getParam('set_id') ?? 0);
            $next    = trim((string)$r->getParam('next_code'));

            // Read filters (attribute selections) + price window
            [$filters, $priceMin, $priceMax] = $this->readFilters($r);

            if ($setId <= 0 || $next === '') {
                return $res->setData(['ok' => false, 'options' => []]);
            }

            $options = $this->loadFilteredOptions($setId, $next, $filters, $priceMin, $priceMax);

            return $res->setData([
                'ok'      => true,
                'options' => $options, // [{value,label}]
            ]);
        } catch (\Throwable $e) {
            return $res->setData(['ok' => false, 'options' => []]);
        }
    }

    /**
     * Read filters from request:
     *  - filters[code]=value for attributes already chosen
     *  - filters[price_min], filters[price_max] (optional)
     *
     * @return array{0: array, 1: ?float, 2: ?float}
     */
    private function readFilters(RequestInterface $r): array
    {
        $raw = $r->getParam('filters');
        $out = [];
        $min = null;
        $max = null;

        if (is_array($raw)) {
            foreach ($raw as $k => $v) {
                $k = trim((string)$k);
                if ($k === '') continue;

                if ($k === 'price_min') {
                    // numeric only
                    $min = is_array($v) ? (float)reset($v) : (float)$v;
                    continue;
                }
                if ($k === 'price_max') {
                    $max = is_array($v) ? (float)reset($v) : (float)$v;
                    continue;
                }

                // attribute filters: take first scalar value
                if (is_array($v)) { $v = reset($v); }
                $out[$k] = trim((string)$v);
            }
        }

        return [$out, $min, $max];
    }

    /**
     * Build a product collection scoped to set + stock + visibility + status + filters (+ price),
     * then return the distinct option id list for $nextCode, mapped to labels.
     *
     * @param int         $setId
     * @param string      $nextCode
     * @param array       $filters     // attribute => value
     * @param float|null  $priceMin
     * @param float|null  $priceMax
     * @return array<int,array{value:string,label:string}>
     */
    private function loadFilteredOptions(int $setId, string $nextCode, array $filters, ?float $priceMin, ?float $priceMax): array
    {
        $store     = $this->storeManager->getStore();
        $websiteId = (int)$store->getWebsiteId();

        $col = $this->productCollectionFactory->create();
        $col->addAttributeToSelect(array_merge([$nextCode, 'price'], array_keys($filters)));
        $col->addFieldToFilter('attribute_set_id', $setId);
        $col->addAttributeToFilter('type_id', 'simple');
        $col->addAttributeToFilter('status', 1);
        $col->addAttributeToFilter('visibility', ['in' => [2,3,4]]);

        // STOCK (legacy view; works with MSI as well)
        $css = $col->getTable('cataloginventory_stock_status');
        $col->getSelect()->joinInner(
            ['css' => $css],
            'css.product_id = e.entity_id AND css.stock_status = 1 AND css.website_id IN (0, ' . $websiteId . ')',
            []
        );

        // PRICE RANGE (prefer price index final_price; fallback to attribute price)
        if ($priceMin !== null || $priceMax !== null) {
            $pMin = ($priceMin !== null) ? max(0, (float)$priceMin) : null;
            $pMax = ($priceMax !== null) ? max(0, (float)$priceMax) : null;

            // Try index join for final price (customer_group_id = 0 as a safe default)
            $pip = $col->getTable('catalog_product_index_price');
            $alias = 'pip_idx';
            try {
                if (!in_array($alias, $col->getTableAliases(), true)) {
                    $col->getSelect()->joinLeft(
                        [$alias => $pip],
                        $alias . '.entity_id = e.entity_id AND ' .
                        $alias . '.website_id = ' . (int)$websiteId . ' AND ' .
                        $alias . '.customer_group_id = 0',
                        [] // no columns
                    );
                }
                // WHERE final_price BETWEEN bounds (if provided)
                if ($pMin !== null) {
                    $col->getSelect()->where($alias . '.final_price >= ?', $pMin);
                }
                if ($pMax !== null) {
                    $col->getSelect()->where($alias . '.final_price <= ?', $pMax);
                }
            } catch (\Throwable $e) {
                // Fallback: filter by base price attribute
                if ($pMin !== null) {
                    $col->addAttributeToFilter('price', ['gteq' => $pMin]);
                }
                if ($pMax !== null) {
                    $col->addAttributeToFilter('price', ['lteq' => $pMax]);
                }
            }
        }

        // ATTRIBUTE FILTERS (support select & multiselect: eq OR finset)
        foreach ($filters as $code => $value) {
            if ($value === '') continue;
            $col->addAttributeToFilter([
                ['attribute' => $code, 'eq' => $value],
                ['attribute' => $code, 'finset' => $value],
            ]);
        }

        // Collect distinct option ids for $nextCode
        $ids = [];
        foreach ($col as $p) {
            $val = $p->getData($nextCode);
            if ($val === null || $val === '' || $val === false) continue;

            if (is_string($val) && strpos($val, ',') !== false) {
                foreach (explode(',', $val) as $v) {
                    $v = trim($v);
                    if ($v !== '' && $v !== '0') $ids[$v] = true;
                }
            } elseif (is_array($val)) {
                foreach ($val as $v) {
                    $v = (string)$v;
                    if ($v !== '' && $v !== '0') $ids[$v] = true;
                }
            } else {
                $v = (string)$val;
                if ($v !== '' && $v !== '0') $ids[$v] = true;
            }
        }

        if (!$ids) return [];

        // Map ids -> labels (store scoped)
        $labelMap = [];
        foreach ($this->getAttributeOptions($nextCode) as $opt) {
            $labelMap[(string)$opt['value']] = (string)$opt['label'];
        }

        $out = [];
        foreach (array_keys($ids) as $id) {
            $idStr = (string)$id;
            $out[] = ['value' => $idStr, 'label' => $labelMap[$idStr] ?? $idStr];
        }

        // Sort by label for nicer UX
        usort($out, static function ($a, $b) {
            return strcasecmp($a['label'], $b['label']);
        });

        return $out;
    }

    /** Store-scoped attribute options (mirrors your block logic, simplified) */
    private function getAttributeOptions(string $code): array
    {
        $storeId = (int)$this->storeManager->getStore()->getId();

        // try repository
        try {
            $attr = $this->attributeRepository->get('catalog_product', $code);
            if (method_exists($attr, 'setStoreId')) $attr->setStoreId($storeId);
            $rows = $attr->getOptions() ?? [];
            $out = [];
            foreach ($rows as $opt) {
                $v = (string)$opt->getValue();
                $l = (string)$opt->getLabel();
                if ($v === '' || ($v === '0' && trim($l) === '')) continue;
                $out[] = ['value' => $v, 'label' => ($l !== '' ? $l : $v)];
            }
            if ($out) return $out;
        } catch (\Throwable $e) { /* fall through */ }

        // fallback: source model
        $attr = $this->eavConfig->getAttribute('catalog_product', $code);
        if ($attr && $attr->getId()) {
            $attr->setStoreId($storeId);
            $rows = $attr->getSource()->getAllOptions(false) ?? [];
            $out = [];
            foreach ($rows as $r) {
                $v = isset($r['value']) ? (string)$r['value'] : '';
                $l = isset($r['label']) ? (string)$r['label'] : '';
                if ($v === '' || ($v === '0' && trim($l) === '')) continue;
                $out[] = ['value' => $v, 'label' => ($l !== '' ? $l : $v)];
            }
            return $out;
        }

        return [];
    }
}
