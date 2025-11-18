define(['jquery', 'underscore', 'jquery-ui-modules/sortable'], function ($, _) {
    'use strict';

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

    function getFormKey() {
        var el = document.querySelector('input[name="form_key"]');
        if (el && el.value) return el.value;
        if (window.FORM_KEY) return window.FORM_KEY;
        var m = document.cookie.match(/(?:^|;\s*)form_key=([^;]+)/);
        return m ? decodeURIComponent(m[1]) : '';
    }

    function parseJsonFromScriptTag(id) {
        var el = document.getElementById(id);
        if (!el) return {};
        try {
            return JSON.parse(el.textContent || el.innerText || '{}') || {};
        } catch (e) {
            return {};
        }
    }

    // inject builder styles
    function injectBuilderStyles() {
        var STYLE_ID = 'merlin-pf-builder-styles';
        if (document.getElementById(STYLE_ID)) return;

        var css = ''
            + '.merlin-pf-builder-wrapper{margin-top:15px;display:flex;flex-wrap:wrap;gap:20px;}'
            + '.merlin-pf-builder-column{background:#fafafa;border:1px solid #d6d6d6;border-radius:8px;padding:12px;flex:1 1 320px;min-width:280px;box-shadow:0 1px 2px rgba(0,0,0,.04);}'
            + '@media (min-width:900px){.merlin-pf-builder-wrapper{flex-wrap:nowrap;}}'
            + '.merlin-pf-builder-title{font-weight:600;margin-bottom:8px;font-size:14px;color:#333;}'
            + '.merlin-pf-attr-list,.merlin-pf-step-list{min-height:140px;margin:0;padding:0;list-style:none;border:1px dashed #c8c8c8;border-radius:6px;background:#fff;}'
            + '.merlin-pf-attr-item,.merlin-pf-step-item{background:#fff;border:1px solid #d8d8d8;padding:6px 10px 6px 30px;margin:6px;border-radius:4px;font-size:13px;color:#333;position:relative;cursor:grab;transition:background .15s ease,border-color .15s ease;display:flex;align-items:center;justify-content:space-between;gap:6px;}'
            + '.merlin-pf-attr-item:hover,.merlin-pf-step-item:hover{background:#f1f3f7;border-color:#bbb;}'
            + '.merlin-pf-dragging{opacity:.6;cursor:grabbing!important;}'
            + '.merlin-pf-handle{position:absolute;left:8px;top:50%;transform:translateY(-50%);cursor:grab;width:12px;height:12px;opacity:.7;}'
            + '.merlin-pf-handle::before{content:"::";font-size:13px;color:#888;line-height:12px;}'
            + '.merlin-pf-attr-item:hover .merlin-pf-handle,.merlin-pf-step-item:hover .merlin-pf-handle{opacity:1;}'
            + '.merlin-pf-drop-target{border:2px dashed #4f9ddf!important;background:#ecf6ff!important;}'
            + '.merlin-pf-step-label{flex:1;}'
            + '.merlin-pf-step-remove span{font-size:11px;}';

        var style = document.createElement('style');
        style.type = 'text/css';
        style.id = STYLE_ID;
        style.appendChild(document.createTextNode(css));
        document.head.appendChild(style);
    }

    return function (config, element) {
        var $root = $(element);
        if (!$root.length) return;

        injectBuilderStyles();

        // Prevent config form submission on our buttons
        $root.on('click', [
            '[data-mpf-new-profile]',
            '[data-mpf-add-section]',
            '[data-mpf-add-extra]',
            '[data-mpf-del]',
            '[data-mpf-save-json]',
            '[data-mpf-add-image]',
            '[data-mpf-image-upload]',
            '[data-mpf-image-remove]'
        ].join(','), function (e) {
            e.preventDefault();
            e.stopPropagation();
        });

        var fieldId   = $root.data('field-id');
        var $textarea = $('#' + fieldId);
        var uploadUrl = String($root.data('upload-url') || '');

        // Attribute metadata per set (from profiles.phtml)
        var attrMeta = parseJsonFromScriptTag('mpf-attr-meta-json') || {};

        // Live JSON source-of-truth
        var profiles = {};
        (function readFromTextarea() {
            var raw = ($textarea.val() || '').trim();
            if (!raw) { profiles = {}; return; }
            try { profiles = JSON.parse(raw); } catch (e) { profiles = {}; }
            if (typeof profiles !== 'object' || profiles === null || Array.isArray(profiles)) profiles = {};
        })();

        // Attribute set list
        var rawAttrSets = $root.data('attrsets') || [];
        var attrsets = Array.isArray(rawAttrSets) ? rawAttrSets.slice() : [];
        attrsets.sort(function (a, b) {
            return String(a && a.name || '').localeCompare(String(b && b.name || ''), undefined, {sensitivity: 'base'});
        });

        var setNameById = {};
        attrsets.forEach(function (s) { setNameById[String(s.id)] = s.name; });

        function getSetName(setId) {
            var idStr = String(setId || '');
            if (!idStr) return '';
            if (setNameById[idStr]) return setNameById[idStr];
            var $opt = $root.find('[data-mpf-attrset] option[value="' + idStr.replace(/"/g, '\\"') + '"]');
            var txt  = ($opt.text() || '').trim();
            return txt || ('Attribute Set ' + idStr);
        }

        function writeJsonToTextarea() {
            try { $textarea.val(JSON.stringify(profiles, null, 2)).trigger('change'); }
            catch (e) { $textarea.val(JSON.stringify(profiles)).trigger('change'); }
        }

        var $sectionsWrap   = $root.find('[data-mpf-sections]');
        var $attrsetSelect  = $root.find('[data-mpf-attrset]');
        var $imageRow       = $root.find('[data-mpf-image-row]');
        var $imgPreview     = $root.find('[data-mpf-image-preview]');
        var $imgInput       = $root.find('[data-mpf-image-input]');
        var $imgHiddenUrl   = $root.find('[data-mpf-image-url]');
        var $attrList       = $root.find('[data-mpf-attr-list]');
        var $stepList       = $root.find('[data-mpf-step-list]');
        var currentSetId    = null;

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

        function makeAttrLi(code, label) {
            code  = String(code || '');
            label = String(label || code);

            var $li = $('<li/>', {
                'class': 'merlin-pf-attr-item',
                'data-code': code,
                'data-label': label
            });
            $li.append(
                $('<span/>', {'class': 'merlin-pf-handle'}),
                $('<span/>').text(label + ' (' + code + ')')
            );
            return $li;
        }

        // Clean label: "Item Type (item_type)"
        function makeStepLi(code, label, logical) {
            code    = String(code || '');
            label   = String(label || code);
            logical = logical || code;

            var $li = $('<li/>', {
                'class': 'merlin-pf-step-item',
                'data-code': code,
                'data-logical': logical,
                'data-label': label
            });
            $li.append(
                $('<span/>', {'class': 'merlin-pf-handle'}),
                $('<span/>', {'class': 'merlin-pf-step-label'}).text(label + ' (' + code + ')'),
                $('<button type="button" class="action-secondary merlin-pf-step-remove"><span>'
                    + esc($.mage ? $.mage.__('Remove') : 'Remove') + '</span></button>')
            );
            return $li;
        }

        /**
         * Populate the drag & drop builder lists for the given attribute set.
         * IMPORTANT: Profile Steps follow profile.sections order.
         */
        function renderBuilder(setId) {
            $attrList.empty();
            $stepList.empty();
            if (!setId) return;

            var allAttrs = attrMeta[setId] || [];
            var profile  = profiles[setId] || { sections: [], map: {} };

            // Index attributes by code
            var byCode = {};
            allAttrs.forEach(function (attr) {
                var code = String(attr.code || '');
                if (!code) return;
                byCode[code] = attr;
            });

            var usedCodes = {};

            // 1) Build Profile Steps in profile.sections order
            (profile.sections || []).forEach(function (logical) {
                var code = (profile.map && profile.map[logical]) ? profile.map[logical] : logical;
                code = String(code || '');
                if (!code) return;

                var attr  = byCode[code] || {};
                var label = String(attr.label || code);

                $stepList.append(makeStepLi(code, label, logical));
                usedCodes[code] = true;
            });

            // 2) Unused attrs in Available list
            allAttrs.forEach(function (attr) {
                var code  = String(attr.code || '');
                if (!code || usedCodes[code]) return;
                var label = String(attr.label || code);
                $attrList.append(makeAttrLi(code, label));
            });

            initSortables();
        }

        function renderProfile(setId) {
            $sectionsWrap.empty();

            var profile = profiles[setId] || {};
            var url     = String(profile.image || '').trim();
            if (url) {
                $imgHiddenUrl.val(url);
                $imgPreview.attr('src', url).show();
                $imageRow.show();
            } else {
                $imgHiddenUrl.val('');
                $imgPreview.hide().attr('src', '');
                $imageRow.hide();
            }

            profile = profiles[setId] || { sections: [], map: {}, extras: {} };
            (profile.sections || []).forEach(function (logical) {
                var mapped = (profile.map && profile.map[logical]) ? profile.map[logical] : '';
                addSectionRow(logical, mapped);
            });
            var extras = profile.extras || {};
            Object.keys(extras).forEach(function (k) { addExtraRow(k, extras[k]); });

            renderBuilder(setId);
        }

        // Merge current UI into profiles[setId]
        function collectIntoProfile(setId) {
            if (!setId) return;

            var profile = profiles[setId] || {
                label: getSetName(setId),
                sections: [],
                map: {},
                extras: {},
                image: ''
            };

            var newSections = [];
            var newMap      = {};
            var newExtras   = {};

            // sections order = step list order
            $stepList.children('.merlin-pf-step-item').each(function () {
                var $item   = $(this);
                var code    = String($item.data('code') || '').trim();
                var logical = String($item.data('logical') || code).trim();
                if (!code || !logical) return;
                newSections.push(logical);
                newMap[logical] = code;
            });

            // Extras from manual rows
            $sectionsWrap.children('.merlin-pf-row').each(function () {
                var $row  = $(this);
                var rtype = $row.data('row-type');

                if (rtype === 'extra') {
                    var key  = String($row.find('[data-mpf-extra-key]').val() || '').trim();
                    var code = String($row.find('[data-mpf-extra-mapped]').val() || '').trim();
                    if (key && code) newExtras[key] = code;
                }
            });

            profile.label    = getSetName(setId);
            profile.sections = newSections;
            profile.map      = newMap;
            profile.extras   = newExtras;
            profile.image    = String($imgHiddenUrl.val() || '').trim();

            profiles[setId] = profile;
        }

        // jQuery UI sortable wiring (DND between lists)
        function initSortables() {
            if ($attrList.data('ui-sortable')) $attrList.sortable('destroy');
            if ($stepList.data('ui-sortable')) $stepList.sortable('destroy');

            $attrList.sortable({
                connectWith: '[data-mpf-step-list]',
                items: '> li',
                placeholder: 'merlin-pf-drop-target',
                handle: '.merlin-pf-handle',
                receive: function (event, ui) {
                    var $item = $(ui.item);
                    // convert STEP dropped back into AVAILABLE
                    if ($item.hasClass('merlin-pf-step-item')) {
                        var code  = String($item.data('code') || '').trim();
                        var label = String($item.data('label') || code).trim();
                        var $attr = makeAttrLi(code, label);
                        $item.replaceWith($attr);
                    }
                }
            }).disableSelection();

            $stepList.sortable({
                connectWith: '[data-mpf-attr-list]',
                items: '> li',
                placeholder: 'merlin-pf-drop-target',
                handle: '.merlin-pf-handle',
                receive: function (event, ui) {
                    var $item = $(ui.item);
                    // convert AVAILABLE dropped into STEP
                    if ($item.hasClass('merlin-pf-attr-item')) {
                        var code   = String($item.data('code') || '').trim();
                        var label  = String($item.data('label') || code).trim();
                        var $step  = makeStepLi(code, label, code);
                        $item.replaceWith($step);
                    }
                }
            }).disableSelection();
        }

        // click "Remove" on a step -> move back to available
        $root.on('click', '.merlin-pf-step-remove', function () {
            var $li   = $(this).closest('.merlin-pf-step-item');
            var code  = String($li.data('code') || '').trim();
            var label = String($li.data('label') || code).trim();
            if (code) {
                $attrList.append(makeAttrLi(code, label));
            }
            $li.remove();
        });

        // Attribute set switch: persist current ? render next + builder
        $attrsetSelect.on('change', function () {
            var newId = String($(this).val() || '').trim();

            if (currentSetId) {
                collectIntoProfile(currentSetId);
                writeJsonToTextarea();
            }

            currentSetId = newId || null;
            if (currentSetId) {
                renderProfile(currentSetId);
            } else {
                $sectionsWrap.empty();
                $attrList.empty();
                $stepList.empty();
            }
        });

        // New/Reset
        $root.on('click', '[data-mpf-new-profile]', function () {
            var setId = String($attrsetSelect.val() || '').trim();
            if (!setId) {
                alert($.mage ? $.mage.__('Please select an attribute set first.') : 'Please select an attribute set first.');
                return;
            }
            profiles[setId] = { label: getSetName(setId), sections: [], map: {}, extras: {}, image: '' };
            currentSetId = setId;
            renderProfile(setId);
            writeJsonToTextarea();
        });

        // Add Image (just toggles the row on; upload is separate)
        $root.on('click', '[data-mpf-add-image]', function () {
            $imageRow.show();
        });

        // Upload image
        $root.on('click', '[data-mpf-image-upload]', function () {
            var file = ($imgInput[0] && $imgInput[0].files && $imgInput[0].files[0]) ? $imgInput[0].files[0] : null;
            if (!file) { alert($.mage ? $.mage.__('Choose an image file first.') : 'Choose an image file first.'); return; }
            if (!uploadUrl) { alert('Upload URL missing.'); return; }

            var fd = new FormData();
            fd.append('image', file);
            fd.append('form_key', getFormKey());

            $.ajax({
                url: uploadUrl,
                method: 'POST',
                data: fd,
                processData: false,
                contentType: false,
                dataType: 'json',
                showLoader: true,
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            }).done(function (resp) {
                var url = '';
                if (resp && typeof resp === 'object') {
                    url = resp.url || (resp.data && resp.data.url) || (resp.result && resp.result.url) || '';
                }
                url = String(url || '').trim();

                if (!url) {
                    alert(resp && resp.message ? resp.message : ($.mage ? $.mage.__('Upload failed: no URL returned.') : 'Upload failed: no URL returned.'));
                    return;
                }

                $imgHiddenUrl.val(url);
                $imgPreview.attr('src', url).show();

                var sid = String($attrsetSelect.val() || '').trim();
                if (sid) {
                    profiles[sid] = profiles[sid] || { label: getSetName(sid), sections: [], map: {}, extras: {}, image: '' };
                    profiles[sid].image = url;
                    writeJsonToTextarea();
                }
            }).fail(function () {
                alert('Upload failed.');
            });
        });

        // Remove image
        $root.on('click', '[data-mpf-image-remove]', function () {
            $imgHiddenUrl.val('');
            $imgPreview.hide().attr('src', '');
            var sid = String($attrsetSelect.val() || '').trim();
            if (sid) {
                profiles[sid] = profiles[sid] || { label: getSetName(sid), sections: [], map: {}, extras: {}, image: '' };
                profiles[sid].image = '';
                writeJsonToTextarea();
            }
        });

        // Add section / extra (legacy manual UI still supported)
        $root.on('click', '[data-mpf-add-section]', function () { addSectionRow('', ''); });
        $root.on('click', '[data-mpf-add-extra]', function () { addExtraRow('', ''); });

        // Delete row
        $root.on('click', '[data-mpf-del]', function () { $(this).closest('.merlin-pf-row').remove(); });

        // Save to JSON
        $root.on('click', '[data-mpf-save-json]', function () {
            var setId = String($attrsetSelect.val() || '').trim();
            if (!setId) {
                alert($.mage ? $.mage.__('Please select an attribute set first.') : 'Please select an attribute set first.');
                return;
            }
            collectIntoProfile(setId);
            writeJsonToTextarea();
        });

        // Persist on form submit
        $root.parents('form').first().on('submit', function () {
            var sid = currentSetId || String($attrsetSelect.val() || '').trim();
            if (sid) { collectIntoProfile(sid); writeJsonToTextarea(); }
        });
    };
});
