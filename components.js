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
        const btn = e.target.closest('button');
        if (!btn || !btn.innerText.match(/Excel|PDF/)) return;
        if (btn.classList.contains('exporting')) return;

        btn.classList.add('exporting');
        const originalHtml = btn.innerHTML;
        btn.innerHTML = `<span class="material-symbols-outlined text-[16px] animate-spin">sync</span> โหลด...`;

        const type = btn.innerText.includes('Excel') ? 'excel' : 'pdf';
        const ext = type === 'excel' ? 'xlsx' : 'pdf';

        fetch(`export_${type}`)
            .then(res => res.blob())
            .then(blob => {
                const url = window.URL.createObjectURL(blob);
                const a = document.createElement('a');
                a.href = url;
                a.download = `Nexus_AI_Summary.${ext}`;
                document.body.appendChild(a);
                a.click();
                a.remove();
            })
            .finally(() => {
                setTimeout(() => {
                    btn.innerHTML = originalHtml;
                    btn.classList.remove('exporting');
                }, 500);
            });
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
    }
});
