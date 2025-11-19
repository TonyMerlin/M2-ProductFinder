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

    return function (config, element) {
        var $form = $(element);
        if (!$form.length) return;

        var $attrSet   = $form.find('select[name="attribute_set_id"]');
        var $dynamic   = $form.find('#merlin-pf-dynamic-fields');
        var $submitRow = $form.find('.merlin-pf-step[data-field="submit"]');

        // Price slider elements
        var $priceBlock   = $form.find('#mpf-price-block');
        var $priceMin     = $form.find('#mpf-price-min');
        var $priceMax     = $form.find('#mpf-price-max');
        var $priceMinLab  = $form.find('#mpf-price-min-label');
        var $priceMaxLab  = $form.find('#mpf-price-max-label');
        var $priceProg    = $form.find('#mpf-price-progress');

        function formatCurrency(val) {
        val = parseFloat(val || 0);
        return '\u00A3' + val.toFixed(0);
        }

        function syncPriceUI() {
            var min = parseFloat($priceMin.val() || 0);
            var max = parseFloat($priceMax.val() || 0);
            var absMin = parseFloat($priceMin.attr('min') || 0);
            var absMax = parseFloat($priceMin.attr('max') || 0);

            // keep handles ordered
            if (min > max) {
                var tmp = min;
                min = max;
                max = tmp;
                $priceMin.val(min);
                $priceMax.val(max);
            }

            $priceMinLab.text(formatCurrency(min));
            $priceMaxLab.text(formatCurrency(max));

            var span = absMax - absMin;
            if (span <= 0) {
                $priceProg.css({left: '0%', right: '0%'});
            } else {
                var left  = ((min - absMin) / span) * 100;
                var right = 100 - ((max - absMin) / span) * 100;
                $priceProg.css({left: left + '%', right: right + '%'});
            }
        }

        if ($priceBlock.length) {
            // initialise labels/progress
            syncPriceUI();
            $priceBlock.hide();

            $priceMin.on('input change', syncPriceUI);
            $priceMax.on('input change', syncPriceUI);
        }

        var ajaxUrl    = String($form.data('ajax-url') || '').trim(); // product-finder/ajax/options

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
            var imageUrl = (profile.image || '').trim(); // <-- NEW

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

                var seed = getSeedOptionsFor(setId, mappedCode);
                var $row = buildSelect(logical, labelTxt, seed);

                // Step badge
                var $label = $row.find('label');
                $label.attr('data-step', (idx + 1));

                if (idx === 0) { $row.show(); } else { $row.hide(); }

                $dynamic.append($row);
                created.push({ name: logical, code: mappedCode, row: $row });
            });

            // Insert image row AFTER the first field, hidden initially
            var $mediaRow = null;
            if (imageUrl && created.length > 0) {
                $mediaRow = buildImageRow(imageUrl, profile.label || '');
                // place between first and second fields
                $mediaRow.insertAfter(created[0].row);
            }

            // Progressive reveal with AJAX intersection filtering
            created.forEach(function (item, i) {
                var $current = item.row.find('select');
                $current.on('change', function () {
                    // Hide & reset downstream selects
                    for (var j = i + 1; j < created.length; j++) {
                        var $selDown = created[j].row.find('select');
                        created[j].row.hide();
                        $selDown.val('');
                        $selDown.find('option:not(:first)').remove();
                        $selDown.prop('disabled', false);
                    }
                    $submitRow.hide();

                    var val = $current.val();

                    // Special handling for media visibility tied to first field
                    if ($mediaRow && i === 0) {
                        if (val) {
                            $mediaRow.show();
                        } else {
                            $mediaRow.hide();
                        }
                    }

                    if (!val) return;

                    // Last field? Then show submit
                    if (i === created.length - 1) {
                        $submitRow.show();
                        return;
                    }

                    // Build filters from selected values up to i
                    var filters = {};
                    for (var k = 0; k <= i; k++) {
                        var v = created[k].row.find('select').val();
                        if (v) filters[created[k].code] = v;
                    }

                    var next = created[i + 1];

                    // If no AJAX URL (shouldn't happen), just show next row
                    if (!ajaxUrl) {
                        next.row.show();
                        return;
                    }

                    // Fetch next options based on intersection of chosen filters
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
                                $('<option/>')
                                    .val('')
                                    .text(humanizeLabel(next.name) + ' â€” No options in stock')
                            ).prop('disabled', true);
                        } else {
                            opts.forEach(function (o) {
                                $sel.append($('<option/>').val(String(o.value)).text(String(o.label)));
                            });
                            $sel.prop('disabled', false);
                        }

                        next.row.show();

                        // Optional auto-advance if only one option:
                        // if (opts.length === 1) { $sel.val(String(opts[0].value)).trigger('change'); }
                    }).fail(function () {
                        var $sel = next.row.find('select');
                        $sel.find('option:not(:first)').remove();
                        $sel.append($('<option/>').val('').text(humanizeLabel(next.name) + ' â€” unavailable')).prop('disabled', true);
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

            // Show price slider when an attribute set is chosen
            if ($priceBlock && $priceBlock.length) {
                if (setId) {
                    $priceBlock.show();
                    syncPriceUI();
                } else {
                    $priceBlock.hide();
                }
            }

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
                        .text('No profile found for the selected set. Check your â€œAttribute Set Profiles (JSON)â€ or scope.')
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

            if ($priceBlock && $priceBlock.length) {
                $priceBlock.hide();
                if ($priceMin.length && $priceMax.length) {
                    $priceMin.val($priceMin.attr('min') || 0);
                    $priceMax.val($priceMax.attr('max') || 0);
                    syncPriceUI();
                }
            }
        });
    };
});
