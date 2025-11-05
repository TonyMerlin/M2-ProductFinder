define(['jquery'], function ($) {
    'use strict';

    return function (config, element) {
        var $form = $(element);
        if (!$form.length) return;

        // attribute sets map
        var rawSets = $form.data('attrsets') || {};
        if (typeof rawSets === 'string') {
            try { rawSets = JSON.parse(rawSets); } catch (e) { rawSets = {}; }
        }

        // aliases from template (preferred)
        var rawAliases = $form.data('attr-aliases') || {};
        if (typeof rawAliases === 'string') {
            try { rawAliases = JSON.parse(rawAliases); } catch (e) { rawAliases = {}; }
        }

        // normalise set attrs
        var attrsets = {};
        Object.keys(rawSets).forEach(function (setId) {
            var arr = rawSets[setId] || [];
            attrsets[setId] = arr.map(function (code) {
                return (code + '').toLowerCase();
            });
        });

        // normalise aliases
        var aliases = {};
        Object.keys(rawAliases).forEach(function (frontendCode) {
            var val = rawAliases[frontendCode];
            if (Array.isArray(val)) {
                aliases[frontendCode.toLowerCase()] = val.map(function (v) { return (v + '').toLowerCase(); });
            } else {
                aliases[frontendCode.toLowerCase()] = [(val + '').toLowerCase()];
            }
        });

        function codeMatches(needle, allowedArr) {
            var n = (needle + '').toLowerCase();

            // direct match
            if (allowedArr.indexOf(n) !== -1) {
                return true;
            }

            // alias match
            if (aliases[n]) {
                var aliasList = aliases[n];
                for (var i = 0; i < aliasList.length; i++) {
                    if (allowedArr.indexOf(aliasList[i]) !== -1) {
                        return true;
                    }
                }
            }

            return false;
        }

        function applyAttrset(selectedSetId) {
            var allowed = attrsets[selectedSetId] || [];

            // show/hide attribute-based steps
            $form.find('.merlin-pf-step[data-attr-code]').each(function () {
                var $step = $(this);
                var stepCode = ($step.data('attr-code') + '').toLowerCase();

                if (codeMatches(stepCode, allowed)) {
                    $step.show();
                } else {
                    $step.hide();
                    $step.find('select,input').val('').trigger('change');
                }
            });

            // we do NOT hide the final submit
            $form.find('.merlin-actions').show();
        }

        // initial: hide attribute steps
        $form.find('.merlin-pf-step[data-attr-code]').hide();

        // on change of product group
        $form.on('change', 'select[name="attribute_set_id"]', function () {
            var setId = $(this).val();
            if (setId) {
                applyAttrset(setId);
            } else {
                $form.find('.merlin-pf-step[data-attr-code]').hide();
            }
        });
    };
});
