<?php
declare(strict_types=1);

namespace Merlin\ProductFinder\Block;

use Magento\Catalog\Model\Product;
use Magento\Framework\App\ObjectManager;
use Magento\Framework\Pricing\PriceCurrencyInterface;
use Magento\Framework\Stdlib\DateTime\TimezoneInterface;
use Magento\Framework\View\Element\Template;
use Magento\Framework\Registry;

class Results extends Template
{
    protected $_template = 'Merlin_ProductFinder::results.phtml';

    private Registry $registry;
    private TimezoneInterface $tz;

    public function __construct(
        Template\Context $context,
        TimezoneInterface $tz,
        array $data = [],
        $maybeRegistry = null
    ) {
        parent::__construct($context, $data);

        $this->tz = $tz;

        if ($maybeRegistry instanceof Registry) {
            $this->registry = $maybeRegistry;
        } else {
            $this->registry = ObjectManager::getInstance()->get(Registry::class);
        }
    }

    /** @return \Magento\Catalog\Model\ResourceModel\Product\Collection|null */
    public function getCollection()
    {
        return $this->registry->registry('merlin_productfinder_collection');
    }

    public function getFinderParams(): array
    {
        $params = $this->registry->registry('merlin_productfinder_params');
        return is_array($params) ? $params : ['order' => 'name', 'dir' => 'ASC', 'p' => 1, 'limit' => 12];
    }

    /**
     * Ultra defensive price formatting.
     */
    public function formatPrice($amount): string
    {
        $amount = (float)$amount;

        // 1) Try the block's own priceCurrency (normal Magento way)
        $priceCurrency = $this->getPriceCurrency();
        if ($priceCurrency instanceof PriceCurrencyInterface) {
            return $priceCurrency->convertAndFormat($amount);
        }

        // 2) Fallback: pull from OM
        try {
            $om = ObjectManager::getInstance();
            /** @var PriceCurrencyInterface $pc */
            $pc = $om->get(PriceCurrencyInterface::class);
            return $pc->convertAndFormat($amount);
        } catch (\Throwable $e) {
            // 3) Last resort: plain number
            return number_format($amount, 2);
        }
    }

    /**
     * Is special price valid "now" for this product?
     * Honors special_from_date / special_to_date window and ensures it's < base price.
     */
    public function isSpecialActive(Product $p): bool
    {
        $special = (float)$p->getData('special_price');
        if ($special <= 0) {
            return false;
        }

        $now  = $this->tz->date(); // store-aware "now"
        $from = $p->getData('special_from_date') ? $this->tz->date($p->getData('special_from_date')) : null;
        $to   = $p->getData('special_to_date') ? $this->tz->date($p->getData('special_to_date')) : null;

        if ($from && $now < $from) return false;
        if ($to && $now > $to)     return false;

        $price = (float)$p->getData('price');
        return $price > 0 && $special < $price;
    }

    /**
     * Return formatted price data for display in the template.
     * [
     *   'price_raw'    => float,
     *   'price_html'   => string,
     *   'special_raw'  => ?float,
     *   'special_html' => ?string,
     *   'percent_off'  => ?int
     * ]
     */
    public function getDisplayPrices(Product $p): array
    {
        $price   = (float)$p->getData('price');
        $special = $this->isSpecialActive($p) ? (float)$p->getData('special_price') : null;

        $data = [
            'price_raw'    => $price,
            'price_html'   => $this->formatPrice($price),
            'special_raw'  => $special,
            'special_html' => $special !== null ? $this->formatPrice($special) : null,
            'percent_off'  => null,
        ];

        if ($special !== null && $price > 0) {
            $data['percent_off'] = (int)round((1 - ($special / $price)) * 100);
        }

        return $data;
    }
}
