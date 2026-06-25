class FolderTree {
    constructor(container, options = {}) {
        this.container = typeof container === 'string' ? document.querySelector(container) : container;
        this.onSelect = options.onSelect || function() {};
        this.currentFolder = options.currentFolder || null;
        this.expandedFolders = new Set();
        this.loadRoot();
    }

    loadRoot() {
        this.loadChildren(null, this.container);
    }

    loadChildren(parentId, parentElement) {
        const base = typeof BASE !== 'undefined' ? BASE : '';
        fetch(base + '/api/files?folder=' + (parentId || ''), {
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        })
        .then(guardAuth)
        .then(r => r.json())
        .then(data => {
            const folders = data.files.filter(f => f.is_folder);
            folders.sort((a, b) => a.name.localeCompare(b.name));

            folders.forEach(folder => {
                const item = this.createFolderItem(folder);
                parentElement.appendChild(item);
            });
        });
    }

    createFolderItem(folder) {
        const item = document.createElement('div');
        item.className = 'tree-item';
        item.dataset.id = folder.id;

        const row = document.createElement('div');
        row.className = 'tree-row' + (this.currentFolder == folder.id ? ' active' : '');
        row.innerHTML = '<span class="tree-toggle">▶</span>' +
            '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">' +
            '<path d="M22 19a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5l2 3h9a2 2 0 0 1 2 2z"></path></svg>' +
            '<span class="tree-label">' + this.escapeHtml(folder.name) + '</span>';

        const children = document.createElement('div');
        children.className = 'tree-children hidden';

        row.addEventListener('click', (e) => {
            e.stopPropagation();
            if (children.children.length === 0) {
                this.loadChildren(folder.id, children);
            }
            children.classList.toggle('hidden');
            const toggle = row.querySelector('.tree-toggle');
            toggle.textContent = children.classList.contains('hidden') ? '▶' : '▼';
            this.onSelect(folder.id);
        });

        item.appendChild(row);
        item.appendChild(children);
        return item;
    }

    escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }
}
