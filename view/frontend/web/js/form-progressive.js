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

    // Build a select field safely (no HTML string concatenation for options)
    function buildSelect(name, labelText, opts) {
        var $wrap = $('<div/>', {
            'class': 'merlin-field merlin-pf-step'
        });

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

        // Prefer safe JSON from <script type="application/json">, fallback to data-attrs
        var profiles      = parseJsonFromScriptTag('mpf-profiles-json');
        var optionsByCode = parseJsonFromScriptTag('mpf-options-json');

        if (!profiles) {
            var profilesRaw = $form.attr('data-profiles') || '{}';
            try { profiles = JSON.parse(profilesRaw || '{}') || {}; } catch (e) { profiles = {}; }
        }
        if (!optionsByCode) {
            var optionsRaw  = $form.attr('data-options-by-code') || '{}';
            try { optionsByCode = JSON.parse(optionsRaw || '{}') || {}; } catch (e) { optionsByCode = {}; }
        }

        // Normalise profile keys to strings
        (function normaliseProfiles() {
            var norm = {};
            Object.keys(profiles || {}).forEach(function (k) {
                norm[String(k)] = profiles[k] || {};
            });
            profiles = norm;
        })();

        // Diagnostics
        (function logOnce() {
            if (!window.__mpfLogged) {
                window.__mpfLogged = true;
                // eslint-disable-next-line no-console
                console.log('[Merlin ProductFinder] Diagnostics:', {
                    profilesKeys: Object.keys(profiles || {}),
                    optionsCodes: Object.keys(optionsByCode || {})
                });
            }
            window.__mpfProfiles = profiles;
            window.__mpfOptionsByCode = optionsByCode;
        })();

        function clearDynamic() {
            $dynamic.empty();
        }

        function getOptionsForCode(code) {
            code = String(code || '').trim();
            if (!code) return [];
            var list = optionsByCode[code];
            return Array.isArray(list) ? list : [];
        }

        function buildChainForProfile(profile) {
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
                var mappedCode = map[logical] || logical;
                var opts = getOptionsForCode(mappedCode);

                var labelTxt = String(logical)
                    .replace(/_/g, ' ')
                    .replace(/\b\w/g, function (m) { return m.toUpperCase(); });

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

            // Direct / numeric key lookup
            var profile = profiles[setId] || profiles[parseInt(setId, 10)];

            // Fallback: match by label text
            if (!profile) {
                var chosenLabel = ($attrSet.find('option:selected').text() || '').trim().toLowerCase();
                if (chosenLabel) {
                    Object.keys(profiles).some(function (k) {
                        var p = profiles[k] || {};
                        var lbl = (p.label || '').trim().toLowerCase();
                        if (lbl && lbl === chosenLabel) {
                            profile = p;
                            return true;
                        }
                        return false;
                    });
                }
            }

            if (!profile) {
                var $note = $('<div class="merlin-field" style="margin:.5rem 0;color:#c00;"></div>')
                    .text('No profile found for the selected set. Check your “Attribute Set Profiles (JSON)” or scope.');
                $dynamic.append($note);

                // eslint-disable-next-line no-console
                console.warn('[Merlin ProductFinder] No profile for setId:',
                    setId,
                    'selectedLabel:',
                    ($attrSet.find('option:selected').text() || '').trim(),
                    'available keys:',
                    Object.keys(profiles));
                return;
            }

            profile.sections = Array.isArray(profile.sections) ? profile.sections : [];
            profile.map = profile.map && typeof profile.map === 'object' ? profile.map : {};

            if (!profile.sections.length) {
                var $warn = $('<div class="merlin-field" style="margin:.5rem 0;color:#c00;"></div>')
                    .text('Profile has no sections configured for this attribute set.');
                $dynamic.append($warn);
                return;
            }

            buildChainForProfile(profile);
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
