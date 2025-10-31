define(['jquery'], function($){
    'use strict';
    return function(cfg, el){
        var $form = $(el);
        $('#merlin-clear').on('click', function(e){ e.preventDefault(); $form[0].reset(); });
    }
});
