define(['jquery'], function ($) {
  'use strict';

  // Decode HTML entities (e.g. &quot;) from data-* attributes before JSON.parse
  function decodeEntities(s) {
    if (!s || typeof s !== 'string') return s;
    return s
      .replace(/&quot;/g, '"')
      .replace(/&#39;/g, "'")
      .replace(/&lt;/g, '<')
      .replace(/&gt;/g, '>')
      .replace(/&amp;/g, '&');
  }

  function createSelect(name, placeholder) {
    var $sel = $('<select/>', { 'class': 'admin__control-select', 'name': name });
    $sel.append($('<option/>', { value: '', text: placeholder || '-- Select --'}));
    return $sel;
  }

  function stepWrapper(logical, labelText) {
    var $wrap = $('<div/>', { 'class': 'merlin-field merlin-pf-step', 'data-field': logical, 'css': { display: 'none' } });
    $wrap.append($('<label/>').text(labelText));
    return $wrap;
  }

  function buildPriceStep() {
    var $wrap = stepWrapper('price', 'Price Range');
    var $box  = $('<div/>', { 'class': 'merlin-price' });

    var $min = $('<input/>', { type: 'number', name: 'price_min', placeholder: 'Min' });
    var $sep = $('<span/>').text('–');
    var $max = $('<input/>', { type: 'number', name: 'price_max', placeholder: 'Max' });

    var $slider = $('<div/>', {
      id: 'merlin-price-slider',
      'data-mage-init': JSON.stringify({
        "Merlin_ProductFinder/js/price-slider": {
          "inputs": ["input[name=price_min]","input[name=price_max]"]
        }
      })
    });

    $box.append($min, $sep, $max, $slider);
    $wrap.append($box);
    return $wrap;
  }

  function hasValue($wrap) {
    var $sel = $wrap.find('select');
    if ($sel.length) return !!$sel.val();
    var $min = $wrap.find('input[name="price_min"]');
    var $max = $wrap.find('input[name="price_max"]');
    if ($min.length || $max.length) return ($min.val() !== '' || $max.val() !== '');
    return false;
  }

  function labelFor(logical) {
    return logical.replace(/_/g,' ').replace(/\b\w/g, function(m){ return m.toUpperCase(); });
  }

  function getProfile(profiles, setId) {
    if (!setId) return null;
    if (profiles && typeof profiles === 'object' && !Array.isArray(profiles)) {
      if (profiles[setId]) return profiles[setId];
      var k = Object.keys(profiles).find(function (key) { return String(key) === String(setId); });
      if (k) return profiles[k];
    }
    if (Array.isArray(profiles)) {
      var idNum = parseInt(setId, 10);
      var found = profiles.find(function (p) { return String(p.id) === String(setId) || parseInt(p.id,10) === idNum; });
      return found || null;
    }
    return null;
  }

  return function (config, element) {
    var $form = $(element);

    // IMPORTANT: use .attr() and decode entities before JSON.parse
    var profilesRaw      = decodeEntities($form.attr('data-profiles') || '{}');
    var optionsByCodeRaw = decodeEntities($form.attr('data-options-by-code') || '{}');

    var profiles = {};
    var optionsByCode = {};
    try { profiles = JSON.parse(profilesRaw); } catch (e) { profiles = {}; console.warn('[ProductFinder] profiles JSON parse error', e, profilesRaw); }
    try { optionsByCode = JSON.parse(optionsByCodeRaw); } catch (e) { optionsByCode = {}; /* optional */ }

    var $dynamic = $form.find('#merlin-pf-dynamic-fields');
    var $set     = $form.find('[name="attribute_set_id"]');

    function resetFlow() {
      $dynamic.empty();
      $form.find('.merlin-actions[data-field="submit"]').hide();
    }

    function revealNext(sequence, idx) {
      var nextKey = sequence[idx + 1];
      if (!nextKey) { $form.find('.merlin-actions[data-field="submit"]').show(); return; }
      $dynamic.find('.merlin-pf-step[data-field="'+ nextKey +'"]').show();
    }

    function wireStep(sequence, logical, idx) {
      var $wrap = $dynamic.find('.merlin-pf-step[data-field="'+ logical +'"]');
      $wrap.on('change input', 'select, input', function () {
        if (hasValue($wrap)) {
          revealNext(sequence, idx);
        } else {
          sequence.slice(idx + 1).forEach(function (k) {
            $dynamic.find('.merlin-pf-step[data-field="'+ k +'"]').hide();
          });
          $form.find('.merlin-actions[data-field="submit"]').hide();
        }
      });
    }

    $set.on('change', function () {
      resetFlow();

      var setId   = $(this).val();
      var profile = getProfile(profiles, setId);
      if (!profile) { console.warn('[ProductFinder] No profile for set', setId); return; }

      var sequence = Array.isArray(profile.sections) ? profile.sections.slice(0) : [];
      if (!sequence.length) { console.warn('[ProductFinder] Profile has no sections', profile); return; }

      // Build steps (hidden initially)
      sequence.forEach(function (logical) {
        if (logical === 'price') {
          $dynamic.append(buildPriceStep());
          return;
        }

        var mapped = (profile.map && profile.map[logical]) ? profile.map[logical] : null;

        var $wrap   = stepWrapper(logical, labelFor(logical));
        var $select = createSelect(logical, '-- Select --');
        $wrap.append($select);
        $dynamic.append($wrap);

        // Populate from preloaded optionsByCode (no AJAX)
        var opts = (mapped && optionsByCode[mapped]) ? optionsByCode[mapped] : [];
        $select.find('option:not(:first)').remove();
        if (Array.isArray(opts)) {
          opts.forEach(function (o) {
            if (o && o.value !== '' && o.value !== null && o.label !== undefined) {
              $select.append($('<option/>', { value: String(o.value), text: String(o.label) }));
            }
          });
        }
      });

      // Initialise slider only now
      $form.trigger('contentUpdated');

      // Reveal first step only
      $dynamic.find('.merlin-pf-step').hide();
      var firstKey = sequence[0];
      $dynamic.find('.merlin-pf-step[data-field="'+ firstKey +'"]').show();

      // Wire progressive flow
      sequence.forEach(function (logical, idx) { wireStep(sequence, logical, idx); });

      // Keep submit hidden until done
      $form.find('.merlin-actions[data-field="submit"]').hide();
    });

    // initial state
    resetFlow();
  };
});
