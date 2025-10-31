define(['jquery'], function ($) {
    'use strict';

    return function (config, element) {
        var $form = $(element);
        if (!$form.length) {
            return;
        }

        function stepIsFilled($step) {
            var filled = false;
            $step.find('select,input').each(function () {
                var $f = $(this);
                var v = $f.val();
                if (Array.isArray(v)) {
                    if (v.length) {
                        filled = true;
                        return false;
                    }
                } else if (v && v !== '' && v !== '0') {
                    filled = true;
                    return false;
                }
            });
            return filled;
        }

        function updateVisibility() {
            var $steps = $form.find('.merlin-pf-step');
            var allowNext = true;
            $steps.each(function () {
                var $s = $(this);
                if (allowNext) {
                    $s.show();
                    // if this one is filled, the *next* one can show
                    allowNext = stepIsFilled($s);
                } else {
                    $s.hide();
                }
            });
        }

        updateVisibility();
        $form.on('change input', 'select,input', updateVisibility);
    };
});
