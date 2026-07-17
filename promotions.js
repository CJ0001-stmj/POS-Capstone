// ============ RAM-YUM Promotions page ============

// ---------- scope toggle: show product picker only for scope=product ----------
const scopeSelect = document.getElementById('scopeSelect');
const productPickerWrap = document.getElementById('productPickerWrap');

function syncScopeVisibility() {
    productPickerWrap.style.display = scopeSelect.value === 'product' ? 'block' : 'none';
}
scopeSelect?.addEventListener('change', syncScopeVisibility);
syncScopeVisibility();

// ---------- product picker filter ----------
const productPickerSearch = document.getElementById('productPickerSearch');
productPickerSearch?.addEventListener('input', () => {
    const term = productPickerSearch.value.trim().toLowerCase();
    document.querySelectorAll('.promo-product-option').forEach(opt => {
        opt.style.display = !term || opt.dataset.name.includes(term) ? 'flex' : 'none';
    });
});

// ---------- run analytics scan ----------
const runScanBtn = document.getElementById('runScanBtn');
const scanResult = document.getElementById('scanResult');

runScanBtn?.addEventListener('click', async () => {
    const percents = {};
    document.querySelectorAll('.promo-reason-pct').forEach(input => {
        percents[input.dataset.reason] = input.value;
    });

    runScanBtn.disabled = true;
    runScanBtn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Scanning…';

    try {
        const res = await fetch('promotions_api.php?action=scan', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ percents }),
        });
        const data = await res.json();

        if (!res.ok || !data.ok) {
            scanResult.className = 'promo-scan-result promo-scan-error';
            scanResult.textContent = data.message || 'Scan failed. Please try again.';
            scanResult.style.display = 'block';
            return;
        }

        const lines = Object.values(data.summary).map(s =>
            `${s.label}: ${s.count} product${s.count === 1 ? '' : 's'} at ${s.percent}% off`
        );
        scanResult.className = 'promo-scan-result promo-scan-ok';
        scanResult.innerHTML = '<i class="fa-solid fa-circle-check"></i> Scan complete — ' + lines.join(' · ');
        scanResult.style.display = 'block';

        // Reload shortly after so the promotions table below reflects the
        // scan results (new/updated rows, product counts, etc.).
        setTimeout(() => window.location.reload(), 5000);
    } catch (err) {
        scanResult.className = 'promo-scan-result promo-scan-error';
        scanResult.textContent = "Couldn't reach the server. Please try again.";
        scanResult.style.display = 'block';
        console.error('Promotion scan failed:', err);
    } finally {
        runScanBtn.disabled = false;
        runScanBtn.innerHTML = '<i class="fa-solid fa-bolt"></i> Run Analytics Scan';
    }
});
