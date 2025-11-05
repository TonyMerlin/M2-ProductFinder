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

    return function (config, element) {
        var $form = $(element);
        if (!$form.length) return;

        var $attrSet   = $form.find('select[name="attribute_set_id"]');
        var $dynamic   = $form.find('#merlin-pf-dynamic-fields');
        var $submitRow = $form.find('.merlin-pf-step[data-field="submit"]');

        // Read JSON blobs
        var profiles        = parseJsonFromScriptTag('mpf-profiles-json') || {};
        var optionsBySetId  = parseJsonFromScriptTag('mpf-options-by-set') || {}; // preferred (in-stock)
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
                    globalCodes: Object.keys(optionsByCode || {})
                });
            }
            window.__mpfProfiles = profiles;
            window.__mpfOptionsBySet = optionsBySetId;
            window.__mpfOptionsByCode = optionsByCode;
        })();

        function clearDynamic() { $dynamic.empty(); }

        // Prefer per-set in-stock options; fall back to global options when missing/empty
        function getOptionsFor(setId, code) {
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

            if (!sections.length) {
                $dynamic.append(
                    $('<div class="merlin-field" style="margin:.5rem 0;color:#c00;"></div>')
                        .text('Profile has no sections configured for this attribute set.')
                );
                return;
            }

            var created = [];
            sections.forEach(function (logical, idx) {
                var mappedCode = map[logical] || logical; // allow logical===code
                var opts       = getOptionsFor(setId, mappedCode);
                var labelTxt   = humanizeLabel(logical);

                var $row = buildSelect(logical, labelTxt, opts);

                if (!opts.length) {
                    var $sel = $row.find('select');
                    $sel.empty()
                        .append($('<option/>').val('').text(labelTxt + ' — No options'))
                        .prop('disabled', true);
                }

                if (idx === 0) { $row.show(); } else { $row.hide(); }

                $dynamic.append($row);
                created.push({ name: logical, row: $row });
            });

            // Progressive reveal
            created.forEach(function (item, i) {
                var $current = item.row.find('select');
                $current.on('change', function () {
                    for (var j = i + 1; j < created.length; j++) {
                        created[j].row.hide();
                        created[j].row.find('select').val('');
                    }
                    $submitRow.hide();

                    if ($current.val()) {
                        if (i + 1 < created.length) {
                            created[i + 1].row.show();
                        } else {
                            $submitRow.show();
                        }
                    }
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
                        .text('No profile found for the selected set. Check your “Attribute Set Profiles (JSON)” or scope.')
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
