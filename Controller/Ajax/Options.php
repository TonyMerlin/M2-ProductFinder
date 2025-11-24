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
            $filters = $this->readFilters($r);

            if ($setId <= 0 || $next === '') {
                return $res->setData(['ok' => false, 'options' => []]);
            }

            $options = $this->loadFilteredOptions($setId, $next, $filters);

            return $res->setData([
                'ok'      => true,
                'options' => $options, // [{value,label}]
            ]);
        } catch (\Throwable $e) {
            return $res->setData(['ok' => false, 'options' => []]);
        }
    }

    /** Read filters[] = { code: value } from request (GET is fine) */
    private function readFilters(RequestInterface $r): array
    {
        $raw = $r->getParam('filters');
        if (is_array($raw)) {
            $out = [];
            foreach ($raw as $k => $v) {
                $k = trim((string)$k);
                if ($k === '') {
                    continue;
                }
                // take first scalar value
                if (is_array($v)) {
                    $v = reset($v);
                }
                $out[$k] = trim((string)$v);
            }
            return $out;
        }
        return [];
    }

    /**
     * Build a product collection scoped to set + stock + visibility + status + current filters,
     * then return the distinct option id list for $nextCode, mapped to labels.
     *
     * @return array<int,array{value:string,label:string}>
     */
    private function loadFilteredOptions(int $setId, string $nextCode, array $filters): array
    {
        $store     = $this->storeManager->getStore();
        $websiteId = (int)$store->getWebsiteId();

        $col = $this->productCollectionFactory->create();
        $col->addAttributeToSelect(array_merge([$nextCode], array_keys($filters)));
        $col->addAttributeToFilter('type_id', 'simple');
        $col->addAttributeToFilter('status', 1);
        // Allow not-visible simple children so configurable parents can contribute options
        $col->addAttributeToFilter('visibility', ['in' => [1,2,3,4]]);

        // When profiles are configured on configurable parents, include their child simples
        // even if those children use a different attribute set. This makes progressive filtering
        // keep returning options after the first selection.
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
            ->where('(e.attribute_set_id = ? OR parent.attribute_set_id = ?)', [$setId, $setId]);

        // legacy stock status (works with MSI too)
        $css = $col->getTable('cataloginventory_stock_status');
        $col->getSelect()->joinInner(
            ['css' => $css],
            'css.product_id = e.entity_id AND css.stock_status = 1 AND css.website_id IN (0, ' . $websiteId . ')',
            []
        );

        // Apply already chosen filters. Use (eq OR finset) to support select & multiselect.
        foreach ($filters as $code => $value) {
            if ($value === '') {
                continue;
            }
            $col->addAttributeToFilter([
                ['attribute' => $code, 'eq' => $value],
                ['attribute' => $code, 'finset' => $value],
            ]);
        }

        // Collect distinct option ids for $nextCode
        $ids = [];
        foreach ($col as $p) {
            $val = $p->getData($nextCode);
            if ($val === null || $val === '' || $val === false) {
                continue;
            }

            if (is_string($val) && strpos($val, ',') !== false) {
                foreach (explode(',', $val) as $v) {
                    $v = trim($v);
                    if ($v !== '' && $v !== '0') {
                        $ids[$v] = true;
                    }
                }
            } elseif (is_array($val)) {
                foreach ($val as $v) {
                    $v = (string)$v;
                    if ($v !== '' && $v !== '0') {
                        $ids[$v] = true;
                    }
                }
            } else {
                $v = (string)$val;
                if ($v !== '' && $v !== '0') {
                    $ids[$v] = true;
                }
            }
        }

        if (!$ids) {
            return [];
        }

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

        // nice UX: sort by label
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
            if (method_exists($attr, 'setStoreId')) {
                $attr->setStoreId($storeId);
            }
            $rows = $attr->getOptions() ?? [];
            $out = [];
            foreach ($rows as $opt) {
                $v = (string)$opt->getValue();
                $l = (string)$opt->getLabel();
                if ($v === '' || $v === '0' && trim($l) === '') {
                    continue;
                }
                $out[] = ['value' => $v, 'label' => ($l !== '' ? $l : $v)];
            }
            if ($out) {
                return $out;
            }
        } catch (\Throwable $e) {
            /* fall through */
        }

        // fallback: source model
        $attr = $this->eavConfig->getAttribute('catalog_product', $code);
        if ($attr && $attr->getId()) {
            $attr->setStoreId($storeId);
            $rows = $attr->getSource()->getAllOptions(false) ?? [];
            $out = [];
            foreach ($rows as $r) {
                $v = isset($r['value']) ? (string)$r['value'] : '';
                $l = isset($r['label']) ? (string)$r['label'] : '';
                if ($v === '' || ($v === '0' && trim($l) === '')) {
                    continue;
                }
                $out[] = ['value' => $v, 'label' => ($l !== '' ? $l : $v)];
            }
            return $out;
        }

        return [];
    }
}
