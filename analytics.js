// ============ RAM-YUM Analytics ============
// Depends on:
//   window.RAMYUM_ANALYTICS  (injected by analytics.php)
//   Chart.js (loaded via CDN in analytics.php)

const DATA = window.RAMYUM_ANALYTICS || { series: {}, categoryMix: [] };

const RED = '#750b1d';
const YELLOW = '#f9e076';
const GOOD = '#2e7d4f';
const MUTED = '#8a7a5c';

function formatPeso(n) {
    const num = Number(n) || 0;
    return '₱' + num.toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}

function formatPesoCompact(n) {
    const num = Number(n) || 0;
    if (num >= 1000000) return '₱' + (num / 1000000).toFixed(1) + 'M';
    if (num >= 1000) return '₱' + (num / 1000).toFixed(1) + 'k';
    return '₱' + num.toFixed(0);
}

// ---------- category mix (donut) ----------
const catCanvas = document.getElementById('categoryChart');
let categoryChart = null;
if (catCanvas) {
    const mix = (DATA.categoryMix || []).filter(c => Number(c.revenue) > 0);
    const palette = ['#750b1d', '#b8790b', '#2e7d4f', '#f9e076', '#8a7a5c', '#4f0713', '#c9974f'];

    categoryChart = new Chart(catCanvas, {
        type: 'doughnut',
        data: {
            labels: mix.length ? mix.map(c => c.name) : ['No sales yet'],
            datasets: [{
                data: mix.length ? mix.map(c => Number(c.revenue)) : [1],
                backgroundColor: mix.length ? palette.slice(0, mix.length) : ['#e5d6ab'],
                borderColor: '#fff9e6',
                borderWidth: 2,
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'bottom',
                    labels: { boxWidth: 10, font: { size: 11 }, color: '#2b1810' }
                },
                tooltip: {
                    callbacks: {
                        label: (ctx) => mix.length ? `${ctx.label}: ${formatPeso(ctx.parsed)}` : 'No sales recorded yet'
                    }
                }
            }
        }
    });
}

// ---------- performance chart (revenue + profit over time) ----------
const perfCanvas = document.getElementById('performanceChart');
const periodSummary = document.getElementById('periodSummary');
let performanceChart = null;

function buildPerformanceChart(period) {
    const rows = DATA.series?.[period] || [];
    const labels = rows.map(r => String(r.label));
    const revenue = rows.map(r => Number(r.revenue));
    const profit = rows.map(r => Number(r.profit));

    if (performanceChart) {
        performanceChart.data.labels = labels;
        performanceChart.data.datasets[0].data = revenue;
        performanceChart.data.datasets[1].data = profit;
        performanceChart.update();
    } else {
        performanceChart = new Chart(perfCanvas, {
            type: 'line',
            data: {
                labels,
                datasets: [
                    {
                        label: 'Revenue',
                        data: revenue,
                        borderColor: RED,
                        backgroundColor: 'rgba(117,11,29,0.08)',
                        pointBackgroundColor: RED,
                        tension: 0.3,
                        fill: true,
                    },
                    {
                        label: 'Profit',
                        data: profit,
                        borderColor: GOOD,
                        backgroundColor: 'rgba(46,125,79,0.08)',
                        pointBackgroundColor: GOOD,
                        tension: 0.3,
                        fill: true,
                    },
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                interaction: { mode: 'index', intersect: false },
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        callbacks: {
                            label: (ctx) => `${ctx.dataset.label}: ${formatPeso(ctx.parsed.y)}`
                        }
                    }
                },
                scales: {
                    x: { ticks: { color: MUTED, font: { size: 10 }, maxRotation: 0, autoSkipPadding: 12 }, grid: { display: false } },
                    y: {
                        ticks: { color: MUTED, font: { size: 10 }, callback: (v) => formatPesoCompact(v) },
                        grid: { color: 'rgba(117,11,29,0.06)' }
                    }
                }
            }
        });
    }

    // period summary strip
    const totalRevenue = revenue.reduce((a, b) => a + b, 0);
    const totalProfit = profit.reduce((a, b) => a + b, 0);
    const totalTxns = rows.reduce((a, r) => a + Number(r.txns || 0), 0);

    if (periodSummary) {
        periodSummary.innerHTML = `
            <div class="ps-item"><div class="ps-value">${formatPeso(totalRevenue)}</div><div class="ps-label">Revenue in range</div></div>
            <div class="ps-item"><div class="ps-value">${formatPeso(totalProfit)}</div><div class="ps-label">Profit in range</div></div>
            <div class="ps-item"><div class="ps-value">${totalTxns.toLocaleString('en-PH')}</div><div class="ps-label">Transactions</div></div>
        `;
    }

    if (rows.length === 0 && perfCanvas) {
        perfCanvas.parentElement.style.opacity = '0.4';
    } else if (perfCanvas) {
        perfCanvas.parentElement.style.opacity = '1';
    }
}

// initial paint
buildPerformanceChart('daily');

document.getElementById('periodTabs')?.addEventListener('click', (e) => {
    const btn = e.target.closest('.an-tab');
    if (!btn) return;
    document.querySelectorAll('#periodTabs .an-tab').forEach(t => t.classList.remove('active'));
    btn.classList.add('active');
    buildPerformanceChart(btn.dataset.period);
});

// ---------- product ranking tabs ----------
document.getElementById('rankTabs')?.addEventListener('click', (e) => {
    const btn = e.target.closest('.an-tab');
    if (!btn) return;
    document.querySelectorAll('#rankTabs .an-tab').forEach(t => t.classList.remove('active'));
    btn.classList.add('active');

    const target = btn.dataset.rank;
    document.querySelectorAll('.an-rank-table').forEach(table => {
        table.classList.toggle('hidden', table.dataset.rankPanel !== target);
    });
});
