<?php
declare(strict_types=1);

namespace Merlin\ProductFinder\Block\Adminhtml\System\Config\Field;

use Magento\Config\Block\System\Config\Form\Field;
use Magento\Framework\Data\Form\Element\AbstractElement;

/**
 * Renders a JSON key=>attribute map editor with add/remove rows.
 * Uses nowdoc for the inline JS to avoid PHP string parsing issues.
 */
class AttributeMap extends Field
{
    protected function _getElementHtml(AbstractElement $element)
    {
        $value = $element->getData('value') ?: '{}';
        $html = '<div class="merlin-attr-map" data-target="' . $element->getHtmlId() . '">'
            . '<table class="admin__control-table"><thead><tr><th>Frontend Key</th><th>Attribute Code</th><th></th></tr></thead><tbody></tbody></table>'
            . '<button type="button" class="action-default" onclick="MerlinAttrMap.addRow(this)">Add</button>'
            . '<input type="hidden" name="' . $element->getName() . '" id="' . $element->getHtmlId() . '" value="' . htmlspecialchars($value, ENT_QUOTES) . '" />'
            . '</div>';

        $script = <<<'HTML'
<script>
require(['jquery'], function($){
    window.MerlinAttrMap = {
        addRow: function(btn){
            var wrap = $(btn).closest('.merlin-attr-map');
            var tbody = wrap.find('tbody');
            tbody.append('<tr>'
                + '<td><input class="admin__control-text key"></td>'
                + '<td><input class="admin__control-text val"></td>'
                + '<td><button type="button" class="action-delete" onclick="MerlinAttrMap.removeRow(this)">Remove</button></td>'
                + '</tr>');
            MerlinAttrMap.sync(btn);
        },
        removeRow: function(btn){
            $(btn).closest('tr').remove();
            MerlinAttrMap.sync(btn);
        },
        load: function(){
            $('.merlin-attr-map').each(function(){
                var el = $(this);
                var target = $('#' + el.data('target'));
                var data = {};
                try { data = JSON.parse(target.val() || '{}'); } catch(e) {}
                var tbody = el.find('tbody');
                $.each(data, function(k, v){
                    tbody.append('<tr>'
                        + '<td><input class="admin__control-text key" value="' + k + '"></td>'
                        + '<td><input class="admin__control-text val" value="' + v + '"></td>'
                        + '<td><button type="button" class="action-delete" onclick="MerlinAttrMap.removeRow(this)">Remove</button></td>'
                        + '</tr>');
                });
            });
        },
        sync: function(el){
            var wrap = $(el).closest('.merlin-attr-map');
            var target = $('#' + wrap.data('target'));
            var data = {};
            wrap.find('tbody tr').each(function(){
                var k = $(this).find('.key').val();
                var v = $(this).find('.val').val();
                if (k && v) { data[k] = v; }
            });
            target.val(JSON.stringify(data));
        }
    };
    $(function(){
        MerlinAttrMap.load();
        $(document).on('change', '.merlin-attr-map input', function(){ MerlinAttrMap.sync(this); });
    });
});
</script>
HTML;
        $html .= $script;
        return $html;
    }
}
