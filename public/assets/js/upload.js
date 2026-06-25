document.addEventListener('DOMContentLoaded', function() {
    const uploadBtn = document.getElementById('upload-btn');
    const uploadZone = document.getElementById('upload-zone');
    const fileInput = document.getElementById('file-input');
    const uploadProgress = document.getElementById('upload-progress');

    if (uploadBtn) {
        uploadBtn.addEventListener('click', function() {
            uploadZone.classList.toggle('hidden');
        });
    }

    if (uploadZone) {
        uploadZone.addEventListener('click', function() {
            fileInput.click();
        });

        uploadZone.addEventListener('dragover', function(e) {
            e.preventDefault();
            this.classList.add('dragover');
        });

        uploadZone.addEventListener('dragleave', function() {
            this.classList.remove('dragover');
        });

        uploadZone.addEventListener('drop', function(e) {
            e.preventDefault();
            this.classList.remove('dragover');
            uploadFiles(e.dataTransfer.files);
        });
    }

    if (fileInput) {
        fileInput.addEventListener('change', function() {
            uploadFiles(this.files);
        });
    }

    function getBase() {
        if (typeof BASE !== 'undefined') return BASE;
        var m = document.querySelector('meta[name="base-url"]');
        return m ? m.getAttribute('content') : '';
    }

    function getFolderId() {
        var zone = document.getElementById('upload-zone');
        return zone ? zone.getAttribute('data-folder-id') || '' : '';
    }

    function uploadFiles(files) {
        if (!files.length) return;

        uploadProgress.classList.remove('hidden');
        uploadProgress.innerHTML = '';

        var total = files.length;
        var completed = 0;
        var uploadQueue = Array.from(files);
        var maxConcurrent = 3;
        var active = 0;
        var base = getBase();

        function processQueue() {
            while (active < maxConcurrent && uploadQueue.length > 0) {
                var file = uploadQueue.shift();
                active++;
                uploadSingle(file, function() {
                    active--;
                    completed++;
                    if (completed === total) {
                        var hasError = uploadProgress.querySelector('[style*="danger"]');
                        if (!hasError) {
                            var msgs = typeof TRANSLATIONS !== 'undefined' ? TRANSLATIONS : {};
                            var msg = total === 1 ? (msgs.upload_success || 'File uploaded') : (msgs.upload_success_plural || total + ' files uploaded').replace('{count}', total);
                            showToast(msg, 'success');
                        }
                        setTimeout(function() { location.reload(); }, 1500);
                    }
                    processQueue();
                });
            }
        }

        processQueue();
    }

    function uploadSingle(file, callback) {
        var formData = new FormData();
        formData.append('file', file);
        formData.append('folder_id', getFolderId());

        var progressItem = document.createElement('div');
        progressItem.className = 'upload-item';
        progressItem.innerHTML = '<div class="upload-item-name">' + escapeHtml(file.name) + '</div>' +
            '<div class="progress-bar"><div class="progress-fill" style="width: 0%"></div></div>';
        uploadProgress.appendChild(progressItem);

        var xhr = new XMLHttpRequest();
        xhr.open('POST', getBase() + '/files/upload', true);
        xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
        xhr.setRequestHeader('X-CSRF-Token', csrfToken());

        xhr.upload.onprogress = function(e) {
            if (e.lengthComputable) {
                var pct = Math.round(e.loaded / e.total * 100);
                progressItem.querySelector('.progress-fill').style.width = pct + '%';
            }
        };

        xhr.onload = function() {
            if (xhr.status === 401) {
                handleAuthError();
                callback();
                return;
            }
            if (xhr.status === 200) {
                progressItem.querySelector('.progress-fill').style.width = '100%';
                progressItem.querySelector('.progress-fill').style.background = 'var(--success)';
            } else {
                progressItem.querySelector('.progress-fill').style.background = 'var(--danger)';
                try {
                    var resp = JSON.parse(xhr.responseText);
                    showToast(resp.message || 'Upload failed', 'error');
                } catch(e) {
                    showToast('Upload failed', 'error');
                }
            }
            callback();
        };

        xhr.onerror = function() {
            progressItem.querySelector('.progress-fill').style.background = 'var(--danger)';
            showToast('Upload error', 'error');
            callback();
        };

        xhr.send(formData);
    }

    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }
});
