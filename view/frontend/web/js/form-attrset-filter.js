define(['jquery'], function ($) {
    'use strict';

    return function (config, element) {
        var $form = $(element);
        if (!$form.length) return;

        // data-attrsets='{"34":["color","manufacturer","energy_rating"],"35":["appliance_type","color"]}'
        var attrsetsJson = $form.data('attrsets') || {};
        if (typeof attrsetsJson === 'string') {
            try { attrsetsJson = JSON.parse(attrsetsJson); } catch (e) { attrsetsJson = {}; }
        }

        function applyAttrset(selectedSetId) {
            var allowedAttrs = attrsetsJson[selectedSetId] || [];

            // for each step that has data-attr-code, hide if not in set
            $form.find('.merlin-pf-step[data-attr-code]').each(function () {
                var $step = $(this);
                var code = $step.data('attr-code');
                if (!code) return;

                if (allowedAttrs.indexOf(code) !== -1) {
                    $step.show();
                } else {
                    $step.hide();
                    // also clear its values
                    $step.find('select,input').val('').trigger('change');
                }
            });
        }

        // initial: hide attribute-based steps until a set is chosen
        $form.find('.merlin-pf-step[data-attr-code]').hide();

        $form.on('change', 'select[name="attribute_set_id"]', function () {
            var val = $(this).val();
            if (val) {
                applyAttrset(val);
            } else {
                // no set selected: hide all attribute-based steps
                $form.find('.merlin-pf-step[data-attr-code]').hide();
            }
        });
    };
});
