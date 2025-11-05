define(['jquery', 'underscore'], function ($, _) {
    'use strict';

    // Safe escape if underscore missing for any reason
    var esc = (_ && _.escape) ? _.escape : function (s) {
        return String(s)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#39;');
    };

    return function (config, element) {
        var $root = $(element);
        if (!$root.length) return;

        // prevent system config form submission from our buttons
        $root.on('click', '[data-mpf-new-profile],[data-mpf-add-section],[data-mpf-add-extra],[data-mpf-del],[data-mpf-save-json]', function (e) {
            e.preventDefault();
            e.stopPropagation();
        });

        var fieldId        = $root.data('field-id');
        var $textarea      = $('#' + fieldId); // REAL system.xml field (hidden)
        var initialJson    = $root.data('initial') || '{}';
        var rawAttrSets    = $root.data('attrsets') || [];
        var $sectionsWrap  = $root.find('[data-mpf-sections]');
        var $attrsetSelect = $root.find('[data-mpf-attrset]');

        // Keep track of the currently edited set id
        var currentSetId = null;

        // parse initial profiles JSON
        var profiles = {};
        try { profiles = JSON.parse(initialJson); } catch (e) { profiles = {}; }

        // parse attribute sets list
        var attrsets = Array.isArray(rawAttrSets) ? rawAttrSets : (function () {
            try { return JSON.parse(rawAttrSets) || []; } catch (e) { return []; }
        })();

        // ----- Utilities -----
        function findAttrsetName(id) {
            id = parseInt(id, 10);
            for (var i = 0; i < attrsets.length; i++) {
                if (parseInt(attrsets[i].id, 10) === id) return attrsets[i].name;
            }
            return 'Set ' + id;
        }

        function sanitize(val) {
            return (val == null) ? '' : String(val).trim();
        }

        function writeJsonToField() {
            try {
                $textarea.val(JSON.stringify(profiles, null, 2)).trigger('change');
            } catch (e) {
                $textarea.val(JSON.stringify(profiles)).trigger('change');
            }
        }

        // ----- Collect UI -> profiles[currentSetId] (MERGE, do not overwrite others) -----
        function collectIntoProfile(setId) {
            if (!setId) return;

            var profile = profiles[setId] || {
                label: findAttrsetName(setId),
                sections: [],
                map: {},
                extras: {}
            };

            var newSections = [];
            var newMap = {};
            var newExtras = {};

            $sectionsWrap.children('.merlin-pf-row').each(function () {
                var $row = $(this);
                var type = $row.data('row-type');

                if (type === 'section') {
                    var logical = sanitize($row.find('[data-mpf-logical]').val());
                    var mapped  = sanitize($row.find('[data-mpf-mapped]').val());
                    if (logical) {
                        newSections.push(logical);
                        if (mapped) {
                            newMap[logical] = mapped;
                        }
                    }
                } else if (type === 'extra') {
                    var key  = sanitize($row.find('[data-mpf-extra-key]').val());
                    var attr = sanitize($row.find('[data-mpf-extra-mapped]').val());
                    if (key && attr) {
                        newExtras[key] = attr;
                    }
                }
            });

            profile.sections = newSections;
            profile.map      = newMap;
            profile.extras   = newExtras;

            profiles[setId] = profile; // MERGE into the big object
        }

        // ----- Renderers -----
        function renderProfile(setId) {
            $sectionsWrap.empty();
            if (!setId) return;

            var profile = profiles[setId] || {
                label: findAttrsetName(setId),
                sections: [],
                map: {},
                extras: {}
            };

            (profile.sections || []).forEach(function (logical) {
                var mapped = (profile.map && profile.map[logical]) ? profile.map[logical] : '';
                addSectionRow(logical, mapped);
            });

            var extras = profile.extras || {};
            Object.keys(extras).forEach(function (extraKey) {
                addExtraRow(extraKey, extras[extraKey]);
            });
        }

        function addSectionRow(logical, mapped) {
            logical = sanitize(logical);
            mapped  = sanitize(mapped);

            var html = '' +
                '<div class="merlin-pf-row" data-row-type="section">' +
                    '<div class="admin__field inline">' +
                        '<label class="admin__field-label"><span>Logical</span></label>' +
                        '<div class="admin__field-control">' +
                            '<input type="text" class="admin__control-text" data-mpf-logical ' +
                                   'placeholder="e.g. appliance_type" value="' + esc(logical) + '"/>' +
                        '</div>' +
                    '</div>' +
                    '<div class="admin__field inline">' +
                        '<label class="admin__field-label"><span>Attribute Code</span></label>' +
                        '<div class="admin__field-control">' +
                            '<input type="text" class="admin__control-text" data-mpf-mapped ' +
                                   'placeholder="e.g. refrigerator_type" value="' + esc(mapped) + '"/>' +
                        '</div>' +
                    '</div>' +
                    '<div class="admin__field inline">' +
                        '<button type="button" class="action-delete" data-mpf-del>Delete</button>' +
                    '</div>' +
                '</div>';

            $sectionsWrap.append(html);
        }

        function addExtraRow(key, mapped) {
            key    = sanitize(key);
            mapped = sanitize(mapped);

            var html = '' +
                '<div class="merlin-pf-row" data-row-type="extra">' +
                    '<div class="admin__field inline">' +
                        '<label class="admin__field-label"><span>Extra Key</span></label>' +
                        '<div class="admin__field-control">' +
                            '<input type="text" class="admin__control-text" data-mpf-extra-key ' +
                                   'placeholder="e.g. energy_rating" value="' + esc(key) + '"/>' +
                        '</div>' +
                    '</div>' +
                    '<div class="admin__field inline">' +
                        '<label class="admin__field-label"><span>Attribute Code</span></label>' +
                        '<div class="admin__field-control">' +
                            '<input type="text" class="admin__control-text" data-mpf-extra-mapped ' +
                                   'placeholder="e.g. energy_rating" value="' + esc(mapped) + '"/>' +
                        '</div>' +
                    '</div>' +
                    '<div class="admin__field inline">' +
                        '<button type="button" class="action-delete" data-mpf-del>Delete</button>' +
                    '</div>' +
                '</div>';

            $sectionsWrap.append(html);
        }

        // ----- Events -----

        // When switching sets: FIRST collect current, write to field, THEN render new
        $attrsetSelect.on('change', function () {
            var newId = $(this).val();

            // capture current edits before switching away
            if (currentSetId) {
                collectIntoProfile(currentSetId);
                writeJsonToField();
            }

            currentSetId = newId || null;
            renderProfile(currentSetId);
        });

        // New / Reset profile for current set (doesn't touch other profiles)
        $root.on('click', '[data-mpf-new-profile]', function () {
            var setId = $attrsetSelect.val();
            if (!setId) { alert('Please select an attribute set first.'); return; }

            profiles[setId] = {
                label: findAttrsetName(setId),
                sections: [],
                map: {},
                extras: {}
            };

            currentSetId = setId;
            renderProfile(setId);
            writeJsonToField(); // keep field in sync
        });

        // Add rows
        $root.on('click', '[data-mpf-add-section]', function () { addSectionRow('', ''); });
        $root.on('click', '[data-mpf-add-extra]', function () { addExtraRow('', ''); });

        // Delete row
        $root.on('click', '[data-mpf-del]', function () {
            $(this).closest('.merlin-pf-row').remove();
        });

        // Save to JSON field = collect current and write (MERGE)
        $root.on('click', '[data-mpf-save-json]', function () {
            var setId = $attrsetSelect.val();
            if (!setId) { alert('Please select an attribute set first.'); return; }
            collectIntoProfile(setId);
            writeJsonToField();
        });

        // Also sync right before the Config form submits
        var $configForm = $root.parents('form').first();
        $configForm.on('submit', function () {
            if (currentSetId) {
                collectIntoProfile(currentSetId);
            } else {
                var setId = $attrsetSelect.val();
                if (setId) collectIntoProfile(setId);
            }
            writeJsonToField();
        });
    };
});
