// --- 1. Fetch Interceptor (แนบ JWT อัตโนมัติ - Guard ป้องกัน double-wrap) ---
if (!window.__nexusFetchBound) {
    window.__nexusFetchBound = true;
    const _origFetch = window.fetch;
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

        const response = await _origFetch(resource, config);
        if (response.status === 401 && !window.location.pathname.includes('login.html')) {
            localStorage.removeItem('nexus_token');
            window.location.href = 'login.html';
        }
        return response;
    };
}

// --- Auth Check (global scope) ---
window.__nexusCheckAuth = function () {
    if (window.location.pathname.includes('login.html')) return;
    const token = localStorage.getItem('nexus_token');
    if (!token) window.location.href = 'login.html';
};

// --- DOMContentLoaded ---
document.addEventListener("DOMContentLoaded", () => {

    // --- Load HTML Component (Navbar / Footer) ---
    const loadComponent = async (id, file, callback) => {
        const el = document.getElementById(id);
        if (!el) return;
        try {
            const res = await fetch(file);
            if (res.ok) {
                el.innerHTML = await res.text();
                if (callback) callback();
            }
        } catch (err) {
            console.error(`Error loading ${file}:`, err);
        }
    };

    // --- Highlight Active Nav Link ---
    const updateActiveNav = () => {
        let currentPath = window.location.pathname.split('/').pop() || 'overview.html';
        document.querySelectorAll('#nav-tabs .nav-link').forEach(link => {
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

    // --- Export Button Handler (guard against duplicate binding) ---
    if (!window.__nexusEventsBound) {
        window.__nexusEventsBound = true;

        document.body.addEventListener('click', (e) => {
            const btn = e.target.closest('.btn-export');
            if (!btn || btn.classList.contains('exporting')) return;

            e.preventDefault();
            const exportUrl = btn.getAttribute('data-export-url');
            if (!exportUrl) return;

            btn.classList.add('exporting');
            const originalHtml = btn.innerHTML;
            btn.innerHTML = `<span class="material-symbols-outlined text-[16px] animate-spin">sync</span> โหลด...`;
            window.location.href = exportUrl;
            setTimeout(() => {
                btn.innerHTML = originalHtml;
                btn.classList.remove('exporting');
            }, 2500);
        });
    }

    // --- Auth & Initial Navbar/Footer Load ---
    window.__nexusCheckAuth();

    if (!window.location.pathname.includes('login.html') && !window.__nexusNavLoaded) {
        window.__nexusNavLoaded = true;

        // Navbar ถูก Embed ไว้ในทุกหน้าแล้ว - ไม่ต้อง fetch ป้องกัน flicker
        updateActiveNav();

        // อัปเดตข้อมูล User (ชื่อ/อีเมล/avatar)
        fetch('api/me.php?_=' + Date.now())
            .then(r => r.json())
            .then(user => {
                const el = (id) => document.getElementById(id);
                if (el('nav-user-name'))   el('nav-user-name').innerText   = user.fullname || '';
                if (el('nav-user-email'))  el('nav-user-email').innerText  = user.email    || '';
                if (el('nav-user-avatar') && user.fullname)
                    el('nav-user-avatar').innerText = user.fullname.charAt(0);
            })
            .catch(err => console.error("Error fetching user", err));

        // Footer (เบา ไม่มี flicker)
        loadComponent("footer-placeholder", "footer.html");

        // Notification polling
        // Notification polling
        fetchNotifications();
        setInterval(fetchNotifications, 60000);
    }
    
    // Initialize custom dropdowns
    if (window.initCustomSelects) {
        window.initCustomSelects();
    }
});

// ─────────────────────────────────────────────
// UI Utilities
// ─────────────────────────────────────────────
window.initCustomSelects = function () {
    const selects = document.querySelectorAll('select:not(.customized)');
    selects.forEach(select => {
        select.classList.add('customized');
        
        // Ensure parent is relative if we are appending absolute things, but wrapper handles it
        select.style.display = 'none';

        const wrapper = document.createElement('div');
        wrapper.className = 'relative w-full text-left inline-block';
        
        const trigger = document.createElement('button');
        trigger.type = 'button';
        // Copy some classes from original select if needed, or use default beautiful ones
        trigger.className = 'w-full bg-surface-container-low border border-outline-variant text-sm text-on-surface py-2 px-3 rounded-xl focus:outline-none focus:border-primary focus:ring-1 focus:ring-primary transition-colors flex items-center justify-between shadow-sm';
        
        const selectedText = document.createElement('span');
        selectedText.className = 'truncate';
        selectedText.innerText = select.options[select.selectedIndex]?.text || 'Select...';
        
        const chevron = document.createElement('span');
        chevron.className = 'material-symbols-outlined text-[18px] text-on-surface-variant pointer-events-none transition-transform duration-200';
        chevron.innerText = 'expand_more';
        
        trigger.appendChild(selectedText);
        trigger.appendChild(chevron);
        
        const dropdown = document.createElement('ul');
        dropdown.className = 'absolute z-50 mt-1 w-full bg-surface border border-outline-variant rounded-xl shadow-lg hidden overflow-hidden max-h-60 overflow-y-auto transform opacity-0 scale-95 transition-all duration-200 origin-top';
        
        Array.from(select.options).forEach((opt, idx) => {
            const li = document.createElement('li');
            li.className = 'px-3 py-2 text-sm text-on-surface hover:bg-primary/10 cursor-pointer transition-colors flex items-center justify-between';
            if (idx === select.selectedIndex) {
                li.classList.add('bg-primary/5', 'font-medium', 'text-primary');
            }
            li.innerText = opt.text;
            
            li.addEventListener('click', (e) => {
                e.stopPropagation();
                select.selectedIndex = idx;
                select.dispatchEvent(new Event('change'));
                selectedText.innerText = opt.text;
                
                Array.from(dropdown.children).forEach(child => child.className = 'px-3 py-2 text-sm text-on-surface hover:bg-primary/10 cursor-pointer transition-colors flex items-center justify-between');
                li.className = 'px-3 py-2 text-sm text-primary bg-primary/5 font-medium cursor-pointer transition-colors flex items-center justify-between';
                
                closeDropdown();
            });
            dropdown.appendChild(li);
        });
        
        wrapper.appendChild(trigger);
        wrapper.appendChild(dropdown);
        
        select.parentNode.insertBefore(wrapper, select.nextSibling);
        
        let isOpen = false;
        
        const openDropdown = () => {
            isOpen = true;
            wrapper.style.zIndex = '50';
            dropdown.classList.remove('hidden');
            requestAnimationFrame(() => {
                dropdown.classList.remove('opacity-0', 'scale-95');
                dropdown.classList.add('opacity-100', 'scale-100');
                chevron.style.transform = 'rotate(180deg)';
                trigger.classList.add('border-primary', 'ring-1', 'ring-primary');
            });
        };
        
        const closeDropdown = () => {
            isOpen = false;
            wrapper.style.zIndex = '';
            dropdown.classList.remove('opacity-100', 'scale-100');
            dropdown.classList.add('opacity-0', 'scale-95');
            chevron.style.transform = 'rotate(0deg)';
            trigger.classList.remove('border-primary', 'ring-1', 'ring-primary');
            setTimeout(() => {
                if (!isOpen) dropdown.classList.add('hidden');
            }, 200);
        };
        
        trigger.addEventListener('click', (e) => {
            e.preventDefault();
            e.stopPropagation();
            if (isOpen) closeDropdown();
            else {
                // Close all other custom selects
                document.dispatchEvent(new CustomEvent('close-all-selects'));
                openDropdown();
            }
        });
        
        document.addEventListener('close-all-selects', () => {
            if (isOpen) closeDropdown();
        });

        document.addEventListener('click', (e) => {
            if (isOpen && !wrapper.contains(e.target)) {
                closeDropdown();
            }
        });
        
        // Listen for programmatic changes to original select
        select.addEventListener('change', () => {
            selectedText.innerText = select.options[select.selectedIndex]?.text || '';
            Array.from(dropdown.children).forEach((child, idx) => {
                if (idx === select.selectedIndex) {
                    child.className = 'px-3 py-2 text-sm text-primary bg-primary/5 font-medium cursor-pointer transition-colors flex items-center justify-between';
                } else {
                    child.className = 'px-3 py-2 text-sm text-on-surface hover:bg-primary/10 cursor-pointer transition-colors flex items-center justify-between';
                }
            });
        });
    });
};

// ─────────────────────────────────────────────
// Notifications
// ─────────────────────────────────────────────
window.toggleNotifications = function () {
    const dropdown = document.getElementById('nav-notif-dropdown');
    if (!dropdown) return;
    dropdown.classList.toggle('hidden');
    if (!dropdown.classList.contains('hidden')) fetchNotifications();
};

window.fetchNotifications = async function () {
    try {
        const res  = await fetch('api/notifications.php');
        const data = await res.json();

        const badge = document.getElementById('nav-notif-badge');
        if (badge) badge.classList.toggle('hidden', !(data.unread_count > 0));

        const list = document.getElementById('nav-notif-list');
        if (!list || !data.notifications) return;

        if (data.notifications.length === 0) {
            list.innerHTML = `<div class="p-4 text-center text-sm text-on-surface-variant">ไม่มีการแจ้งเตือนใหม่</div>`;
            return;
        }

        list.innerHTML = data.notifications.map(n => {
            const isUnread  = n.is_read == 0;
            const bgClass   = isUnread ? 'bg-primary/5' : 'bg-transparent';
            const dot       = isUnread
                ? `<span class="w-2 h-2 rounded-full bg-primary flex-shrink-0 mt-1.5"></span>`
                : `<span class="w-2 h-2 flex-shrink-0"></span>`;
            let icon = 'notifications', iconColor = 'text-primary';
            if (n.notification_type === 'data_error')         { icon = 'error';         iconColor = 'text-error'; }
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
            </div>`;
        }).join('');
    } catch (err) {
        console.error("Error fetching notifications", err);
    }
};

window.markNotificationsRead = async function () {
    try {
        await fetch('api/notifications.php', { method: 'POST' });
        fetchNotifications();
    } catch (err) { console.error(err); }
};

// Close notifications dropdown on outside click
if (!window.__nexusNotifBound) {
    window.__nexusNotifBound = true;
    document.addEventListener('click', function (e) {
        const btn      = document.getElementById('nav-btn-notification');
        const dropdown = document.getElementById('nav-notif-dropdown');
        if (btn && dropdown && !btn.contains(e.target) && !dropdown.contains(e.target)) {
            dropdown.classList.add('hidden');
        }
    });
}
