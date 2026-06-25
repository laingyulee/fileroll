var Preview = (function() {
    var baseUrl = (typeof BASE !== 'undefined') ? BASE : '';
    var TEXT_PREVIEW_MAX_SIZE = 2 * 1024 * 1024;
    var IMAGE_EXTS = ['jpg','jpeg','png','gif','webp','svg','bmp','ico','tiff'];
    var VIDEO_EXTS = ['mp4','webm','ogg','mov'];
    var AUDIO_EXTS = ['mp3','wav','ogg','flac','aac','m4a'];
    var EDITABLE_EXTS = ['txt','md','markdown','log','json','xml','yaml','yml','csv',
        'js','mjs','ts','jsx','tsx','php','py','rb','java','c','cpp','h','cs',
        'go','rs','swift','kt','scala','sh','bash','zsh','sql','r','lua','dart',
        'html','htm','css','scss','less','toml','ini','cfg','conf','dockerfile','makefile'];

    var state = {
        fileId: null, fileName: '', fileSize: 0, fileType: '', folderId: null,
        siblings: [], currentIndex: -1, editMode: false, fileContent: '',
        attributeOnly: false
    };

    function getExt(name) {
        if (!name || name.indexOf('.') === -1) return '';
        return name.split('.').pop().toLowerCase();
    }

    function isEditable(name) {
        return EDITABLE_EXTS.indexOf(getExt(name)) !== -1;
    }

    function isImage(name) {
        return IMAGE_EXTS.indexOf(getExt(name)) !== -1;
    }

    function isVideo(name) {
        return VIDEO_EXTS.indexOf(getExt(name)) !== -1;
    }

    function isAudio(name) {
        return AUDIO_EXTS.indexOf(getExt(name)) !== -1;
    }

    function isPdf(name) {
        return getExt(name) === 'pdf';
    }

    function isTextFile(name, mime) {
        if (isEditable(name)) return true;
        if (mime && mime.indexOf('text/') === 0) return true;
        return false;
    }

    function isPreviewable(name, mime) {
        if (isImage(name)) return true;
        if (isVideo(name)) return true;
        if (isAudio(name)) return true;
        if (isPdf(name)) return true;
        if (isTextFile(name, mime)) return true;
        return false;
    }

    function isAttributeOnly(name, mime) {
        return !isPreviewable(name, mime);
    }

    function escapeHtml(t) { var d = document.createElement('div'); d.textContent = t; return d.innerHTML; }

    function formatSize(b) {
        if (b === 0) return '0 B';
        var u = ['B','KB','MB','GB','TB'];
        var i = Math.floor(Math.log(b) / Math.log(1024));
        return (b / Math.pow(1024, i)).toFixed(i > 0 ? 1 : 0) + ' ' + u[i];
    }

    function open(fileId, fileName, fileSize, fileType, folderId, editMode) {
        state.attributeOnly = !editMode && isAttributeOnly(fileName, fileType);

        if (!state.attributeOnly && isTextFile(fileName, fileType) && fileSize > TEXT_PREVIEW_MAX_SIZE) {
            showToast('Text file too large to preview (' + (fileSize / 1024 / 1024).toFixed(1) + 'MB). Max is 2MB.', 'warning');
            return;
        }

        state.fileId = fileId;
        state.fileName = fileName;
        state.fileSize = fileSize;
        state.fileType = fileType;
        state.folderId = folderId;
        state.siblings = [];
        state.currentIndex = -1;
        state.editMode = !!editMode;
        state.fileContent = '';

        renderModal();
        loadContent();

        if (folderId && isImage(fileName)) loadSiblings();
    }

    function renderModal() {
        var existing = document.getElementById('preview-modal');
        if (existing) existing.remove();

        var overlay = document.getElementById('modal-overlay');
        var isImg = isImage(state.fileName);
        var galleryHtml = isImg ? '<div id="preview-gallery" class="preview-gallery">' +
            '<button class="preview-nav preview-nav-prev" onclick="Preview.prev()" style="display:none">&lsaquo;</button>' +
            '<button class="preview-nav preview-nav-next" onclick="Preview.next()" style="display:none">&rsaquo;</button>' +
            '</div>' : '';

        var dialogClass = 'modal-dialog preview-dialog';
        if (state.attributeOnly) {
            dialogClass += ' preview-dialog-attr';
        } else if (isTextFile(state.fileName, state.fileType)) {
            dialogClass += ' preview-dialog-text';
        }

        var ext = getExt(state.fileName);
        var LANG_LABELS = {js:'JS',mjs:'MJS',jsx:'JSX',ts:'TS',tsx:'TSX',py:'PY',rb:'RB',
            java:'JAVA',c:'C',cpp:'CPP',h:'H',cs:'CS',go:'GO',rs:'RS',swift:'SWIFT',
            kt:'KT',scala:'SCALA',sh:'SH',bash:'BASH',sql:'SQL',r:'R',lua:'LUA',dart:'DART',
            html:'HTML',htm:'HTML',css:'CSS',scss:'SCSS',less:'LESS',json:'JSON',xml:'XML',
            svg:'SVG',yaml:'YAML',yml:'YML',toml:'TOML',ini:'INI',md:'MD',markdown:'MD',
            csv:'CSV',log:'LOG',txt:'TXT',php:'PHP'};
        var langLabel = LANG_LABELS[ext] || '';

        var editBtn = '';
        if (!state.editMode && isEditable(state.fileName)) {
            editBtn = '<button class="btn btn-sm btn-secondary preview-header-btn" onclick="Preview.toggleEdit()">' +
                '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>' +
                ' Edit</button>';
        }

        var saveBtn = state.editMode ? '<button class="btn btn-sm btn-primary preview-header-btn" onclick="Preview.save()">' +
            '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"/><polyline points="17 21 17 13 7 13 7 21"/><polyline points="7 3 7 8 15 8"/></svg>' +
            ' Save</button>' : '';

        var statusHtml = state.editMode ? '<span class="preview-status" id="preview-status"></span>' : '';

        var modal = document.createElement('div');
        modal.id = 'preview-modal';
        modal.className = 'modal';
        modal.innerHTML =
            '<div class="' + dialogClass + '">' +
                '<div class="modal-header preview-header">' +
                    '<div class="preview-header-left">' +
                        '<span class="preview-filename">' + escapeHtml(state.fileName) + '</span>' +
                        (langLabel ? '<span class="preview-lang-badge">' + langLabel + '</span>' : '') +
                        '<span class="preview-size">' + formatSize(state.fileSize) + '</span>' +
                        statusHtml +
                    '</div>' +
                    '<div class="preview-header-right">' +
                        editBtn + saveBtn +
                        '<a href="' + baseUrl + '/files/' + state.fileId + '" class="btn btn-sm btn-primary preview-header-btn auth-check-download" title="Download">' +
                            '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>' +
                            ' Download' +
                        '</a>' +
                        '<button class="modal-close preview-close" onclick="Preview.close()">&times;</button>' +
                    '</div>' +
                '</div>' +
                '<div class="modal-body preview-body">' +
                    galleryHtml +
                    '<div id="preview-content" class="preview-content"></div>' +
                '</div>' +
            '</div>';

        document.getElementById('main-content').appendChild(modal);
        overlay.classList.remove('hidden');
        var main = document.getElementById('main-content');
        if (main) main.classList.add('modal-open');
        overlay.onclick = function(e) { if (e.target === overlay) Preview.close(); };
    }

    function loadContent() {
        var el = document.getElementById('preview-content');
        if (!el) return;
        el.innerHTML = '<div class="preview-loading">Loading...</div>';

        if (state.attributeOnly) { renderAttributes(el); return; }

        if (isImage(state.fileName)) { renderImage(el); return; }

        var ext = getExt(state.fileName);
        if (isVideo(state.fileName)) { renderVideo(el); return; }
        if (isAudio(state.fileName)) { renderAudio(el); return; }
        if (isPdf(state.fileName)) { renderPdf(el); return; }

        if (isTextFile(state.fileName, state.fileType)) {
            fetch(baseUrl + '/files/' + state.fileId + '/preview', {
                headers: {'X-Requested-With': 'XMLHttpRequest'}
            })
                .then(guardAuth)
                .then(function(r) {
                    if (!r.ok) {
                        return r.json().then(function(data) {
                            throw new Error(data.message || 'Failed to load file');
                        }).catch(function() {
                            throw new Error('Failed to load file');
                        });
                    }
                    return r.text();
                })
                .then(function(text) {
                    state.fileContent = text;
                    renderTextEditor(el, text);
                })
                .catch(function(err) { el.innerHTML = '<p class="preview-error">' + escapeHtml(err.message || 'Failed to load file') + '.</p>'; });
            return;
        }

        renderAttributes(el);
    }

    function renderAttributes(el) {
        var ext = getExt(state.fileName) || '—';
        var mime = state.fileType || '—';
        el.innerHTML =
            '<div class="preview-attributes">' +
                '<div class="preview-attr-icon"><svg width="56" height="56" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg></div>' +
                '<div class="preview-attr-row"><span class="preview-attr-label">Name</span><span class="preview-attr-value" title="' + escapeHtml(state.fileName) + '">' + escapeHtml(state.fileName) + '</span></div>' +
                '<div class="preview-attr-row"><span class="preview-attr-label">Size</span><span class="preview-attr-value">' + formatSize(state.fileSize) + '</span></div>' +
                '<div class="preview-attr-row"><span class="preview-attr-label">Type</span><span class="preview-attr-value">' + escapeHtml(mime) + '</span></div>' +
                '<div class="preview-attr-row"><span class="preview-attr-label">Extension</span><span class="preview-attr-value">' + escapeHtml(ext) + '</span></div>' +
            '</div>';
    }

    function renderTextEditor(el, text) {
        el.innerHTML = '';
        var ta = document.createElement('textarea');
        ta.className = 'preview-editor';
        ta.value = text;
        ta.readOnly = !state.editMode;
        ta.spellcheck = false;

        if (state.editMode) {
            ta.addEventListener('input', function() { updateStatus('modified'); });
            ta.addEventListener('keydown', function(e) {
                if ((e.ctrlKey || e.metaKey) && e.key === 's') { e.preventDefault(); Preview.save(); }
                if (e.key === 'Tab') {
                    e.preventDefault();
                    var s = this.selectionStart, end = this.selectionEnd;
                    this.value = this.value.substring(0, s) + '    ' + this.value.substring(end);
                    this.selectionStart = this.selectionEnd = s + 4;
                    updateStatus('modified');
                }
            });
            updateStatus('saved');
        }

        el.appendChild(ta);
        if (state.editMode) ta.focus();
    }

    function renderImage(el) {
        fetch(baseUrl + '/files/' + state.fileId + '/preview', { headers: {'X-Requested-With': 'XMLHttpRequest'} })
            .then(guardAuth)
            .then(function() {
                var img = document.createElement('img');
                img.src = baseUrl + '/files/' + state.fileId + '/preview';
                img.className = 'preview-image';
                img.alt = state.fileName;
                img.onload = function() { el.innerHTML = ''; el.appendChild(img); };
                img.onerror = function() { el.innerHTML = '<p class="preview-error">Failed to load image.</p>'; };
            }).catch(function() {});
    }

    function renderVideo(el) {
        fetch(baseUrl + '/files/' + state.fileId + '/preview', { headers: {'X-Requested-With': 'XMLHttpRequest'} })
            .then(guardAuth)
            .then(function() {
                el.innerHTML = '<video controls class="preview-video" preload="metadata"><source src="' + baseUrl + '/files/' + state.fileId + '/preview"></video>';
            }).catch(function() {});
    }

    function renderAudio(el) {
        fetch(baseUrl + '/files/' + state.fileId + '/preview', { headers: {'X-Requested-With': 'XMLHttpRequest'} })
            .then(guardAuth)
            .then(function() {
                el.innerHTML = '<div class="preview-audio-wrap"><div class="preview-audio-icon"><svg width="64" height="64" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M9 18V5l12-2v13"/><circle cx="6" cy="18" r="3"/><circle cx="18" cy="16" r="3"/></svg></div><audio controls class="preview-audio" preload="metadata"><source src="' + baseUrl + '/files/' + state.fileId + '/preview"></audio></div>';
            }).catch(function() {});
    }

    function renderPdf(el) {
        fetch(baseUrl + '/files/' + state.fileId + '/preview', { headers: {'X-Requested-With': 'XMLHttpRequest'} })
            .then(guardAuth)
            .then(function() {
                el.innerHTML = '<iframe src="' + baseUrl + '/files/' + state.fileId + '/preview" class="preview-pdf"></iframe>';
            }).catch(function() {});
    }

    function toggleEdit() {
        state.editMode = true;
        renderModal();
        loadContent();
    }

    function save() {
        var ta = document.querySelector('.preview-editor');
        if (!ta) return;
        updateStatus('saving');
        fetch(baseUrl + '/files/' + state.fileId + '/content', {
            method: 'PUT',
            headers: {'Content-Type': 'text/plain', 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-Token': csrfToken()},
            body: ta.value
        }).then(guardAuth).then(function(r) { return r.json(); })
        .then(function(data) {
            if (data.success) {
                state.fileSize = ta.value.length;
                state.fileContent = ta.value;
                var sizeEl = document.querySelector('.preview-size');
                if (sizeEl) sizeEl.textContent = formatSize(state.fileSize);
                updateStatus('saved');
                showToast('File saved', 'success');
            } else {
                updateStatus('error');
                showToast('Failed to save: ' + (data.error || 'Unknown error'), 'error');
            }
        }).catch(function() {
            updateStatus('error');
            showToast('Failed to save file', 'error');
        });
    }

    function updateStatus(s) {
        var el = document.getElementById('preview-status');
        if (!el) return;
        var map = {
            saved: ['Saved', 'preview-status preview-status-saved'],
            modified: ['Modified', 'preview-status preview-status-modified'],
            saving: ['Saving...', 'preview-status preview-status-saving'],
            error: ['Error', 'preview-status preview-status-error']
        };
        if (map[s]) { el.textContent = map[s][0]; el.className = map[s][1]; }
    }

    function loadSiblings() {
        fetch(baseUrl + '/files/' + state.fileId + '/siblings', {
            headers: {'X-Requested-With': 'XMLHttpRequest'}
        })
            .then(guardAuth)
            .then(function(r) { return r.json(); })
            .then(function(data) {
                if (data && data.images) {
                    state.siblings = data.images;
                    for (var i = 0; i < state.siblings.length; i++) {
                        if (state.siblings[i].id == state.fileId) { state.currentIndex = i; break; }
                    }
                    updateNav();
                }
            }).catch(function() {});
    }

    function updateNav() {
        var p = document.querySelector('.preview-nav-prev');
        var n = document.querySelector('.preview-nav-next');
        if (p) p.style.display = state.currentIndex > 0 ? '' : 'none';
        if (n) n.style.display = state.currentIndex < state.siblings.length - 1 ? '' : 'none';
    }

    function prev() { if (state.currentIndex > 0) navigateTo(state.currentIndex - 1); }
    function next() { if (state.currentIndex < state.siblings.length - 1) navigateTo(state.currentIndex + 1); }

    function navigateTo(index) {
        var s = state.siblings[index];
        if (!s) return;
        state.fileId = s.id;
        state.fileName = s.name;
        state.fileSize = s.size;
        state.currentIndex = index;
        state.editMode = false;
        state.attributeOnly = isAttributeOnly(state.fileName, state.fileType);
        renderModal();
        loadContent();
        updateNav();
    }

    async function close() {
        if (state.editMode) {
            var ta = document.querySelector('.preview-editor');
            if (ta && ta.value !== state.fileContent) {
                if (!await confirmModal(window.PREVIEW_UNSAVED_TEXT || 'Unsaved changes. Discard?')) return;
            }
        }
        var modal = document.getElementById('preview-modal');
        if (modal) modal.remove();
        var overlay = document.getElementById('modal-overlay');
        if (overlay) overlay.classList.add('hidden');
        var main = document.getElementById('main-content');
        if (main) main.classList.remove('modal-open');
    }

    document.addEventListener('keydown', function(e) {
        if (!document.getElementById('preview-modal')) return;
        if (e.key === 'Escape') { close(); return; }
        if ((e.ctrlKey || e.metaKey) && e.key === 's') { e.preventDefault(); save(); }
        if (!state.editMode) {
            if (e.key === 'ArrowLeft') prev();
            if (e.key === 'ArrowRight') next();
        }
    });

    return { open: open, close: close, prev: prev, next: next, toggleEdit: toggleEdit, save: save };
})();
