define(['jquery'], function ($) {
    'use strict';

    function parseJsonFromScriptTag(id) {
        var el = document.getElementById(id);
        if (!el) return null;
        try {
            return JSON.parse(el.textContent || el.innerText || '{}') || {};
        } catch (e) {
            return null;
        }
    }

    // Build a select field safely (no string concat for options)
    function buildSelect(name, labelText, opts) {
        var $wrap = $('<div/>', { 'class': 'merlin-field merlin-pf-step' });
        var id = 'mpf-' + name + '-' + (Math.random().toString(36).slice(2));

        var $label = $('<label/>', { for: id }).text(labelText || name);
        var $sel   = $('<select/>', {
            'class': 'admin__control-select',
            id: id,
            name: name
        });

        // Placeholder
        $sel.append($('<option/>', { value: '' }).text('-- ' + (labelText || 'Select') + ' --'));

        // Real options
        (opts || []).forEach(function (o) {
            if (!o || typeof o.value === 'undefined') return;
            var val = String(o.value);
            var lab = (o.label != null) ? String(o.label) : val;
            $sel.append($('<option/>').val(val).text(lab));
        });

        $wrap.append($label, $sel);
        return $wrap;
    }

    // Build media row (attribute-set image), hidden by default
    function buildImageRow(imgUrl, altText) {
        var $row = $('<div/>', { 'class': 'merlin-field merlin-pf-media', style: 'display:none' });
        var $img = $('<img/>', {
            src: String(imgUrl),
            alt: String(altText || ''),
            loading: 'lazy',
            style: 'max-width:25%;height:auto;display:block;border-radius:10px;'
        });
        $row.append($img);
        return $row;
    }

    // Simple currency
    function formatMoney(n) {
        n = Number(n || 0);
        return '£' + n.toFixed(0).replace(/\B(?=(\d{3})+(?!\d))/g, ',');
    }

    // Price slider (dual range)
    function buildPriceRange(labelText, min, max, step) {
        var $wrap = $('<div/>', { 'class': 'merlin-field merlin-pf-step mpf-price' });
        var idMin = 'mpf-price-min-' + (Math.random().toString(36).slice(2));
        var idMax = 'mpf-price-max-' + (Math.random().toString(36).slice(2));

        var $header = $('<div class="mpf-price__header"></div>');
        var $label  = $('<label/>').text(labelText || 'Price');
        var $vals   = $('<div class="mpf-price__values"/>')
                        .append('<span data-minv>'+ formatMoney(min) +'</span>')
                        .append(' – ')
                        .append('<span data-maxv>'+ formatMoney(max) +'</span>');
        $header.append($label, $vals);

        var $track   = $('<div class="mpf-price__track"><div class="mpf-price__progress"></div></div>');
        var $ranges  = $('<div class="mpf-price__ranges"></div>');
        var $rMin    = $('<input type="range" class="mpf-price__range">').attr({ id: idMin, min: min, max: max, step: step, value: min });
        var $rMax    = $('<input type="range" class="mpf-price__range">').attr({ id: idMax, min: min, max: max, step: step, value: max });
        var $hMin    = $('<input type="hidden" name="price_min">').val(min);
        var $hMax    = $('<input type="hidden" name="price_max">').val(max);

        $ranges.append($rMin, $rMax);
        $wrap.append($header, $track, $ranges, $hMin, $hMax);

        function updateProgress() {
            var v1 = Math.min(Number($rMin.val()), Number($rMax.val()));
            var v2 = Math.max(Number($rMin.val()), Number($rMax.val()));
            var pct1 = ((v1 - min) / (max - min)) * 100;
            var pct2 = ((v2 - min) / (max - min)) * 100;
            $track.find('.mpf-price__progress').css({ left: pct1 + '%', right: (100 - pct2) + '%' });

            $vals.find('[data-minv]').text(formatMoney(v1));
            $vals.find('[data-maxv]').text(formatMoney(v2));
            $hMin.val(Math.floor(v1));
            $hMax.val(Math.ceil(v2));
        }

        $rMin.on('input change', updateProgress);
        $rMax.on('input change', updateProgress);
        updateProgress();

        // API to read selected window
        $wrap.data('mpf-get', function() {
            return { price_min: Number($hMin.val()), price_max: Number($hMax.val()) };
        });

        return $wrap;
    }

    return function (config, element) {
        var $form = $(element);
        if (!$form.length) return;

        var $attrSet   = $form.find('select[name="attribute_set_id"]');
        var $dynamic   = $form.find('#merlin-pf-dynamic-fields');
        var $submitRow = $form.find('.merlin-pf-step[data-field="submit"]');
        var ajaxUrl    = String($form.data('ajax-url') || '').trim(); // product-finder/ajax/options

        // Price slider config from data-* on the form
        var priceMin  = parseFloat($form.data('price-min'))  || 0;
        var priceMax  = parseFloat($form.data('price-max'))  || 10000;
        var priceStep = parseFloat($form.data('price-step')) || 10;

        // Read JSON blobs
        var profiles        = parseJsonFromScriptTag('mpf-profiles-json') || {};
        var optionsBySetId  = parseJsonFromScriptTag('mpf-options-by-set') || {}; // preferred (in-stock per set)
        var optionsByCode   = parseJsonFromScriptTag('mpf-options-json') || {};   // fallback (global)

        // Normalise keys to strings
        (function normalise() {
            var normProfiles = {};
            Object.keys(profiles || {}).forEach(function (k) { normProfiles[String(k)] = profiles[k] || {}; });
            profiles = normProfiles;

            var normPerSet = {};
            Object.keys(optionsBySetId || {}).forEach(function (k) { normPerSet[String(k)] = optionsBySetId[k] || {}; });
            optionsBySetId = normPerSet;
        })();

        // Diagnostics
        (function logOnce() {
            if (!window.__mpfLogged) {
                window.__mpfLogged = true;
                // eslint-disable-next-line no-console
                console.log('[Merlin ProductFinder] Diagnostics:', {
                    profilesKeys: Object.keys(profiles || {}),
                    perSetIds: Object.keys(optionsBySetId || {}),
                    globalCodes: Object.keys(optionsByCode || {}),
                    ajaxUrl: ajaxUrl
                });
            }
            window.__mpfProfiles       = profiles;
            window.__mpfOptionsBySet   = optionsBySetId;
            window.__mpfOptionsByCode  = optionsByCode;
        })();

        function clearDynamic() { $dynamic.empty(); }

        // Prefer per-set in-stock options; fall back to global options when missing/empty
        function getSeedOptionsFor(setId, code) {
            setId = String(setId || '').trim();
            code  = String(code  || '').trim();
            if (!code) return [];

            var perSet = (setId && optionsBySetId[setId]) ? optionsBySetId[setId][code] : null;
            if (Array.isArray(perSet) && perSet.length) {
                return perSet;
            }
            var global = optionsByCode[code];
            return Array.isArray(global) ? global : [];
        }

        function humanizeLabel(key) {
            return String(key || '')
                .replace(/_/g, ' ')
                .replace(/\b\w/g, function (m) { return m.toUpperCase(); });
        }

        function buildChainForProfile(setId, profile) {
            clearDynamic();
            $submitRow.hide();

            var sections = Array.isArray(profile.sections) ? profile.sections.slice() : [];
            var map      = (profile.map && typeof profile.map === 'object') ? profile.map : {};
            var imageUrl = (profile.image || '').trim();

            if (!sections.length) {
                $dynamic.append(
                    $('<div class="merlin-field" style="margin:.5rem 0;color:#c00;"></div>')
                        .text('Profile has no sections configured for this attribute set.')
                );
                return;
            }

            var created = [];
            sections.forEach(function (logical, idx) {
                var mappedCode = map[logical] || logical;
                var labelTxt   = humanizeLabel(logical);

                var isPrice = ['price','price_range','price-range','amount','budget'].indexOf(String(logical).toLowerCase()) >= 0
                           || ['price','price_range','price-range'].indexOf(String(mappedCode).toLowerCase()) >= 0;

                var $row, seed;

                if (isPrice) {
                    $row = buildPriceRange(labelTxt, priceMin, priceMax, priceStep);
                } else {
                    seed = getSeedOptionsFor(setId, mappedCode);
                    $row = buildSelect(logical, labelTxt, seed);

                    if ((!seed || !seed.length) && idx !== 0) {
                        var $sel = $row.find('select');
                        $sel.empty()
                            .append($('<option/>').val('').text(labelTxt + ' — No options'))
                            .prop('disabled', true);
                    }
                }

                // Step badge
                $row.find('label').attr('data-step', (idx + 1));

                if (idx === 0) { $row.show(); } else { $row.hide(); }

                $dynamic.append($row);
                created.push({ name: logical, code: mappedCode, row: $row, isPrice: isPrice });
            });

            // Insert image row AFTER the first field, hidden initially; show when first field selected
            var $mediaRow = null;
            if (imageUrl && created.length > 0) {
                $mediaRow = buildImageRow(imageUrl, profile.label || '');
                $mediaRow.insertAfter(created[0].row);
            }

            // Progressive reveal with AJAX intersection filtering (+ price window support)
            created.forEach(function (item, i) {
                var $current = item.isPrice ? item.row.find('input[type="range"]') : item.row.find('select');

                // For price, update on both sliders; for select, on change
                var eventName = item.isPrice ? 'input change' : 'change';
                $current.on(eventName, function () {
                    // Hide & reset downstream selects
                    for (var j = i + 1; j < created.length; j++) {
                        var $selDown = created[j].row.find('select');
                        created[j].row.hide();
                        if ($selDown.length) {
                            $selDown.val('');
                            $selDown.find('option:not(:first)').remove();
                            $selDown.prop('disabled', false);
                        }
                    }
                    $submitRow.hide();

                    // Special handling for the media row tied to first field selection
                    if ($mediaRow && i === 0) {
                        var hasValue = item.isPrice
                          ? true // price step always considered "chosen"
                          : !!item.row.find('select').val();
                        $mediaRow.toggle(!!hasValue);
                    }

                    if (!item.isPrice && !item.row.find('select').val()) return;

                    // Last field? Then show submit
                    if (i === created.length - 1) {
                        $submitRow.show();
                        return;
                    }

                    // Build filters up to i (include price window if present)
                    var filters = {};
                    for (var k = 0; k <= i; k++) {
                        var it = created[k];
                        if (it.isPrice) {
                            var priceVal = it.row.data('mpf-get') ? it.row.data('mpf-get')() : null;
                            if (priceVal) {
                                filters.price_min = priceVal.price_min;
                                filters.price_max = priceVal.price_max;
                            }
                        } else {
                            var v = it.row.find('select').val();
                            if (v) filters[it.code] = v;
                        }
                    }

                    var next = created[i + 1];

                    // If no AJAX URL (shouldn't happen), just show next row
                    if (!ajaxUrl) {
                        next.row.show();
                        return;
                    }

                    // For a price next step, there's nothing to fetch via AJAX
                    if (next.isPrice) {
                        next.row.show();
                        return;
                    }

                    // Fetch next options based on intersection of chosen filters (+price)
                    $.ajax({
                        url: ajaxUrl,
                        method: 'GET',
                        dataType: 'json',
                        data: {
                            set_id: setId,
                            next_code: next.code,
                            filters: filters
                        }
                    }).done(function (resp) {
                        var $sel = next.row.find('select');
                        $sel.find('option:not(:first)').remove();

                        var opts = (resp && resp.ok && Array.isArray(resp.options)) ? resp.options : [];
                        if (!opts.length) {
                            $sel.append(
                                $('<option/>').val('').text(humanizeLabel(next.name) + ' — No options in stock')
                            ).prop('disabled', true);
                        } else {
                            opts.forEach(function (o) {
                                $sel.append($('<option/>').val(String(o.value)).text(String(o.label)));
                            });
                            $sel.prop('disabled', false);
                        }

                        next.row.show();
                    }).fail(function () {
                        var $sel = next.row.find('select');
                        $sel.find('option:not(:first)').remove();
                        $sel.append($('<option/>').val('').text(humanizeLabel(next.name) + ' — unavailable')).prop('disabled', true);
                        next.row.show();
                    });
                });
            });
        }

        // Attribute set change handler with robust lookup
        $attrSet.on('change', function () {
            var setId = String($(this).val() || '').trim();
            clearDynamic();
            $submitRow.hide();
            if (!setId) return;

            var profile = profiles[setId] || profiles[parseInt(setId, 10)];

            // Fallback match by label text
            if (!profile) {
                var chosenLabel = ($(this).find('option:selected').text() || '').trim().toLowerCase();
                if (chosenLabel) {
                    Object.keys(profiles).some(function (k) {
                        var p = profiles[k] || {};
                        var lbl = (p.label || '').trim().toLowerCase();
                        if (lbl && lbl === chosenLabel) { profile = p; return true; }
                        return false;
                    });
                }
            }

            if (!profile) {
                $dynamic.append(
                    $('<div class="merlin-field" style="margin:.5rem 0;color:#c00;"></div>')
                        .text('No profile found for the selected set. Check your "Attribute Set Profiles (JSON)" or scope.')
                );
                // eslint-disable-next-line no-console
                console.warn('[Merlin ProductFinder] No profile for setId:',
                    setId,
                    'selectedLabel:',
                    ($(this).find('option:selected').text() || '').trim(),
                    'available profile keys:',
                    Object.keys(profiles));
                return;
            }

            profile.sections = Array.isArray(profile.sections) ? profile.sections : [];
            profile.map      = profile.map && typeof profile.map === 'object' ? profile.map : {};

            if (!profile.sections.length) {
                $dynamic.append(
                    $('<div class="merlin-field" style="margin:.5rem 0;color:#c00;"></div>')
                        .text('Profile has no sections configured for this attribute set.')
                );
                return;
            }

            buildChainForProfile(setId, profile);
        });

        // Clear
        $form.on('click', '#merlin-clear', function (e) {
            e.preventDefault();
            $form[0].reset();
            clearDynamic();
            $submitRow.hide();
        });
    };
});
