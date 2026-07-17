// Update current date and time
function updateDateTime() {
    const now = new Date();
    const options = {
        weekday: 'long',
        year: 'numeric',
        month: 'long',
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit'
    };
    
    document.getElementById('current-date').textContent = now.toLocaleDateString('en-PH', options);
}

// Calculate profit margin
function calculateMargin(sales, profit) {
    if (!sales || sales === 0) return '0%';
    const margin = (profit / sales) * 100;
    return margin.toFixed(1) + '%';
}

// Render the sales & profit line chart using the data the PHP page
// embeds on window.SALES_CHART_DATA (see dashboard.php)
function renderSalesChart() {
    const canvas = document.getElementById('salesChart');
    const data = window.SALES_CHART_DATA;
    if (!canvas) return;
    if (!data) {
        console.error('Sales chart: window.SALES_CHART_DATA is missing.');
        return;
    }
    if (typeof Chart === 'undefined') {
        console.error('Sales chart: Chart.js did not load (check the CDN <script> tag/URL and your network connection).');
        const wrap = canvas.closest('.chart-wrap');
        if (wrap) {
            wrap.innerHTML = '<p style="color:var(--ram-muted); font-size:0.85rem; margin:0;">Chart library failed to load — check your internet connection or the Chart.js script tag.</p>';
        }
        return;
    }

    const styles = getComputedStyle(document.documentElement);
    const red = styles.getPropertyValue('--ram-red').trim() || '#750b1d';
    const yellow = styles.getPropertyValue('--ram-yellow').trim() || '#f9e076';
    const muted = styles.getPropertyValue('--ram-muted').trim() || '#8a7a5c';

    new Chart(canvas.getContext('2d'), {
        type: 'line',
        data: {
            labels: data.labels,
            datasets: [
                {
                    label: 'Sales',
                    data: data.sales,
                    borderColor: red,
                    backgroundColor: red + '22',
                    fill: true,
                    tension: 0.35,
                    pointRadius: 3,
                    pointBackgroundColor: red,
                },
                {
                    label: 'Profit',
                    data: data.profit,
                    borderColor: '#b8790b',
                    backgroundColor: yellow + '33',
                    borderDash: [6, 3],
                    fill: true,
                    tension: 0.35,
                    pointRadius: 3,
                    pointStyle: 'rectRot',
                    pointBackgroundColor: '#b8790b',
                }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { display: false },
                tooltip: {
                    callbacks: {
                        label: (ctx) => `${ctx.dataset.label}: ₱${ctx.parsed.y.toLocaleString()}`
                    }
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: {
                        color: muted,
                        callback: (value) => '₱' + value.toLocaleString()
                    },
                    grid: { color: 'rgba(117,11,29,0.08)' }
                },
                x: {
                    ticks: { color: muted },
                    grid: { display: false }
                }
            }
        }
    });
}

// Update profit margins on load
document.addEventListener('DOMContentLoaded', () => {
    updateDateTime();
    renderSalesChart();
    
    // Update margin for today
    const todaySales = parseFloat(
        document.querySelector('.today-card .stat-value').textContent.replace(/[₱,]/g, '')
    );
    const todayProfit = parseFloat(
        document.querySelector('.profit-card .stat-value').textContent.replace(/[₱,]/g, '')
    );
    document.getElementById('profit-margin').textContent = calculateMargin(todaySales, todayProfit) + ' margin';
    
    // Update margin for month
    const monthSales = parseFloat(
        document.querySelector('.month-card .stat-value').textContent.replace(/[₱,]/g, '')
    );
    const monthProfit = parseFloat(
        document.querySelector('.month-profit-card .stat-value').textContent.replace(/[₱,]/g, '')
    );
    document.getElementById('month-margin').textContent = calculateMargin(monthSales, monthProfit) + ' margin';
    
    // Update time every minute
    setInterval(updateDateTime, 60000);
});

// Quick action handlers
document.querySelectorAll('.action-btn').forEach((btn, index) => {
    btn.addEventListener('click', () => {
        switch(index) {
            case 0: // Export
                exportToCSV();
                break;
            case 1: // Print
                window.print();
                break;
            case 2: // Settings
                alert('Settings coming soon!');
                break;
        }
    });
});

// Export sales data to CSV
function exportToCSV() {
    const table = document.querySelector('.transactions-table');
    if (!table) return;
    let csv = 'Transaction ID,Date & Time,Items,Total Amount,Profit\n';
    
    table.querySelectorAll('tbody tr:not(.empty-row)').forEach(row => {
        const cells = row.querySelectorAll('td');
        const transId = cells[0].textContent.trim();
        const date = cells[1].textContent.trim();
        const items = cells[2].textContent.trim();
        const amount = cells[3].textContent.trim();
        const profit = cells[4].textContent.trim();
        
        csv += `"${transId}","${date}","${items}","${amount}","${profit}"\n`;
    });
    
    const element = document.createElement('a');
    element.setAttribute('href', 'data:text/csv;charset=utf-8,' + encodeURIComponent(csv));
    element.setAttribute('download', `sales_export_${new Date().toISOString().split('T')[0]}.csv`);
    element.style.display = 'none';
    document.body.appendChild(element);
    element.click();
    document.body.removeChild(element);
}

// Add some animation to stat cards on load
document.addEventListener('DOMContentLoaded', () => {
    const statCards = document.querySelectorAll('.stat-card');
    statCards.forEach((card, index) => {
        card.style.opacity = '0';
        card.style.transform = 'translateY(20px)';
        setTimeout(() => {
            card.style.transition = 'all 0.5s ease';
            card.style.opacity = '1';
            card.style.transform = 'translateY(0)';
        }, index * 100);
    });
});