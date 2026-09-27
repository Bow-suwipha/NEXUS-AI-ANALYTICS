// --- 1. Fetch Interceptor (แนบ JWT อัตโนมัติ) ---
const originalFetch = window.fetch;
window.fetch = async function () {
    let [resource, config] = arguments;
    if (!config) config = {};
    if (!config.headers) config.headers = {};

    const token = localStorage.getItem('nexus_token');
    if (token && typeof resource === 'string' && resource.includes('/api/')) {
        if (config.headers instanceof Headers) {
            config.headers.set('Authorization', `Bearer ${token}`);
        } else {
            config.headers['Authorization'] = `Bearer ${token}`;
        }
    }

    const response = await originalFetch(resource, config);
    if (response.status === 401 && !window.location.pathname.includes('login.html')) {
        localStorage.removeItem('nexus_token');
        window.location.href = 'login.html';
    }
    return response;
};

// --- Global Cache ---
const pageCache = {};
let isInitialLoad = true;

document.addEventListener("DOMContentLoaded", () => {

    const loadComponent = async (id, file, callback) => {
        const el = document.getElementById(id);
        if (el) {
            try {
                const response = await fetch(file);
                if (response.ok) {
                    el.innerHTML = await response.text();
                    if (callback) callback();
                }
            } catch (error) {
                console.error(`Error loading ${file}:`, error);
            }
        }
    };

    const updateActiveNav = () => {
        let currentPath = window.location.pathname.split('/').pop();
        if (!currentPath || currentPath === "") currentPath = "overview.html";

        const links = document.querySelectorAll('#nav-tabs .nav-link');
        links.forEach(link => {
            const href = link.getAttribute('href');
            link.className = "nav-link px-space-sm py-1.5 rounded-lg transition-all text-sm";
            if (href === currentPath) {
                link.classList.add("bg-primary", "text-on-primary", "font-semibold", "shadow-sm");
                link.setAttribute("aria-current", "page");
            } else {
                link.classList.add("text-on-surface-variant", "hover:text-on-surface", "hover:bg-surface-container-low");
                link.removeAttribute("aria-current");
            }
        });
    };

    // --- 2. Minimal SPA Router + Caching ---
    const loadPageContent = async (url, pushState = true) => {
        try {
            document.body.style.cursor = 'wait';
            let htmlString = "";

            // ใช้ Cache ถ้ามีข้อมูลอยู่แล้ว (ทำให้สลับหน้าทันที)
            if (pageCache[url]) {
                htmlString = pageCache[url];
            }

            // Fetch ข้อมูลใหม่มาอัปเดต Cache เบื้องหลัง
            const response = await fetch(url);
            if (response.ok) {
                htmlString = await response.text();
                pageCache[url] = htmlString; // อัปเดต Cache
            }

            if (!htmlString) return;

            const parser = new DOMParser();
            const doc = parser.parseFromString(htmlString, 'text/html');

            const newMain = doc.querySelector('main');
            const currentMain = document.querySelector('main');

            if (newMain && currentMain) {
                // สลับเฉพาะเนื้อหาใน <main>
                currentMain.parentNode.replaceChild(newMain, currentMain);

                // Re-execute scripts ใน <main> ที่ถูกสลับเข้ามา
                const scripts = newMain.querySelectorAll('script');
                scripts.forEach(oldScript => {
                    const newScript = document.createElement('script');
                    Array.from(oldScript.attributes).forEach(attr => newScript.setAttribute(attr.name, attr.value));
                    if (oldScript.textContent) {
                        newScript.textContent = oldScript.textContent;
                    }
                    oldScript.parentNode.replaceChild(newScript, oldScript);
                });
            }

            // ** แก้ไขแล้ว: ต้องอัปเดต URL ก่อน ค่อยไฮไลท์เมนู **
            if (pushState) window.history.pushState({}, '', url);
            updateActiveNav();

            window.scrollTo(0, 0);

        } catch (error) {
            console.error(`Error loading page content for ${url}:`, error);
        } finally {
            document.body.style.cursor = 'default';
        }
    };

    // Intercept Link Clicks
    document.body.addEventListener('click', e => {
        const link = e.target.closest('a');
        if (link) {
            const href = link.getAttribute('href');
            // ตรวจสอบให้ดักจับเฉพาะลิงก์ภายใน
            if (href && href.endsWith('.html') && !href.startsWith('http') && href !== 'login.html') {
                e.preventDefault();
                let currentPath = window.location.pathname.split('/').pop();
                if (currentPath !== href) {
                    loadPageContent(href);
                }
            }
        }
    });

    window.addEventListener('popstate', () => {
        loadPageContent(window.location.pathname.split('/').pop() || 'overview.html', false);
    });

    // --- 3. Auth & Initial Load ---
    const checkAuth = () => {
        if (window.location.pathname.includes('login.html')) return;
        const token = localStorage.getItem('nexus_token');
        if (!token) window.location.href = 'login.html';
    };

    // Centralized Export
    document.body.addEventListener('click', (e) => {
        const btn = e.target.closest('.btn-export');
        if (!btn) return;
        if (btn.classList.contains('exporting')) return;

        e.preventDefault();

        const exportUrl = btn.getAttribute('data-export-url');
        if (!exportUrl) return;

        btn.classList.add('exporting');
        const originalHtml = btn.innerHTML;
        btn.innerHTML = `<span class="material-symbols-outlined text-[16px] animate-spin">sync</span> โหลด...`;

        // Use direct navigation for reliable downloads
        window.location.href = exportUrl;

        // Reset button after a short delay
        setTimeout(() => {
            btn.innerHTML = originalHtml;
            btn.classList.remove('exporting');
        }, 2000);
    });

    checkAuth();
    if (!window.location.pathname.includes('login.html') && isInitialLoad) {
        // โหลด Navbar และข้อมูล User แค่ครั้งแรกครั้งเดียว
        loadComponent("navbar-placeholder", "navbar.html", () => {
            updateActiveNav();
            fetch('api/me.php?_=' + new Date().getTime())
                .then(res => res.json())
                .then(user => {
                    const userNameEl = document.getElementById('nav-user-name');
                    const userEmailEl = document.getElementById('nav-user-email');
                    const userAvatarEl = document.getElementById('nav-user-avatar');
                    if (userNameEl) userNameEl.innerText = user.fullname;
                    if (userEmailEl) userEmailEl.innerText = user.email;
                    if (userAvatarEl && user.fullname) {
                        userAvatarEl.innerText = user.fullname.charAt(0);
                    }
                })
                .catch(err => console.error("Error fetching user data", err));
        });
        loadComponent("footer-placeholder", "footer.html");
        isInitialLoad = false;
        
        // Start polling notifications periodically
        fetchNotifications();
        setInterval(fetchNotifications, 60000); // Poll every minute
    }
});

