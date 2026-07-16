function viewDeductions(transactionId, txnCode) {
            const modal = document.getElementById('deductionsModal');
            const container = document.getElementById('modalContainer');
            const modalTxnCode = document.getElementById('modalTxnCode');
            const rowsContainer = document.getElementById('modalItemRows');
            
            modalTxnCode.innerText = `Transaction Code: ${txnCode}`;
            rowsContainer.innerHTML = '<tr><td colspan="5" class="p-4 text-center text-gray-500"><i class="fa-solid fa-spinner animate-spin"></i> Fetching records...</td></tr>';
            
            // Show modal backdrop
            modal.classList.remove('hidden');
            setTimeout(() => {
                container.classList.remove('scale-95', 'opacity-0');
                container.classList.add('scale-100', 'opacity-100');
            }, 10);

            // Fetch dynamic items via self-directed AJAX call
            fetch(`transaction_report.php?action=get_items&transaction_id=${transactionId}`)
                .then(response => response.json())
                .then(items => {
                    rowsContainer.innerHTML = '';
                    if(items.length === 0) {
                        rowsContainer.innerHTML = '<tr><td colspan="5" class="p-4 text-center text-gray-400">No item details recorded.</td></tr>';
                        return;
                    }

                    items.forEach(item => {
                        const row = `
                            <tr class="hover:bg-gray-50">
                                <td class="py-3 font-mono text-xs text-gray-500">${item.product_sku || 'N/A'}</td>
                                <td class="py-3 font-semibold text-gray-800">${item.product_name}</td>
                                <td class="py-3 text-right">₱${parseFloat(item.unit_price).toFixed(2)}</td>
                                <td class="py-3 text-center text-red-600 font-bold">- ${item.quantity}</td>
                                <td class="py-3 text-right font-bold text-gray-900">₱${parseFloat(item.line_total).toFixed(2)}</td>
                            </tr>
                        `;
                        rowsContainer.innerHTML += row;
                    });
                })
                .catch(err => {
                    rowsContainer.innerHTML = '<tr><td colspan="5" class="p-4 text-center text-red-500">Error fetching item details.</td></tr>';
                });
        }

        function closeModal() {
            const modal = document.getElementById('deductionsModal');
            const container = document.getElementById('modalContainer');
            
            container.classList.remove('scale-100', 'opacity-100');
            container.classList.add('scale-95', 'opacity-0');
            setTimeout(() => {
                modal.classList.add('hidden');
            }, 300);
        }
        
        // Close modal when clicking outside of it
        window.onclick = function(event) {
            const modal = document.getElementById('deductionsModal');
            if (event.target == modal) {
                closeModal();
            }
        }