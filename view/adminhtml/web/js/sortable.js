require(['jquery', 'jquery/ui'], function($){
    'use strict';
    window.MerlinSortable = {
        init: function(wrapper){
            var $w = $(wrapper);
            var $input = $w.find('input[type="hidden"]');
            var sections = [];
            try { sections = JSON.parse($input.val() || '[]'); } catch(e) {}
            var labels = { category:'Top Category', product_type:'Product Type', color:'Color', price:'Price', extras:'Extras' };
            var $ul = $('<ul class="merlin-sortable admin__control-list"/>').appendTo($w);
            if (!sections.length) { sections = ['category','product_type','color','price','extras']; }
            sections.forEach(function(key){ $('<li class="merlin-sort-item"/>').text(labels[key]||key).attr('data-key', key).appendTo($ul); });
            $ul.sortable({ update:function(){ var arr=[]; $ul.find('li').each(function(){arr.push($(this).data('key'));}); $input.val(JSON.stringify(arr)); } });
        }
    };
});
