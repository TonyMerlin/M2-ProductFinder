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
     * Return the product's *raw* special price (ignores from/to dates entirely).
     * Returns null if not set or non-positive.
     */
    public function getRawSpecialPrice(Product $product): ?float
    {
        $sp = $product->getData('special_price');
        if ($sp === null || $sp === '') {
            return null;
        }
        $sp = (float)$sp;
        return $sp > 0 ? $sp : null;
    }

    /**
     * Treat product as "on sale" if special_price is set and lower than base price.
     * (Ignores special_from_date / special_to_date by design.)
     */
    public function hasSpecialPrice(Product $product): bool
    {
        $regular = (float)$product->getPrice();
        $special = $this->getRawSpecialPrice($product);
        return ($special !== null) && $regular > 0 && $special < $regular;
    }

    /**
     * Discount percentage (rounded down), ignoring date windows.
     */
    public function getDiscountPercent(Product $product): int
    {
        $regular = (float)$product->getPrice();
        $special = $this->getRawSpecialPrice($product);
        if ($regular <= 0 || $special === null || $special >= $regular) {
            return 0;
        }
        return (int)floor((1 - ($special / $regular)) * 100);
    }

    /**
     * Backwards-compat helper: previous code used "isSpecialActive" (date-aware).
     * We now ignore dates but keep the method name to avoid breaking templates.
     */
    public function isSpecialActive(Product $p): bool
    {
        return $this->hasSpecialPrice($p);
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
     * (Ignores special date windows.)
     */
    public function getDisplayPrices(Product $p): array
    {
        $price   = (float)$p->getData('price');
        $special = $this->hasSpecialPrice($p) ? (float)$this->getRawSpecialPrice($p) : null;

        $data = [
            'price_raw'    => $price,
            'price_html'   => $this->formatPrice($price),
            'special_raw'  => $special,
            'special_html' => $special !== null ? $this->formatPrice($special) : null,
            'percent_off'  => null,
        ];

        if ($special !== null && $price > 0) {
            $data['percent_off'] = (int)floor((1 - ($special / $price)) * 100);
        }

        return $data;
    }
}
