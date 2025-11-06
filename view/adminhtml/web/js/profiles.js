define(['jquery', 'underscore'], function ($, _) {
    'use strict';

    // Safe HTML escape (for any inline text we inject)
    function esc(s) {
        s = String(s == null ? '' : s);
        if (_ && _.escape) return _.escape(s);
        return s
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#39;');
    }

    return function (config, element) {
        var $root = $(element);
        if (!$root.length) return;

        // Stop button clicks from submitting the config form
        $root.on('click', '[data-mpf-new-profile],[data-mpf-add-section],[data-mpf-add-extra],[data-mpf-del],[data-mpf-save-json]', function (e) {
            e.preventDefault();
            e.stopPropagation();
        });

        var fieldId   = $root.data('field-id');                // textarea id Magento uses to save the JSON
        var $textarea = $('#' + fieldId);

        // Always read the **live** textarea as the source of truth so we don't wipe other profiles
        var profiles = {};
        (function readFromTextarea() {
            var raw = ($textarea.val() || '').trim();
            if (!raw) { profiles = {}; return; }
            try { profiles = JSON.parse(raw); }
            catch (e) { profiles = {}; }
            if (typeof profiles !== 'object' || profiles === null || Array.isArray(profiles)) profiles = {};
        })();

        // Attribute-set list passed from PHP (sorted there, but we guard anyway)
        var rawAttrSets = $root.data('attrsets') || [];
        var attrsets = Array.isArray(rawAttrSets) ? rawAttrSets.slice() : [];
        attrsets.sort(function (a, b) {
            return String(a && a.name || '').localeCompare(String(b && b.name || ''), undefined, {sensitivity: 'base'});
        });

        // Build a quick map for id -> name
        var setNameById = {};
        attrsets.forEach(function (s) {
            setNameById[String(s.id)] = s.name;
        });

        // UI bits
        var $sectionsWrap   = $root.find('[data-mpf-sections]');
        var $attrsetSelect  = $root.find('[data-mpf-attrset]');
        var currentSetId    = null;

        function getSetName(setId) {
            if (!setId) return '';
            var idStr = String(setId);
            // Prefer the preloaded list
            if (setNameById[idStr]) return setNameById[idStr];

            // Fallback to the currently selected option’s text (handles scope changes)
            var $opt = $attrsetSelect.find('option[value="' + idStr.replace(/"/g, '\\"') + '"]');
            var txt  = ($opt.text() || '').trim();
            if (txt) return txt;

            // Last resort fallback — but try to never use this
            return 'Attribute Set ' + idStr;
        }

        function writeJsonToTextarea() {
            try {
                $textarea.val(JSON.stringify(profiles, null, 2)).trigger('change');
            } catch (e) {
                // minimal
                $textarea.val(JSON.stringify(profiles)).trigger('change');
            }
        }

        // Collect the current UI rows into the in-memory profiles[setId]
        function collectIntoProfile(setId) {
            if (!setId) return;

            var profile = profiles[setId] || {
                label: getSetName(setId),
                sections: [],
                map: {},
                extras: {}
            };

            var newSections = [];
            var newMap      = {};
            var newExtras   = {};

            $sectionsWrap.children('.merlin-pf-row').each(function () {
                var $row  = $(this);
                var rtype = $row.data('row-type');

                if (rtype === 'section') {
                    var logical = String($row.find('[data-mpf-logical]').val() || '').trim();
                    var mapped  = String($row.find('[data-mpf-mapped]').val() || '').trim();

                    if (logical) {
                        newSections.push(logical);
                        if (mapped) newMap[logical] = mapped;
                    }
                } else if (rtype === 'extra') {
                    var key  = String($row.find('[data-mpf-extra-key]').val() || '').trim();
                    var code = String($row.find('[data-mpf-extra-mapped]').val() || '').trim();
                    if (key && code) newExtras[key] = code;
                }
            });

            profile.label    = getSetName(setId); // ensure proper human label, never "Set 127"
            profile.sections = newSections;
            profile.map      = newMap;
            profile.extras   = newExtras;

            profiles[setId] = profile; // MERGE/UPSERT — never wipe the whole object
        }

        function addSectionRow(logical, mapped) {
            logical = String(logical || '');
            mapped  = String(mapped  || '');

            var html = ''
              + '<div class="merlin-pf-row" data-row-type="section" style="display:flex;gap:8px;align-items:flex-end;margin:6px 0;">'
              + '  <div style="flex:1;">'
              + '    <label style="display:block;margin-bottom:2px;">' + esc($.mage ? $.mage.__('Logical') : 'Logical') + '</label>'
              + '    <input type="text" class="admin__control-text" data-mpf-logical value="' + esc(logical) + '"/>'
              + '  </div>'
              + '  <div style="flex:1;">'
              + '    <label style="display:block;margin-bottom:2px;">' + esc($.mage ? $.mage.__('Attribute Code') : 'Attribute Code') + '</label>'
              + '    <input type="text" class="admin__control-text" data-mpf-mapped value="' + esc(mapped) + '"/>'
              + '  </div>'
              + '  <div>'
              + '    <button type="button" class="action-secondary" data-mpf-del>'
              + '      <span>' + esc($.mage ? $.mage.__('Delete') : 'Delete') + '</span>'
              + '    </button>'
              + '  </div>'
              + '</div>';

            $sectionsWrap.append(html);
        }

        function addExtraRow(key, mapped) {
            key    = String(key    || '');
            mapped = String(mapped || '');

            var html = ''
              + '<div class="merlin-pf-row" data-row-type="extra" style="display:flex;gap:8px;align-items:flex-end;margin:6px 0;">'
              + '  <div style="flex:1;">'
              + '    <label style="display:block;margin-bottom:2px;">' + esc($.mage ? $.mage.__('Extra Key') : 'Extra Key') + '</label>'
              + '    <input type="text" class="admin__control-text" data-mpf-extra-key value="' + esc(key) + '"/>'
              + '  </div>'
              + '  <div style="flex:1;">'
              + '    <label style="display:block;margin-bottom:2px;">' + esc($.mage ? $.mage.__('Attribute Code') : 'Attribute Code') + '</label>'
              + '    <input type="text" class="admin__control-text" data-mpf-extra-mapped value="' + esc(mapped) + '"/>'
              + '  </div>'
              + '  <div>'
              + '    <button type="button" class="action-secondary" data-mpf-del>'
              + '      <span>' + esc($.mage ? $.mage.__('Delete') : 'Delete') + '</span>'
              + '    </button>'
              + '  </div>'
              + '</div>';

            $sectionsWrap.append(html);
        }

        function renderProfile(setId) {
            $sectionsWrap.empty();
            if (!setId) return;

            var profile = profiles[setId] || {
                label: getSetName(setId),
                sections: [],
                map: {},
                extras: {}
            };

            // sections
            (profile.sections || []).forEach(function (logical) {
                var mapped = (profile.map && profile.map[logical]) ? profile.map[logical] : '';
                addSectionRow(logical, mapped);
            });

            // extras
            var extras = profile.extras || {};
            Object.keys(extras).forEach(function (k) {
                addExtraRow(k, extras[k]);
            });
        }

        // When changing the selected attribute set: save current edits -> switch
        $attrsetSelect.on('change', function () {
            var newId = String($(this).val() || '').trim();

            if (currentSetId) {
                // collect the current UI into the current profile and MERGE it
                collectIntoProfile(currentSetId);
                writeJsonToTextarea();
            }

            currentSetId = newId || null;
            renderProfile(currentSetId);
        });

        // Create/Reset a profile for the currently selected set (non-destructive to others)
        $root.on('click', '[data-mpf-new-profile]', function () {
            var setId = String($attrsetSelect.val() || '').trim();
            if (!setId) {
                alert($.mage ? $.mage.__('Please select an attribute set first.') : 'Please select an attribute set first.');
                return;
            }

            profiles[setId] = {
                label: getSetName(setId),
                sections: [],
                map: {},
                extras: {}
            };
            currentSetId = setId;
            renderProfile(setId);
            writeJsonToTextarea();
        });

        // Add section / extra row
        $root.on('click', '[data-mpf-add-section]', function () { addSectionRow('', ''); });
        $root.on('click', '[data-mpf-add-extra]', function () { addExtraRow('', ''); });

        // Delete row
        $root.on('click', '[data-mpf-del]', function () {
            $(this).closest('.merlin-pf-row').remove();
        });

        // Explicit "Save to JSON Field" — merges current UI into profiles and writes to textarea
        $root.on('click', '[data-mpf-save-json]', function () {
            var setId = String($attrsetSelect.val() || '').trim();
            if (!setId) {
                alert($.mage ? $.mage.__('Please select an attribute set first.') : 'Please select an attribute set first.');
                return;
            }
            collectIntoProfile(setId);
            writeJsonToTextarea();
        });

        // On form submit, persist the current working set as well
        var $configForm = $root.parents('form').first();
        $configForm.on('submit', function () {
            if (currentSetId) {
                collectIntoProfile(currentSetId);
            } else {
                var setId = String($attrsetSelect.val() || '').trim();
                if (setId) collectIntoProfile(setId);
            }
            writeJsonToTextarea();
        });
    };
});