// --- Notifications Logic ---
window.toggleNotifications = function() {
    const dropdown = document.getElementById('nav-notif-dropdown');
    if (dropdown) {
        dropdown.classList.toggle('hidden');
        if (!dropdown.classList.contains('hidden')) {
            fetchNotifications();
        }
    }
};

window.fetchNotifications = async function() {
    try {
        const res = await fetch('api/notifications.php');
        const data = await res.json();
        
        const badge = document.getElementById('nav-notif-badge');
        if (badge) {
            if (data.unread_count > 0) badge.classList.remove('hidden');
            else badge.classList.add('hidden');
        }
        
        const list = document.getElementById('nav-notif-list');
        if (list && data.notifications) {
            if (data.notifications.length === 0) {
                list.innerHTML = `<div class="p-4 text-center text-sm text-on-surface-variant">ไม่มีการแจ้งเตือนใหม่</div>`;
                return;
            }
            
            list.innerHTML = data.notifications.map(n => {
                const isUnread = n.is_read == 0;
                const bgClass = isUnread ? 'bg-primary/5' : 'bg-transparent';
                const dot = isUnread ? `<span class="w-2 h-2 rounded-full bg-primary flex-shrink-0 mt-1.5"></span>` : `<span class="w-2 h-2 flex-shrink-0"></span>`;
                
                let icon = 'notifications';
                let iconColor = 'text-primary';
                if (n.notification_type === 'data_error') { icon = 'error'; iconColor = 'text-error'; }
                else if (n.notification_type === 'analysis_completed') { icon = 'check_circle'; iconColor = 'text-emerald-500'; }
                
                return `
                <div class="p-3 border-b border-outline-variant flex gap-3 hover:bg-surface-container transition-colors ${bgClass}">
                    ${dot}
                    <div class="w-8 h-8 rounded-full bg-surface-container flex items-center justify-center flex-shrink-0">
                        <span class="material-symbols-outlined text-[18px] ${iconColor}">${icon}</span>
                    </div>
                    <div class="flex-1 min-w-0">
                        <h4 class="text-sm font-semibold text-on-surface truncate">${n.title}</h4>
                        <p class="text-xs text-on-surface-variant mt-0.5 line-clamp-2">${n.message}</p>
                        <span class="text-[10px] text-on-surface-variant mt-1 block">${new Date(n.created_at).toLocaleString('th-TH')}</span>
                    </div>
                </div>
                `;
            }).join('');
        }
    } catch (err) {
        console.error("Error fetching notifications", err);
    }
};

window.markNotificationsRead = async function() {
    try {
        await fetch('api/notifications.php', { method: 'POST' });
        fetchNotifications();
    } catch (err) {
        console.error(err);
    }
};

// Close notifications when clicking outside
document.addEventListener('click', function(e) {
    const btn = document.getElementById('nav-btn-notification');
    const dropdown = document.getElementById('nav-notif-dropdown');
    if (btn && dropdown && !btn.contains(e.target) && !dropdown.contains(e.target)) {
        dropdown.classList.add('hidden');
    }
});
