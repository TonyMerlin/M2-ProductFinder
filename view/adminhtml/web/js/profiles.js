define(['jquery', 'underscore'], function ($, _) {
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

    return function (config, element) {
        var $root = $(element);
        if (!$root.length) return;

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

        function renderProfile(setId) {
            $sectionsWrap.empty();

            // Image UI: hydrate from profile.image
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

            // Sections & extras
            profile = profiles[setId] || { sections: [], map: {}, extras: {} };
            (profile.sections || []).forEach(function (logical) {
                var mapped = (profile.map && profile.map[logical]) ? profile.map[logical] : '';
                addSectionRow(logical, mapped);
            });
            var extras = profile.extras || {};
            Object.keys(extras).forEach(function (k) { addExtraRow(k, extras[k]); });
        }

        // Merge current UI into profiles[setId] (non-destructive to other sets)
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

            profile.label    = getSetName(setId);
            profile.sections = newSections;
            profile.map      = newMap;
            profile.extras   = newExtras;
            profile.image    = String($imgHiddenUrl.val() || '').trim();

            profiles[setId] = profile;
        }

        // Attribute set switch: persist current ? render next
        $attrsetSelect.on('change', function () {
            var newId = String($(this).val() || '').trim();

            if (currentSetId) {
                collectIntoProfile(currentSetId);
                writeJsonToTextarea();
            }

            currentSetId = newId || null;
            if (currentSetId) renderProfile(currentSetId);
        });

        // New/Reset
        $root.on('click', '[data-mpf-new-profile]', function () {
            var setId = String($attrsetSelect.val() || '').trim();
            if (!setId) { alert($.mage ? $.mage.__('Please select an attribute set first.') : 'Please select an attribute set first.'); return; }
            profiles[setId] = { label: getSetName(setId), sections: [], map: {}, extras: {}, image: '' };
            currentSetId = setId;
            renderProfile(setId);
            writeJsonToTextarea();
        });

        // Add Image (just toggles the row on; upload is separate)
        $root.on('click', '[data-mpf-add-image]', function () {
            $imageRow.show();
        });

        // Upload image (correct field name + form_key)
        $root.on('click', '[data-mpf-image-upload]', function () {
            var file = ($imgInput[0] && $imgInput[0].files && $imgInput[0].files[0]) ? $imgInput[0].files[0] : null;
            if (!file) { alert($.mage ? $.mage.__('Choose an image file first.') : 'Choose an image file first.'); return; }
            if (!uploadUrl) { alert('Upload URL missing.'); return; }

            var fd = new FormData();
            fd.append('profile_image', file);     // <-- must match controller's fileId
            fd.append('form_key', getFormKey());  // <-- CSRF

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
                // Accept shapes: {ok,url}, {url}, {data:{url}}, {result:{url}}
                var url = '';
                if (resp && typeof resp === 'object') {
                    url = resp.url
                       || (resp.ok && resp.url)
                       || (resp.data && resp.data.url)
                       || (resp.result && resp.result.url)
                       || '';
                }
                url = String(url || '').trim();

                if (!url) {
                    alert(resp && (resp.message || resp.error) ? (resp.message || resp.error) : ($.mage ? $.mage.__('Upload failed: no URL returned.') : 'Upload failed: no URL returned.'));
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
            }).fail(function (xhr) {
                alert('Upload failed' + (xhr && xhr.status ? (' (HTTP ' + xhr.status + ')') : ''));
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

        // Add section / extra
        $root.on('click', '[data-mpf-add-section]', function () { addSectionRow('', ''); });
        $root.on('click', '[data-mpf-add-extra]', function () { addExtraRow('', ''); });

        // Delete row
        $root.on('click', '[data-mpf-del]', function () { $(this).closest('.merlin-pf-row').remove(); });

        // Save to JSON
        $root.on('click', '[data-mpf-save-json]', function () {
            var setId = String($attrsetSelect.val() || '').trim();
            if (!setId) { alert($.mage ? $.mage.__('Please select an attribute set first.') : 'Please select an attribute set first.'); return; }
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
