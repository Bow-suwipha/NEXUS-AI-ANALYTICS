
            (async () => {
                const periodTabs = document.querySelectorAll('.sa-period-tab');
                periodTabs.forEach(tab => {
                    tab.addEventListener('click', (e) => {
                        periodTabs.forEach(t => {
                            t.className = 'sa-period-tab px-space-sm py-1 rounded-lg text-on-surface-variant text-sm hover:text-on-surface transition-all';
                        });
                        e.target.className = 'sa-period-tab px-space-sm py-1 rounded-lg bg-surface text-primary text-sm font-semibold shadow-sm';
                    });
                });

                // Fetch Products
                const formatNumber = new Intl.NumberFormat('th-TH');
                try {
                    const res = await fetch('api/sales_products.php');
                    if (res.ok) {
                        const products = await res.json();
                        const tbody = document.getElementById('products-table-body');
                        tbody.innerHTML = '';
                        let totalSales = 0;

                        products.forEach(p => {
                            totalSales += p.sales;
                            const tr = document.createElement('tr');
                            tr.className = "hover:bg-surface-container-low/50 transition-colors";
                            
                            let trendColor = "text-primary";
                            let trendIcon = "↑";
                            if (p.trend_direction === 'down') {
                                trendColor = "text-error";
                                trendIcon = "↓";
                            }
                            
                            let shareClass = "bg-surface-container-low text-on-surface";
                            if (p.share_percent > 25) {
                                shareClass = "bg-primary/20 text-on-surface font-semibold";
                            }

                            tr.innerHTML = `
                                <td class="py-3 px-4">
                                    <span class="text-sm text-on-surface font-semibold block">${p.name}</span>
                                    <span class="text-[11px] text-on-surface-variant">${p.sku}</span>
                                </td>
                                <td class="py-3 px-4 text-right font-mono text-on-surface">${formatNumber.format(p.sales)}</td>
                                <td class="py-3 px-4 text-center">
                                    <span class="px-1.5 py-0.5 rounded ${shareClass} font-mono text-[11px]">${p.share_percent}%</span>
                                </td>
                                <td class="py-3 px-4 text-center ${trendColor} font-semibold font-mono">${p.trend}% ${trendIcon}</td>
                                <td class="py-3 px-4 text-right font-mono text-on-surface">${formatNumber.format(p.stock_quantity)} ชิ้น</td>
                            `;
                            tbody.appendChild(tr);
                        });

                        document.getElementById('total-sales-value').innerText = `฿${formatNumber.format(totalSales)}`;
                    }
                } catch (err) {
                    console.error("Failed to load products", err);
                    document.getElementById('products-table-body').innerHTML = `<tr><td colspan="5" class="py-4 text-center text-sm text-error">ไม่สามารถโหลดข้อมูลได้</td></tr>`;
                }

                // Product Autocomplete Logic
                const searchInput = document.getElementById('product-search-input');
                const searchClear = document.getElementById('product-search-clear');
                const searchDropdown = document.getElementById('product-search-dropdown');
                const searchLoading = document.getElementById('product-search-loading');
                const searchEmpty = document.getElementById('product-search-empty');
                const searchResults = document.getElementById('product-search-results');
                
                let debounceTimer;

                if (searchInput) {
                    searchInput.addEventListener('input', (e) => {
                        const val = e.target.value.trim();
                        if (val.length > 0) {
                            searchClear.classList.remove('hidden');
                            searchDropdown.classList.remove('hidden');
                            searchLoading.style.display = 'block';
                            searchEmpty.style.display = 'none';
                            searchResults.innerHTML = '';
                            
                            clearTimeout(debounceTimer);
                            debounceTimer = setTimeout(async () => {
                                try {
                                    const res = await fetch(`api/products_search.php?q=${encodeURIComponent(val)}`);
                                    if (res.ok) {
                                        const data = await res.json();
                                        searchLoading.style.display = 'none';
                                        if (data.length === 0) {
                                            searchEmpty.style.display = 'block';
                                        } else {
                                            data.forEach(p => {
                                                const li = document.createElement('li');
                                                li.className = 'px-3 py-2 hover:bg-surface-container-low cursor-pointer flex flex-col gap-0.5';
                                                li.innerHTML = `
                                                    <span class="text-sm font-medium text-on-surface">${p.product_name}</span>
                                                    <span class="text-[10px] text-on-surface-variant font-mono">${p.product_code}</span>
                                                `;
                                                li.addEventListener('click', () => {
                                                    searchInput.value = p.product_name;
                                                    searchDropdown.classList.add('hidden');
                                                    // TODO: Trigger map/table filter if needed
                                                });
                                                searchResults.appendChild(li);
                                            });
                                        }
                                    }
                                } catch (error) {
                                    searchLoading.style.display = 'none';
                                    searchEmpty.style.display = 'block';
                                }
                            }, 300);
                        } else {
                            searchClear.classList.add('hidden');
                            searchDropdown.classList.add('hidden');
                        }
                    });

                    searchClear.addEventListener('click', () => {
                        searchInput.value = '';
                        searchClear.classList.add('hidden');
                        searchDropdown.classList.add('hidden');
                        searchInput.focus();
                    });

                    // Close dropdown on click outside
                    document.addEventListener('click', (e) => {
                        if (!searchInput.contains(e.target) && !searchDropdown.contains(e.target)) {
                            searchDropdown.classList.add('hidden');
                        }
                    });
                }
            })();

            const aiBtn = document.getElementById('runAiBtn');
            const wsTerminal = document.getElementById('ai-realtime-terminal');
            const wsOutput = document.getElementById('ai-realtime-logs');
            const insightBox = document.getElementById('ai-realtime-output');
            const insightText = document.getElementById('ai-insight-text');

            if (aiBtn) {
                aiBtn.addEventListener('click', async () => {
                    const originalHtml = aiBtn.innerHTML;
                    aiBtn.classList.add('opacity-75', 'cursor-wait');
                    aiBtn.disabled = true;
                    aiBtn.innerHTML = `
                        <span class="material-symbols-outlined text-[18px] animate-spin text-surface">sync</span>
                        <span>กำลังประมวลผล...</span>
                    `;
                    
                    // แสดง Terminal
                    if (wsTerminal) wsTerminal.classList.remove('hidden');
                    if (insightBox) insightBox.classList.add('hidden');
                    if (wsOutput) wsOutput.innerHTML = '<div class="text-on-surface-variant/50 italic">Connecting to AutoGen Swarm...</div>';
                    
                    // เชื่อมต่อ WebSocket
                    const ws = new WebSocket('ws://127.0.0.1:8000/ws/agent-logs');
                    
                    ws.onmessage = (event) => {
                        if (wsOutput) {
                            const p = document.createElement('div');
                            p.innerHTML = event.data;
                            p.className = "py-1 text-sm border-b border-surface-container/30 last:border-0";
                            wsOutput.appendChild(p);
                            wsOutput.scrollTop = wsOutput.scrollHeight;
                        }
                        
                        // ถ้าระบบส่งข้อความเสร็จสิ้น
                        if (event.data.includes('Task completed')) {
                            ws.close();
                            
                            // ดึงผลลัพธ์มาแสดง
                            fetch('api/insights_alerts.php')
                                .then(res => res.json())
                                .then(data => {
                                    if (data && data.length > 0) {
                                        if (insightBox) insightBox.classList.remove('hidden');
                                        if (insightText) insightText.innerText = data[0].description;
                                    }
                                });
                                
                            aiBtn.innerHTML = `
                                <span class="material-symbols-outlined text-[18px]">done</span>
                                <span>เสร็จสิ้น</span>
                            `;
                            aiBtn.classList.remove('opacity-75', 'cursor-wait');
                            aiBtn.disabled = false;
                            setTimeout(() => aiBtn.innerHTML = originalHtml, 3000);
                        }
                    };
                    
                    try {
                        // ยิงคำสั่งไปให้ FastAPI เริ่มกระบวนการ AutoGen
                        await fetch('api/run_ai.php', { method: 'POST' });
                    } catch (error) {
                        console.error("Error trigger AI:", error);
                        aiBtn.innerHTML = originalHtml;
                        aiBtn.disabled = false;
                    }
                });
            }
        
