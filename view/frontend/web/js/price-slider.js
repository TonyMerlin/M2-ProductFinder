define(['jquery', 'jquery/ui'], function($){
    'use strict';
    return function(cfg, el){
        var $el = $(el);
        var min = parseFloat($el.data('min')||0);
        var max = parseFloat($el.data('max')||1000);
        var step = parseFloat($el.data('step')||10);
        var inputs = (cfg && cfg.inputs)||[];
        var $min = $(inputs[0]);
        var $max = $(inputs[1]);
        $el.slider({range: true, min: min, max: max, step: step, values: [min, max],
            slide: function(event, ui){ $min.val(ui.values[0]); $max.val(ui.values[1]); }
        });
    }
});
