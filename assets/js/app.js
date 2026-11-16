/**
 * CDN File Manager & Download Center
 * Handles AJAX folder-by-folder navigation, instant search, sorting,
 * download tracking, clipboard copying, and preview modals.
 */

class CDNFileManager {
    constructor() {
        this.currentFolder = '';
        this.currentView = localStorage.getItem('cdn_view_mode') || 'table';
        this.currentSort = 'date_desc';
        this.currentType = '';
        this.currentSearch = '';
        this.currentData = null;
        this.baseUrl = window.location.origin + window.location.pathname.replace(/\/index\.php$/, '').replace(/\/$/, '');
        this.locale = window.CURRENT_LOCALE || this.getCookie('lang') || 'fa';
        this.isRtl = (typeof window.IS_RTL === 'boolean') ? window.IS_RTL : (this.locale === 'fa');
        this.t = window.APP_LANG || {};

        this.init();
    }

    txt(key, fallback = '') {
        return (this.t && this.t[key]) ? this.t[key] : fallback;
    }

    formatNumber(num) {
        return Number(num || 0).toLocaleString(this.locale === 'fa' ? 'fa-IR' : 'en-US');
    }

    getCookie(name) {
        const match = document.cookie.match(new RegExp('(^| )' + name + '=([^;]+)'));
        return match ? decodeURIComponent(match[2]) : null;
    }

    setCookie(name, value, days = 365) {
        const d = new Date();
        d.setTime(d.getTime() + (days * 24 * 60 * 60 * 1000));
        document.cookie = `${name}=${encodeURIComponent(value)};expires=${d.toUTCString()};path=/;SameSite=Lax`;
    }

    setLanguage(lang) {
        if (lang !== 'fa' && lang !== 'en') return;
        this.setCookie('lang', lang, 365);
        window.location.reload();
    }

    showError(msg) {
        console.error(msg);
    }

    toggleTheme() {
        const isDark = document.documentElement.classList.contains('dark');
        const newTheme = isDark ? 'light' : 'dark';
        if (newTheme === 'dark') {
            document.documentElement.classList.add('dark');
        } else {
            document.documentElement.classList.remove('dark');
        }
        this.setCookie('theme', newTheme, 365);
    }

    init() {
        // Sync theme with cookie if present
        const savedTheme = this.getCookie('theme');
        if (savedTheme === 'dark') {
            document.documentElement.classList.add('dark');
        } else if (savedTheme === 'light') {
            document.documentElement.classList.remove('dark');
        }

        // Read initial folder from URL query
        const params = new URLSearchParams(window.location.search);
        const initFolder = params.get('folder') || '';

        // Bind events
        this.bindEvents();

        // Switch to saved view mode
        this.switchView(this.currentView);

        // Load initial folder
        this.loadFolder(initFolder, false);
    }

    bindEvents() {
        // Global search input
        const globalSearch = document.getElementById('globalSearchInput');
        const inlineSearch = document.getElementById('inlineSearchInput');

        if (globalSearch) {
            globalSearch.addEventListener('input', (e) => {
                this.currentSearch = e.target.value.trim().toLowerCase();
                if (inlineSearch) inlineSearch.value = e.target.value;
                this.filterCurrentItems();
            });
        }

        if (inlineSearch) {
            inlineSearch.addEventListener('input', (e) => {
                this.currentSearch = e.target.value.trim().toLowerCase();
                if (globalSearch) globalSearch.value = e.target.value;
                this.filterCurrentItems();
            });
        }

        // Keyboard shortcuts
        document.addEventListener('keydown', (e) => {
            if ((e.ctrlKey || e.metaKey) && e.key === 'k') {
                e.preventDefault();
                if (globalSearch) globalSearch.focus();
            } else if (e.key === 'Escape') {
                this.closePreviewModal();
            }
        });

        // Browser history navigation (Back / Forward)
        window.addEventListener('popstate', (e) => {
            const folder = (e.state && e.state.folder !== undefined) ? e.state.folder : '';
            this.loadFolder(folder, false);
        });
    }

    /**
     * Loads folder contents via AJAX.
     */
    async loadFolder(folderPath = '', updateHistory = true) {
        this.currentFolder = folderPath;

        // Show loading state
        this.showLoading(true);

        try {
            const response = await fetch(`api.php?action=folder&folder=${encodeURIComponent(folderPath)}&lang=${this.locale}`);
            if (!response.ok) {
                throw new Error('Network response not ok');
            }

            const data = await response.json();
            this.currentData = data;

            // Render view with data
            this.render(data);

            // Update browser history URL
            if (updateHistory) {
                const url = new URL(window.location);
                if (folderPath) {
                    url.searchParams.set('folder', folderPath);
                } else {
                    url.searchParams.delete('folder');
                }
                history.pushState({ folder: folderPath }, '', url);
            }

            // Update sidebar active folder
            this.updateSidebarActive(folderPath);

        } catch (err) {
            console.error(err);
            this.showError('Error loading folder contents.');
        } finally {
            this.showLoading(false);
        }
    }

