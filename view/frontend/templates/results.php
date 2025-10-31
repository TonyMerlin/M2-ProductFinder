<?php
/** @var \Merlin\ProductFinder\Block\Results $block */
$collection = $block->getCollection();
$params     = $block->getFinderParams();
$order      = $params['order'];
$dir        = $params['dir'];
$limit      = (int)$params['limit'];

$currentUrl = $block->getUrl('*/*/*', ['_current' => true, '_use_rewrite' => true]);
$baseQuery  = $_GET ?? [];

// we'll use the catalog image helper to get a resized image
$om          = \Magento\Framework\App\ObjectManager::getInstance();
/** @var \Magento\Catalog\Helper\Image $imageHelper */
$imageHelper = $om->get(\Magento\Catalog\Helper\Image::class);

// choose a role that exists on most themes; we can change to product_page_image_small later
$imageRole   = 'category_page_grid';

// thumbnail size – adjust to taste
$thumbW = 300;
$thumbH = 300;
?>
<div class="merlin-results">
    <div class="merlin-results__toolbar">
        <form method="get" action="<?= $block->escapeUrl($currentUrl) ?>" class="merlin-toolbar-form">
            <?php foreach ($baseQuery as $k => $v): if (in_array($k, ['order','dir','limit','p'])) continue;
                if (is_array($v)) {
                    foreach ($v as $vv): ?>
                        <input type="hidden" name="<?= $block->escapeHtmlAttr($k) ?>[]" value="<?= $block->escapeHtmlAttr($vv) ?>">
                    <?php endforeach;
                } else { ?>
                    <input type="hidden" name="<?= $block->escapeHtmlAttr($k) ?>" value="<?= $block->escapeHtmlAttr($v) ?>">
                <?php } ?>
            <?php endforeach; ?>

            <label><?= __('Sort By') ?></label>
            <select name="order" onchange="this.form.submit()">
                <option value="name" <?= $order==='name' ? 'selected' : '' ?>><?= __('Name') ?></option>
                <option value="price" <?= $order==='price' ? 'selected' : '' ?>><?= __('Price') ?></option>
                <option value="created_at" <?= $order==='created_at' ? 'selected' : '' ?>><?= __('Newest') ?></option>
            </select>
            <select name="dir" onchange="this.form.submit()">
                <option value="ASC" <?= $dir==='ASC' ? 'selected' : '' ?>><?= __('Asc') ?></option>
                <option value="DESC" <?= $dir==='DESC' ? 'selected' : '' ?>><?= __('Desc') ?></option>
            </select>

            <label><?= __('Show') ?></label>
            <select name="limit" onchange="this.form.submit()">
                <?php foreach ([12,24,48] as $l): ?>
                    <option value="<?= $l ?>" <?= $limit===$l ? 'selected' : '' ?>><?= $l ?></option>
                <?php endforeach; ?>
            </select>
        </form>
    </div>

    <?php if (!$collection || !$collection->getSize()): ?>
        <p><?= __('No products matched your selection.') ?></p>
    <?php else: ?>
        <?php
        // top pager
        $pagerTop = $block->getLayout()->createBlock(\Magento\Theme\Block\Html\Pager::class);
        if ($pagerTop) {
            $pagerTop->setAvailableLimit([12=>12,24=>24,48=>48])
                ->setShowPerPage(false)
                ->setLimit($limit)
                ->setCollection($collection);
            echo $pagerTop->toHtml();
        }
        ?>
        <div class="merlin-grid-cards">
            <?php foreach ($collection as $product): ?>
                <?php
                // build a resized image url; if product has no image, we'll fallback below
                $resizedUrl = '';
                try {
                    $resizedUrl = $imageHelper
                        ->init($product, $imageRole)
                        ->resize($thumbW, $thumbH)
                        ->getUrl();
                } catch (\Exception $e) {
                    $resizedUrl = '';
                }
                ?>
                <div class="merlin-card">
                    <a href="<?= $product->getProductUrl() ?>" class="merlin-card__image">
                        <?php if ($resizedUrl): ?>
                            <img src="<?= $block->escapeUrl($resizedUrl) ?>"
                                 alt="<?= $block->escapeHtmlAttr($product->getName()) ?>"
                                 loading="lazy"
                                 width="<?= $thumbW ?>" height="<?= $thumbH ?>" />
                        <?php else: ?>
                            <span class="merlin-thumb"><?= $block->escapeHtml($product->getName()) ?></span>
                        <?php endif; ?>
                    </a>
                    <div class="merlin-card__body">
                        <a class="merlin-card__title" href="<?= $product->getProductUrl() ?>">
                            <?= $block->escapeHtml($product->getName()) ?>
                        </a>
                        <div class="merlin-card__price">
                            <?php
                            $price = $product->getFinalPrice() ?: $product->getPrice();
                            echo $block->formatPrice($price);
                            ?>
                        </div>
                        <a class="action primary merlin-card__cta" href="<?= $product->getProductUrl() ?>">
                            <?= __('View') ?>
                        </a>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
        <?php
        // bottom pager
        $pagerBottom = $block->getLayout()->createBlock(\Magento\Theme\Block\Html\Pager::class);
        if ($pagerBottom) {
            $pagerBottom->setAvailableLimit([12=>12,24=>24,48=>48])
                ->setShowPerPage(false)
                ->setLimit($limit)
                ->setCollection($collection);
            echo $pagerBottom->toHtml();
        }
        ?>
    <?php endif; ?>
</div>
