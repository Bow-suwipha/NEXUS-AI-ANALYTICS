
        document.addEventListener("DOMContentLoaded", async () => {
            // Load Alerts
            try {
                const res = await fetch('api/insights_alerts.php');
                if (res.ok) {
                    const alerts = await res.json();
                    const container = document.getElementById('alerts-container');
                    container.innerHTML = '';
                    if (alerts.length === 0) {
                        container.innerHTML = `<div class="text-sm text-on-surface-variant col-span-full py-4">ไม่พบข้อมูลเชิงลึกใหม่</div>`;
                    } else {
                        alerts.forEach(a => {
                            const isHigh = a.severity === 'High';
                            const isPositive = a.insight_type === 'Trend' || a.insight_type === 'Highlight';
                            
                            let bgClass = "bg-surface-container-low border-outline-variant";
                            let icon = "lightbulb";
                            let iconClass = "text-primary";
                            
                            if (isHigh) {
                                bgClass = "bg-error-container/20 border-error/20";
                                icon = "warning";
                                iconClass = "text-error";
                            } else if (isPositive) {
                                bgClass = "bg-emerald-50 border-emerald-200";
                                icon = "trending_up";
                                iconClass = "text-emerald-600";
                            }

                            const alertDiv = document.createElement('div');
                            alertDiv.className = `${bgClass} border p-space-md rounded-2xl flex items-start gap-3 shadow-sm`;
                            alertDiv.innerHTML = `
                                <span class="material-symbols-outlined ${iconClass} mt-0.5">${icon}</span>
                                <div class="flex flex-col">
                                    <span class="${iconClass} font-bold text-sm">${a.title}</span>
                                    <span class="text-on-surface-variant text-sm mt-1">${a.description}</span>
                                </div>
                            `;
                            container.appendChild(alertDiv);
                        });
                    }
                }
            } catch (err) {
                console.error("Failed to load alerts", err);
            }

            // Load Chart (Mock Forecast Data)
            try {
                const ctx = document.getElementById('forecastChart').getContext('2d');
                // Simulate fetch data
                const labels = ["สัปดาห์ 1", "สัปดาห์ 2", "สัปดาห์ 3", "สัปดาห์ 4", "สัปดาห์หน้า (คาดการณ์)"];
                const actualData = [12000, 15000, 14000, 18000, null];
                const forecastData = [null, null, null, 18000, 22000];

                new Chart(ctx, {
                    type: 'line',
                    data: {
                        labels: labels,
                        datasets: [
                            {
                                label: 'ยอดขายจริง',
                                data: actualData,
                                borderColor: '#97C2EC',
                                backgroundColor: 'rgba(151, 194, 236, 0.2)',
                                borderWidth: 3,
                                fill: true,
                                tension: 0.4,
                                pointBackgroundColor: '#ffffff',
                                pointBorderColor: '#97C2EC',
                                pointRadius: 4,
                            },
                            {
                                label: 'คาดการณ์',
                                data: forecastData,
                                borderColor: '#D6D0C2',
                                borderDash: [5, 5],
                                borderWidth: 3,
                                fill: false,
                                tension: 0.4,
                                pointBackgroundColor: '#ffffff',
                                pointBorderColor: '#D6D0C2',
                                pointRadius: 4,
                            }
                        ]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: {
                                display: true,
                                position: 'bottom',
                                labels: { font: { family: 'Kanit', size: 12 }, color: '#888' }
                            },
                            tooltip: {
                                mode: 'index',
                                intersect: false,
                                titleFont: { family: 'Kanit' },
                                bodyFont: { family: 'Kanit' }
                            }
                        },
                        scales: {
                            x: {
                                grid: { display: false },
                                ticks: { font: { family: 'Kanit', size: 10 }, color: '#888' }
                            },
                            y: {
                                grid: { color: 'rgba(255,255,255,0.1)', drawBorder: false },
                                ticks: {
                                    font: { family: 'Kanit', size: 10 },
                                    color: '#888'
                                }
                            }
                        }
                    }
                });
            } catch (err) {
                console.error("Failed to load chart", err);
            }

            // Simple Chat Interaction
            const chatInput = document.querySelector('input[type="text"]');
            const sendBtn = chatInput.nextElementSibling;
            const chatContainer = document.getElementById('chat-history');

            const appendMessage = (text, isUser = true) => {
                const div = document.createElement('div');
                div.className = `flex gap-space-sm ${isUser ? 'flex-row-reverse' : ''}`;
                
                const avatar = document.createElement('div');
                avatar.className = `w-8 h-8 rounded-full shrink-0 flex items-center justify-center text-xs font-semibold ${isUser ? 'bg-primary text-on-primary' : 'bg-surface-container-low text-primary border border-outline-variant'}`;
                avatar.innerHTML = isUser ? 'SA' : '<span class="material-symbols-outlined text-[16px]">psychology</span>';
                
                const bubble = document.createElement('div');
                bubble.className = `p-3 rounded-2xl text-sm leading-relaxed max-w-[85%] ${isUser ? 'bg-primary text-on-primary rounded-tr-none' : 'bg-surface-container-low text-on-surface rounded-tl-none border border-outline-variant shadow-sm'}`;
                bubble.innerText = text;

                div.appendChild(avatar);
                div.appendChild(bubble);
                chatContainer.appendChild(div);
                chatContainer.scrollTop = chatContainer.scrollHeight;
            };

            const sendMessage = async () => {
                const text = chatInput.value.trim();
                if (!text) return;

                appendMessage(text, true);
                chatInput.value = '';

                // Add loading indicator
                const typingId = 'typing-' + Date.now();
                const typingDiv = document.createElement('div');
                typingDiv.id = typingId;
                typingDiv.className = `flex gap-space-sm`;
                typingDiv.innerHTML = `
                    <div class="w-8 h-8 rounded-full shrink-0 flex items-center justify-center text-xs font-semibold bg-surface-container-low text-primary border border-outline-variant">
                        <span class="material-symbols-outlined text-[16px]">psychology</span>
                    </div>
                    <div class="p-3 rounded-2xl bg-surface-container-low text-on-surface rounded-tl-none border border-outline-variant flex items-center gap-1">
                        <span class="w-1.5 h-1.5 bg-primary/50 rounded-full animate-bounce"></span>
                        <span class="w-1.5 h-1.5 bg-primary/50 rounded-full animate-bounce" style="animation-delay: 0.2s"></span>
                        <span class="w-1.5 h-1.5 bg-primary/50 rounded-full animate-bounce" style="animation-delay: 0.4s"></span>
                    </div>`;
                chatContainer.appendChild(typingDiv);
                chatContainer.scrollTop = chatContainer.scrollHeight;

                try {
                    const response = await fetch('api/chat.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({ message: text })
                    });
                    const data = await response.json();
                    document.getElementById(typingId).remove();
                    appendMessage(data.reply, false);
                } catch (error) {
                    document.getElementById(typingId).remove();
                    appendMessage("ขออภัย เกิดข้อผิดพลาดในการเชื่อมต่อเซิร์ฟเวอร์", false);
                }
            };

            sendBtn.addEventListener('click', sendMessage);
            chatInput.addEventListener('keypress', (e) => {
                if (e.key === 'Enter') sendMessage();
            });
        })();
    