    /**
     * Renders folder contents, breadcrumbs, and stats.
     */
    render(data) {
        this.renderBreadcrumbs(data.breadcrumbs || []);
        this.renderHeader(data);
        this.renderItems(data.folders || [], data.files || []);
        this.filterCurrentItems();
    }

    renderBreadcrumbs(breadcrumbs) {
        const container = document.getElementById('breadcrumbsContainer');
        if (!container) return;

        let html = '';
        breadcrumbs.forEach((b, index) => {
            const isLast = index === breadcrumbs.length - 1;
            if (index > 0) {
                html += '<span class="text-gray-300 dark:text-gray-600">/</span>';
            }

            if (isLast) {
                html += `<span class="font-medium text-metronic-heading dark:text-white">${this.escapeHtml(b.name)}</span>`;
            } else {
                html += `<button type="button" onclick="app.loadFolder('${this.escapeHtml(b.path)}')" class="hover:text-brand-500 dark:hover:text-brand-400 text-metronic-muted dark:text-gray-400 transition-colors flex items-center gap-1">
                    ${index === 0 ? `<svg class="w-3.5 h-3.5 inline ${this.isRtl ? 'ml-0.5' : 'mr-0.5'} text-gray-400 dark:text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/></svg>` : ''}
                    ${this.escapeHtml(b.name)}
                </button>`;
            }
        });

        container.innerHTML = html;
    }

    renderHeader(data) {
        const titleEl = document.getElementById('currentViewTitle');
        const countBadge = document.getElementById('visibleFilesBadge');
        const backBtnContainer = document.getElementById('backButtonContainer');

        if (titleEl) {
            titleEl.textContent = data.is_root ? this.txt('root_folder_title', 'پوشه اصلی') : `${data.current_folder_name}`;
        }

        if (countBadge) {
            const filesCount = data.files ? data.files.length : 0;
            const foldersCount = data.folders ? data.folders.length : 0;
            countBadge.textContent = `(${this.formatNumber(foldersCount)} ${this.txt('sidebar_folders_count', 'پوشه')}، ${this.formatNumber(filesCount)} ${this.txt('files_count', 'فایل')})`;
        }

        if (backBtnContainer) {
            if (!data.is_root) {
                backBtnContainer.innerHTML = `
                    <button type="button" onclick="app.loadFolder('${this.escapeHtml(data.parent_folder)}')"
                        class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl border border-gray-200 dark:border-[#262B36] bg-white dark:bg-[#1E2129] hover:bg-gray-50 dark:hover:bg-gray-800 text-xs font-semibold text-metronic-heading dark:text-white shadow-xs transition-all">
                        <svg class="w-4 h-4 text-gray-500 dark:text-gray-400 ${this.isRtl ? '' : 'rotate-180'}" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                        </svg>
                        ${this.txt('back_to_parent', 'بازگشت به پوشه قبل')}
                    </button>
                `;
            } else {
                backBtnContainer.innerHTML = '';
            }
        }
    }

    renderItems(folders, files) {
        const tableBody = document.getElementById('filesTableBody');
        const gridContainer = document.getElementById('gridViewContainer');
        const emptyState = document.getElementById('noResultsState');

        const totalItems = (folders ? folders.length : 0) + (files ? files.length : 0);

        if (totalItems === 0) {
            if (tableBody) tableBody.innerHTML = '';
            if (gridContainer) gridContainer.innerHTML = '';
            if (emptyState) emptyState.classList.remove('hidden');
            return;
        }

        if (emptyState) emptyState.classList.add('hidden');

        // ========================
        // 1. RENDER TABLE ROWS
        // ========================
        if (tableBody) {
            let tableHtml = '';

            // Render Folders in Table (Matching file row structure)
            folders.forEach(f => {
                tableHtml += `
                    <tr class="file-item folder-item file-card-transition hover:bg-amber-50/40 dark:hover:bg-amber-950/20 bg-amber-50/10 dark:bg-amber-950/10 group cursor-pointer"
                        onclick="app.loadFolder('${this.escapeHtml(f.path)}')"
                        data-name="${this.escapeHtml(f.name.toLowerCase())}"
                        data-item-type="folder"
                        data-category="folder"
                        data-size="${f.total_size || 0}"
                        data-downloads="0"
                        data-mtime="${f.mtime || 0}"
                        data-path="${this.escapeHtml(f.path)}">
                        
                        <td class="py-3.5 px-4">
                            <div class="flex items-center gap-3">
                                <div class="w-10 h-10 rounded-xl flex items-center justify-center shrink-0 border border-amber-100 dark:border-amber-900/50 bg-amber-50 dark:bg-amber-950/40 text-amber-500 dark:text-amber-400 shadow-xs group-hover:bg-brand-50 dark:group-hover:bg-brand-950/40 group-hover:text-brand-500 dark:group-hover:text-brand-400 group-hover:border-brand-100 dark:group-hover:border-brand-800 transition-colors">
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 7v10a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-6l-2-2H5a2 2 0 00-2 2z"/>
                                    </svg>
                                </div>
                                <div class="min-w-0">
                                    <a href="javascript:void(0)" onclick="app.loadFolder('${this.escapeHtml(f.path)}')"
                                        class="font-bold text-metronic-heading dark:text-white group-hover:text-brand-600 dark:group-hover:text-brand-400 transition-colors block truncate max-w-xs sm:max-w-md ${this.isRtl ? 'text-right' : 'text-left'}"
                                        style="max-width: 20rem;"
                                        title="${this.escapeHtml(f.name)}">
                                        ${this.escapeHtml(f.name)}
                                    </a>
                                    <div class="flex items-center gap-1.5 mt-0.5">
                                        <span class="inline-block px-1.5 py-0.2 rounded text-[11px] font-bold bg-amber-50 dark:bg-amber-950/40 text-amber-700 dark:text-amber-300 border border-amber-200 dark:border-amber-800">
                                            ${this.txt('badge_folder', 'پوشه')}
                                        </span>
                                        <span class="text-xs text-metronic-muted dark:text-gray-400">${this.formatNumber(f.file_count)} ${this.txt('files_count', 'فایل')}</span>
                                    </div>
                                </div>
                            </div>
                        </td>

                        <td class="py-3.5 px-3 whitespace-nowrap">
                            <span class="font-medium text-metronic-heading dark:text-gray-200 block">${f.total_size_formatted}</span>
                        </td>

                        <td class="py-3.5 px-3 whitespace-nowrap">
                            <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-lg bg-amber-50 dark:bg-amber-950/40 text-amber-700 dark:text-amber-300 border border-amber-200 dark:border-amber-800 text-xs font-medium">
                                <svg class="w-3.5 h-3.5 text-amber-500 dark:text-amber-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"/>
                                </svg>
                                ${this.formatNumber(f.file_count)} ${this.txt('files_count', 'فایل')}
                            </span>
                        </td>

                        <td class="py-3.5 px-3 whitespace-nowrap text-xs text-metronic-muted dark:text-gray-400">
                            <span class="block text-metronic-text dark:text-gray-300 font-medium">${f.date_formatted || '—'}</span>
                        </td>

                        <td class="py-3.5 px-4 text-center whitespace-nowrap" onclick="event.stopPropagation()">
                            <button type="button" onclick="app.loadFolder('${this.escapeHtml(f.path)}')"
                                class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl bg-amber-500 hover:bg-amber-600 text-white text-xs font-semibold shadow-xs hover:shadow transition-all"
                                title="${this.txt('open_folder', 'مشاهده فایل‌ها')} - ${this.escapeHtml(f.name)}">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 19a2 2 0 01-2-2V7a2 2 0 012-2h4l2 2h4a2 2 0 012 2v1M5 19h14a2 2 0 002-2v-5a2 2 0 00-2-2H9a2 2 0 00-2 2v5a2 2 0 01-2 2z"/>
                                </svg>
                                ${this.txt('open_folder', 'مشاهده فایل‌ها')}
                            </button>
                        </td>
                    </tr>
                `;
            });

            // Render Files in Table
            files.forEach(file => {
                const fullDownloadUrl = `${this.baseUrl}/${file.download_url}`;
                tableHtml += `
                    <tr class="file-item file-card-transition hover:bg-gray-50/80 dark:hover:bg-gray-800/40 group"
                        data-name="${this.escapeHtml(file.name.toLowerCase())}"
                        data-item-type="file"
                        data-category="${this.escapeHtml(file.category)}"
                        data-size="${file.size}"
                        data-downloads="${file.download_count}"
                        data-mtime="${file.mtime}"
                        data-path="${this.escapeHtml(file.path)}">
                        
                        <td class="py-3.5 px-4">
                            <div class="flex items-center gap-3">
                                <div class="w-10 h-10 rounded-xl flex items-center justify-center shrink-0 border border-gray-100 dark:border-[#262B36] bg-white dark:bg-[#15171C] shadow-xs">
                                    ${this.renderFileIcon(file.icon)}
                                </div>
                                <div class="min-w-0">
                                    <a href="${file.download_url}" onclick="app.trackDownload('${this.escapeHtml(file.path)}')"
                                        class="font-semibold text-metronic-heading dark:text-white hover:text-brand-500 dark:hover:text-brand-400 transition-colors block truncate max-w-xs sm:max-w-md ${this.isRtl ? 'text-right' : 'text-left'}"
                                        style="max-width: 20rem;"
                                        title="${this.escapeHtml(file.name)}">
                                        ${this.escapeHtml(file.name)}
                                    </a>
                                    <div class="flex items-center gap-1.5 mt-0.5">
                                        <span class="text-xs text-metronic-muted dark:text-gray-400">${this.escapeHtml(file.category_label)}</span>
                                    </div>
                                </div>
                            </div>
                        </td>

                        <td class="py-3.5 px-3 whitespace-nowrap">
                            <span class="font-medium text-metronic-heading dark:text-gray-200 block">${file.size_formatted}</span>
                        </td>

                        <td class="py-3.5 px-3 whitespace-nowrap">
                            <div class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg bg-blue-50/70 dark:bg-blue-950/30 border border-blue-100 dark:border-blue-900/40 text-brand-600 dark:text-brand-400 font-semibold text-xs">
                                <svg class="w-3.5 h-3.5 text-brand-500 dark:text-brand-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/>
                                </svg>
                                <span class="download-counter-span" data-path="${this.escapeHtml(file.path)}" data-count="${file.download_count}">
                                    ${this.formatNumber(file.download_count)}
                                </span>
                                <span class="font-normal text-metronic-muted dark:text-gray-400">${this.txt('downloads_count', 'بار')}</span>
                            </div>
                        </td>

                        <td class="py-3.5 px-3 whitespace-nowrap text-xs text-metronic-muted dark:text-gray-400">
                            <span class="block text-metronic-text dark:text-gray-300 font-medium">${file.date_formatted}</span>
                        </td>

                        <td class="py-3.5 px-4 text-center whitespace-nowrap">
                            <div class="flex items-center justify-center gap-1.5">
                                <a href="${file.download_url}" onclick="app.trackDownload('${this.escapeHtml(file.path)}')"
                                    class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl bg-brand-500 hover:bg-brand-600 text-white text-xs font-semibold shadow-sm hover:shadow transition-all"
                                    title="${this.txt('download_direct', 'دانلود مستقیم فایل')}">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/>
                                    </svg>
                                    ${this.txt('download_file', 'دانلود')}
                                </a>

                                <button type="button" onclick="app.copyDownloadLink('${fullDownloadUrl}')"
                                    class="p-1.5 rounded-xl border border-gray-200 dark:border-gray-700 text-gray-500 dark:text-gray-400 hover:text-brand-600 dark:hover:text-brand-400 hover:bg-brand-50 dark:hover:bg-gray-800 hover:border-brand-200 dark:hover:border-brand-800 transition-all"
                                    title="${this.txt('copy_link', 'کپی لینک مستقیم دانلود')}">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 5H6a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2v-1M8 5a2 2 0 002 2h2a2 2 0 002-2M8 5a2 2 0 012-2h2a2 2 0 012 2m0 0h2a2 2 0 012 2v3m2 4H10m0 0l3-3m-3 3l3 3"/>
                                    </svg>
                                </button>

                                ${file.can_preview ? `
                                    <button type="button" onclick="app.openPreviewModal('${this.escapeHtml(file.name)}', '${file.preview_url}', '${file.extension}')"
                                        class="p-1.5 rounded-xl border border-gray-200 dark:border-gray-700 text-gray-500 dark:text-gray-400 hover:text-purple-600 dark:hover:text-purple-400 hover:bg-purple-50 dark:hover:bg-gray-800 hover:border-purple-200 dark:hover:border-purple-800 transition-all"
                                        title="${this.txt('preview_file', 'پیش‌نمایش آنلاین فایل')}">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                                        </svg>
                                    </button>
                                ` : ''}
                            </div>
                        </td>
                    </tr>
                `;
            });

            tableBody.innerHTML = tableHtml;
        }

        // ========================
        // 2. RENDER GRID CARDS
        // ========================
        if (gridContainer) {
            let gridHtml = '';

            // Render Folders in Grid (Matching file card structure)
            folders.forEach(f => {
                gridHtml += `
                    <div class="file-item folder-item bg-white dark:bg-[#1E2129] rounded-2xl border border-metronic-border dark:border-[#262B36] p-4 shadow-sm hover:shadow-md hover:border-amber-400/60 dark:hover:border-amber-500/60 transition-all flex flex-col justify-between cursor-pointer group"
                        onclick="app.loadFolder('${this.escapeHtml(f.path)}')"
                        data-name="${this.escapeHtml(f.name.toLowerCase())}"
                        data-item-type="folder"
                        data-category="folder"
                        data-size="${f.total_size || 0}"
                        data-downloads="0"
                        data-mtime="${f.mtime || 0}"
                        data-path="${this.escapeHtml(f.path)}">
                        
                        <div>
                            <div class="flex items-start justify-between gap-3 mb-3">
                                <div class="w-12 h-12 rounded-2xl bg-amber-50 dark:bg-amber-950/40 text-amber-500 dark:text-amber-400 border border-amber-100 dark:border-amber-900/50 flex items-center justify-center shrink-0 group-hover:bg-amber-500 group-hover:text-white transition-all">
                                    <svg class="w-7 h-7" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 7v10a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-6l-2-2H5a2 2 0 00-2 2z"/>
                                    </svg>
                                </div>
                                <div class="flex items-center gap-1.5">
                                    <span class="px-2 py-0.5 rounded text-xs font-bold bg-amber-50 dark:bg-amber-950/40 text-amber-700 dark:text-amber-300 border border-amber-200 dark:border-amber-800">
                                        ${this.txt('badge_folder', 'پوشه')}
                                    </span>
                                </div>
                            </div>

                            <a href="javascript:void(0)" onclick="app.loadFolder('${this.escapeHtml(f.path)}')"
                                class="font-bold text-sm text-metronic-heading dark:text-white group-hover:text-amber-600 dark:group-hover:text-amber-400 transition-colors block line-clamp-2 ${this.isRtl ? 'text-right' : 'text-left'} mb-2"
                                title="${this.escapeHtml(f.name)}">
                                ${this.escapeHtml(f.name)}
                            </a>

                            <div class="text-xs text-metronic-muted dark:text-gray-400 space-y-1 mb-4">
                                <div class="flex items-center justify-between">
                                    <span>${this.txt('folder_size', 'حجم کل پوشه:')}</span>
                                    <span class="font-medium text-metronic-heading dark:text-gray-200">${f.total_size_formatted}</span>
                                </div>
                                <div class="flex items-center justify-between">
                                    <span>${this.txt('folder_contents_label', 'محتویات:')}</span>
                                    <span class="font-semibold text-amber-600 dark:text-amber-400 inline-flex items-center gap-1">
                                        ${this.formatNumber(f.file_count)} ${this.txt('files_inside_folder', 'فایل داخل پوشه')}
                                    </span>
                                </div>
                                <div class="flex items-center justify-between">
                                    <span>${this.txt('last_modified', 'آخرین تغییر:')}</span>
                                    <span class="text-gray-500 dark:text-gray-400">${f.date_formatted || '—'}</span>
                                </div>
                            </div>
                        </div>

                        <div class="pt-3 border-t border-gray-100 dark:border-[#262B36]" onclick="event.stopPropagation()">
                            <button type="button" onclick="app.loadFolder('${this.escapeHtml(f.path)}')"
                                class="w-full flex items-center justify-center gap-1.5 py-2 px-3 rounded-xl bg-amber-500 hover:bg-amber-600 text-white text-xs font-bold transition-all shadow-xs hover:shadow">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 19a2 2 0 01-2-2V7a2 2 0 012-2h4l2 2h4a2 2 0 012 2v1M5 19h14a2 2 0 002-2v-5a2 2 0 00-2-2H9a2 2 0 00-2 2v5a2 2 0 01-2 2z"/>
                                </svg>
                                ${this.txt('open_folder', 'مشاهده فایل‌ها')}
                            </button>
                        </div>
                    </div>
                `;
            });

            // Render Files in Grid
            files.forEach(file => {
                const fullDownloadUrl = `${this.baseUrl}/${file.download_url}`;
                gridHtml += `
                    <div class="file-item bg-white dark:bg-[#1E2129] rounded-2xl border border-metronic-border dark:border-[#262B36] p-4 shadow-sm hover:shadow-md transition-all flex flex-col justify-between"
                        data-name="${this.escapeHtml(file.name.toLowerCase())}"
                        data-item-type="file"
                        data-category="${this.escapeHtml(file.category)}"
                        data-size="${file.size}"
                        data-downloads="${file.download_count}"
                        data-mtime="${file.mtime}"
                        data-path="${this.escapeHtml(file.path)}">
                        
                        <div>
                            <div class="flex items-start justify-between gap-3 mb-3">
                                <div class="w-12 h-12 rounded-2xl bg-gray-50 dark:bg-[#15171C] border border-gray-100 dark:border-[#262B36] flex items-center justify-center shrink-0">
                                    ${this.renderFileIcon(file.icon, 'w-7 h-7')}
                                </div>
                                <div class="flex items-center gap-1.5">
                                    <span class="px-2 py-0.5 rounded text-xs font-mono font-bold ${file.badge_class}">
                                        .${this.escapeHtml(file.extension)}
                                    </span>
                                </div>
                            </div>

                            <a href="${file.download_url}" onclick="app.trackDownload('${this.escapeHtml(file.path)}')"
                                class="font-bold text-sm text-metronic-heading dark:text-white hover:text-brand-500 dark:hover:text-brand-400 transition-colors block line-clamp-2 ${this.isRtl ? 'text-right' : 'text-left'} mb-2"
                                title="${this.escapeHtml(file.name)}">
                                ${this.escapeHtml(file.name)}
                            </a>

                            <div class="text-xs text-metronic-muted dark:text-gray-400 space-y-1 mb-4">
                                <div class="flex items-center justify-between">
                                    <span>${this.txt('file_size', 'حجم فایل:')}</span>
                                    <span class="font-medium text-metronic-heading dark:text-gray-200">${file.size_formatted}</span>
                                </div>
                                <div class="flex items-center justify-between">
                                    <span>${this.txt('downloads_label', 'دفعات دانلود:')}</span>
                                    <span class="font-semibold text-brand-600 dark:text-brand-400 inline-flex items-center gap-1">
                                        <span class="download-counter-span" data-path="${this.escapeHtml(file.path)}" data-count="${file.download_count}">
                                            ${this.formatNumber(file.download_count)}
                                        </span>
                                        ${this.txt('downloads_count', 'بار')}
                                    </span>
                                </div>
                                <div class="flex items-center justify-between">
                                    <span>${this.txt('last_updated', 'آخرین بروزرسانی:')}</span>
                                    <span class="text-gray-500 dark:text-gray-400">${file.date_formatted}</span>
                                </div>
                            </div>
                        </div>

                        <div class="flex items-center gap-2 pt-3 border-t border-gray-100 dark:border-[#262B36]">
                            <a href="${file.download_url}" onclick="app.trackDownload('${this.escapeHtml(file.path)}')"
                                class="flex-1 flex items-center justify-center gap-1.5 py-2 px-3 rounded-xl bg-brand-500 hover:bg-brand-600 text-white text-xs font-bold transition-all shadow-sm">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/>
                                </svg>
                                ${this.txt('download_file', 'دانلود فایل')}
                            </a>

                            <button type="button" onclick="app.copyDownloadLink('${fullDownloadUrl}')"
                                class="p-2 rounded-xl border border-gray-200 dark:border-gray-700 text-gray-500 dark:text-gray-400 hover:text-brand-600 dark:hover:text-brand-400 hover:bg-brand-50 dark:hover:bg-gray-800 hover:border-brand-200 dark:hover:border-brand-800 transition-all"
                                title="${this.txt('copy_link', 'کپی لینک مستقیم دانلود')}">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 5H6a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2v-1M8 5a2 2 0 002 2h2a2 2 0 002-2M8 5a2 2 0 012-2h2a2 2 0 012 2m0 0h2a2 2 0 012 2v3m2 4H10m0 0l3-3m-3 3l3 3"/>
                                </svg>
                            </button>

                            ${file.can_preview ? `
                                <button type="button" onclick="app.openPreviewModal('${this.escapeHtml(file.name)}', '${file.preview_url}', '${file.extension}')"
                                    class="p-2 rounded-xl border border-gray-200 dark:border-gray-700 text-gray-500 dark:text-gray-400 hover:text-purple-600 dark:hover:text-purple-400 hover:bg-purple-50 dark:hover:bg-gray-800 hover:border-purple-200 dark:hover:border-purple-800 transition-all"
                                    title="${this.txt('preview_file', 'پیش‌نمایش آنلاین فایل')}">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                                    </svg>
                                </button>
                            ` : ''}
                        </div>
                    </div>
                `;
            });

            gridContainer.innerHTML = gridHtml;
        }

        this.applySort(this.currentSort);
    }

    /**
     * Filter items by type and search query.
     */
    filterCurrentItems() {
        const items = document.querySelectorAll('.file-item');
        let visibleCount = 0;

        items.forEach(item => {
            const name = item.getAttribute('data-name') || '';
            const category = item.getAttribute('data-category') || '';
            const isFolder = item.classList.contains('folder-item');

            let matchesType = true;
            if (this.currentType) {
                matchesType = (!isFolder) && (category === this.currentType);
            }

            const matchesSearch = (!this.currentSearch) || (name.includes(this.currentSearch));

            if (matchesType && matchesSearch) {
                item.classList.remove('hidden');
                visibleCount++;
            } else {
                item.classList.add('hidden');
            }
        });

        // Update badge
        const badge = document.getElementById('visibleFilesBadge');
        if (badge) {
            const actualCount = Math.floor(visibleCount / 2);
            badge.textContent = ``;
        }

        const emptyState = document.getElementById('noResultsState');
        if (visibleCount === 0) {
            if (emptyState) emptyState.classList.remove('hidden');
        } else {
            if (emptyState) emptyState.classList.add('hidden');
        }
    }

    filterByType(category) {
        this.currentType = category;

        document.querySelectorAll('.type-btn').forEach(btn => {
            const btnType = btn.getAttribute('data-type');
            if (btnType === category) {
                btn.classList.add('bg-brand-50', 'dark:bg-brand-950/50', 'text-brand-600', 'dark:text-brand-400', 'border-brand-200', 'dark:border-brand-800');
                btn.classList.remove('border-gray-200', 'dark:border-gray-700', 'text-gray-600', 'dark:text-gray-300', 'hover:bg-gray-50', 'dark:hover:bg-gray-800');
            } else {
                btn.classList.remove('bg-brand-50', 'dark:bg-brand-950/50', 'text-brand-600', 'dark:text-brand-400', 'border-brand-200', 'dark:border-brand-800');
                btn.classList.add('border-gray-200', 'dark:border-gray-700', 'text-gray-600', 'dark:text-gray-300', 'hover:bg-gray-50', 'dark:hover:bg-gray-800');
            }
        });

        this.filterCurrentItems();
    }

    applySort(sortKey) {
        this.currentSort = sortKey;
        const tableBody = document.getElementById('filesTableBody');
        const gridContainer = document.getElementById('gridViewContainer');

        if (!tableBody && !gridContainer) return;

        const sortFn = (a, b) => {
            const isFolderA = a.classList.contains('folder-item');
            const isFolderB = b.classList.contains('folder-item');
            if (isFolderA && !isFolderB) return -1;
            if (!isFolderA && isFolderB) return 1;

            switch (sortKey) {
                case 'downloads_desc':
                    return parseInt(b.getAttribute('data-downloads') || 0) - parseInt(a.getAttribute('data-downloads') || 0);
                case 'size_desc':
                    return parseInt(b.getAttribute('data-size') || 0) - parseInt(a.getAttribute('data-size') || 0);
                case 'size_asc':
                    return parseInt(a.getAttribute('data-size') || 0) - parseInt(b.getAttribute('data-size') || 0);
                case 'name_asc':
                    return (a.getAttribute('data-name') || '').localeCompare(b.getAttribute('data-name') || '');
                case 'date_desc':
                default:
                    return parseInt(b.getAttribute('data-mtime') || 0) - parseInt(a.getAttribute('data-mtime') || 0);
            }
        };

        if (tableBody) {
            const rows = Array.from(tableBody.querySelectorAll('tr.file-item'));
            rows.sort(sortFn);
            rows.forEach(r => tableBody.appendChild(r));
        }

        if (gridContainer) {
            const cards = Array.from(gridContainer.querySelectorAll('div.file-item'));
            cards.sort(sortFn);
            cards.forEach(c => gridContainer.appendChild(c));
        }
    }

    switchView(mode) {
        this.currentView = mode;
        localStorage.setItem('cdn_view_mode', mode);

        const tableContainer = document.getElementById('tableViewContainer');
        const gridContainer = document.getElementById('gridViewContainer');
        const btnTable = document.getElementById('btnViewTable');
        const btnGrid = document.getElementById('btnViewGrid');

        if (mode === 'grid') {
            if (tableContainer) tableContainer.classList.add('hidden');
            if (gridContainer) gridContainer.classList.remove('hidden');
            if (btnGrid) {
                btnGrid.classList.add('text-brand-600', 'dark:text-brand-400', 'bg-white', 'dark:bg-[#1E2129]', 'shadow-xs');
                btnGrid.classList.remove('text-gray-400', 'dark:text-gray-500');
            }
            if (btnTable) {
                btnTable.classList.remove('text-brand-600', 'dark:text-brand-400', 'bg-white', 'dark:bg-[#1E2129]', 'shadow-xs');
                btnTable.classList.add('text-gray-400', 'dark:text-gray-500');
            }
        } else {
            if (gridContainer) gridContainer.classList.add('hidden');
            if (tableContainer) tableContainer.classList.remove('hidden');
            if (btnTable) {
                btnTable.classList.add('text-brand-600', 'dark:text-brand-400', 'bg-white', 'dark:bg-[#1E2129]', 'shadow-xs');
                btnTable.classList.remove('text-gray-400', 'dark:text-gray-500');
            }
            if (btnGrid) {
                btnGrid.classList.remove('text-brand-600', 'dark:text-brand-400', 'bg-white', 'dark:bg-[#1E2129]', 'shadow-xs');
                btnGrid.classList.add('text-gray-400', 'dark:text-gray-500');
            }
        }
    }

    updateSidebarActive(folderPath) {
        document.querySelectorAll('.sidebar-folder-btn').forEach(btn => {
            const btnFolder = btn.getAttribute('data-folder');
            if (btnFolder === folderPath) {
                btn.classList.add('bg-brand-50', 'dark:bg-brand-950/50', 'text-brand-600', 'dark:text-brand-400', 'font-semibold', 'shadow-xs');
                btn.classList.remove('text-metronic-text', 'dark:text-gray-300', 'hover:bg-gray-50', 'dark:hover:bg-gray-800/60');
            } else {
                btn.classList.remove('bg-brand-50', 'dark:bg-brand-950/50', 'text-brand-600', 'dark:text-brand-400', 'font-semibold', 'shadow-xs');
                btn.classList.add('text-metronic-text', 'dark:text-gray-300', 'hover:bg-gray-50', 'dark:hover:bg-gray-800/60');
            }
        });
    }

    showLoading(show) {
        const loadingOverlay = document.getElementById('folderLoadingOverlay');
        if (loadingOverlay) {
            if (show) {
                loadingOverlay.classList.remove('hidden');
            } else {
                loadingOverlay.classList.add('hidden');
            }
        }
    }

    copyDownloadLink(url) {
        if (navigator.clipboard && window.isSecureContext) {
            navigator.clipboard.writeText(url).then(() => this.showToast());
        } else {
            const textArea = document.createElement('textarea');
            textArea.value = url;
            textArea.style.position = 'fixed';
            textArea.style.opacity = '0';
            document.body.appendChild(textArea);
            textArea.select();
            try {
                document.execCommand('copy');
                this.showToast();
            } catch (err) { }
            document.body.removeChild(textArea);
        }
    }

    showToast() {
        const toast = document.getElementById('copyToast');
        if (!toast) return;

        toast.classList.add('toast-visible');
        setTimeout(() => {
            toast.classList.remove('toast-visible');
        }, 3000);
    }

    parsePersianInt(str) {
        if (!str) return 0;
        const persianDigits = [/۰/g, /۱/g, /۲/g, /۳/g, /۴/g, /۵/g, /۶/g, /۷/g, /۸/g, /۹/g];
        const arabicDigits = [/٠/g, /١/g, /٢/g, /٣/g, /٤/g, /٥/g, /٦/g, /٧/g, /٨/g, /٩/g];
        let cleaned = String(str).replace(/[,\s،]/g, '');
        for (let i = 0; i < 10; i++) {
            cleaned = cleaned.replace(persianDigits[i], i).replace(arabicDigits[i], i);
        }
        const val = parseInt(cleaned, 10);
        return isNaN(val) ? 0 : val;
    }

    trackDownload(filePath) {
        // Increment locally in UI immediately for fast visual feedback
        const spans = document.querySelectorAll(`.download-counter-span[data-path="${filePath}"]`);
        spans.forEach(span => {
            let current = parseInt(span.getAttribute('data-count'), 10);
            if (isNaN(current)) {
                current = this.parsePersianInt(span.textContent);
            }
            const next = current + 1;
            span.setAttribute('data-count', next);
            span.textContent = this.formatNumber(next);
        });

        // Update data-downloads attribute on the parent rows/cards for instant accurate sorting
        const items = document.querySelectorAll(`.file-item[data-path="${filePath}"]`);
        items.forEach(item => {
            let current = parseInt(item.getAttribute('data-downloads') || 0, 10);
            item.setAttribute('data-downloads', current + 1);
        });

        // KPI counter update
        const totalEl = document.getElementById('kpiTotalDownloads');
        if (totalEl) {
            let currentTotal = parseInt(totalEl.getAttribute('data-count'), 10);
            if (isNaN(currentTotal)) {
                currentTotal = this.parsePersianInt(totalEl.textContent);
            }
            const nextTotal = currentTotal + 1;
            totalEl.setAttribute('data-count', nextTotal);
            totalEl.textContent = this.formatNumber(nextTotal);
        }
        // Notice: The actual database increment is handled by download.php when serving the file
    }

    openPreviewModal(fileName, previewUrl, ext) {
        const modal = document.getElementById('previewModal');
        const title = document.getElementById('previewModalTitle');
        const content = document.getElementById('previewModalContent');
        const downloadBtn = document.getElementById('previewDownloadBtn');

        if (!modal || !title || !content || !downloadBtn) return;

        title.textContent = `${this.txt('modal_preview_title', 'پیش‌نمایش')}: ${fileName}`;
        downloadBtn.href = previewUrl.replace('&preview=1', '');
        content.innerHTML = `<div class="text-metronic-muted text-xs">${this.txt('modal_preview_loading', 'در حال بارگذاری پیش‌نمایش...')}</div>`;

        modal.classList.remove('hidden');

        ext = ext.toLowerCase();
        if (['png', 'jpg', 'jpeg', 'gif', 'svg', 'webp'].includes(ext)) {
            content.innerHTML = `<img src="${previewUrl}" alt="${fileName}" class="max-h-[70vh] max-w-full rounded-xl object-contain shadow-sm border border-gray-100">`;
        } else if (ext === 'pdf') {
            content.innerHTML = `<iframe src="${previewUrl}" class="w-full h-[65vh] rounded-xl border border-gray-200"></iframe>`;
        } else if (['mp4', 'webm'].includes(ext)) {
            content.innerHTML = `<video controls src="${previewUrl}" class="max-h-[65vh] w-full rounded-xl shadow-sm"></video>`;
        } else if (['mp3', 'wav'].includes(ext)) {
            content.innerHTML = `<div class="p-8 text-center"><audio controls src="${previewUrl}" class="w-full max-w-md mx-auto"></audio></div>`;
        } else {
            content.innerHTML = `<div class="text-center p-8"><p class="text-sm text-metronic-muted mb-4">${this.txt('modal_preview_unsupported', 'امکان پیش‌نمایش مستقیم این نوع فایل در مرورگر وجود ندارد.')}</p><a href="${downloadBtn.href}" class="px-4 py-2 rounded-xl bg-brand-500 text-white text-xs font-bold">${this.txt('modal_download', 'دانلود مستقیم فایل')}</a></div>`;
        }
    }

    closePreviewModal() {
        const modal = document.getElementById('previewModal');
        const content = document.getElementById('previewModalContent');
        if (content) content.innerHTML = '';
        if (modal) modal.classList.add('hidden');
    }

    renderFileIcon(iconType, classes = 'w-6 h-6') {
        switch (iconType) {
            case 'pdf':
                return `<svg class="${classes} text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 13h6M9 17h3"/></svg>`;
            case 'word':
                return `<svg class="${classes} text-blue-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>`;
            case 'excel':
                return `<svg class="${classes} text-emerald-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M3 14h18m-9-4v8m-7 4h14a2 2 0 002-2V6a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>`;
            case 'archive':
                return `<svg class="${classes} text-amber-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 8h14M5 8a2 2 0 110-4h14a2 2 0 110 4M5 8v10a2 2 0 002 2h10a2 2 0 002-2V8m-9 4h4"/></svg>`;
            case 'software':
                return `<svg class="${classes} text-purple-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/></svg>`;
            case 'image':
                return `<svg class="${classes} text-cyan-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>`;
            case 'video':
                return `<svg class="${classes} text-rose-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 10l4.553-2.276A1 1 0 0121 8.618v6.764a1 1 0 01-1.447.894L15 14M5 18h8a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z"/></svg>`;
            case 'audio':
                return `<svg class="${classes} text-pink-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19V6l12-3v13M9 19c0 1.105-1.343 2-3 2s-3-.895-3-2 1.343-2 3-2 3 .895 3 2zm12-3c0 1.105-1.343 2-3 2s-3-.895-3-2 1.343-2 3-2 3 .895 3 2zM9 10l12-3"/></svg>`;
            case 'code':
                return `<svg class="${classes} text-slate-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 20l4-16m4 4l4 4-4 4M6 16l-4-4 4-4"/></svg>`;
            default:
                return `<svg class="${classes} text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z"/></svg>`;
        }
    }

    escapeHtml(str) {
        if (!str) return '';
        return String(str)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }
}

// Global instance
const app = new CDNFileManager();

