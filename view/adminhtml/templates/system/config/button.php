<?php /** @var $block \Magento\Backend\Block\Template */ ?>
<button id="<?= $block->escapeHtmlAttr($block->getData('button_id')) ?>"
        class="action-default"
        type="button"
        onclick="setLocation('<?= $block->escapeUrl($block->getData('action_url')) ?>?form_key=' + FORM_KEY)">
    <span><?= $block->escapeHtml($block->getData('button_lbl')) ?></span>
</button>
