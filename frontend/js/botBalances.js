/**
 * Module for working with bot balances
 */
const BotBalances = {
    /**
     * Initialization of the module
     */
    init() {
        // Add event handlers
        document.getElementById('refresh-balances-btn').addEventListener('click', () => {
            this.loadBalances();
        });
        
        document.getElementById('top-up-form').addEventListener('submit', (e) => {
            e.preventDefault();
            this.topUpBalance();
        });
        
        // Initial load of balances
        this.loadBalances();
    },
    
    /**
     * Load bot balances
     */
    async loadBalances() {
        try {
            // Show loading
            document.getElementById('balances-loading').classList.remove('d-none');
            document.getElementById('balances-table').classList.add('d-none');
            document.getElementById('balances-error').classList.add('d-none');
            
            // Load balances
            const balances = await API.getBotBalances();
            
            // Display balances
            this.displayBalances(balances);
            
            // Hide loading
            document.getElementById('balances-loading').classList.add('d-none');
            document.getElementById('balances-table').classList.remove('d-none');
        } catch (error) {
            console.error('Error loading balances:', error);
            document.getElementById('balances-loading').classList.add('d-none');
            document.getElementById('balances-error').classList.remove('d-none');
            document.getElementById('balances-error-message').textContent = `Error loading balances: ${error.message}`;
        }
    },
    
    /**
     * Display balances in the table
     */
    displayBalances(balances) {
        const tableBody = document.getElementById('balances-list');
        tableBody.innerHTML = '';
        
        if (Object.keys(balances).length === 0) {
            const tr = document.createElement('tr');
            tr.innerHTML = '<td colspan="4" class="text-center">No balances found</td>';
            tableBody.appendChild(tr);
            return;
        }
        
        // Fill currency select options for top-up form
        const currencySelect = document.getElementById('top-up-currency');
        currencySelect.innerHTML = '<option value="">Select currency</option>';
        
        // Sort currencies alphabetically
        Object.entries(balances)
            .sort(([a], [b]) => a.localeCompare(b))
            .forEach(([currency, balance]) => {
                // Add row to table
                const available = parseFloat(balance.available);
                const freeze = parseFloat(balance.freeze);
                const total = available + freeze;
                
                const tr = document.createElement('tr');
                tr.innerHTML = `
                    <td>${currency}</td>
                    <td>${this.formatNumber(available)}</td>
                    <td>${this.formatNumber(freeze)}</td>
                    <td>${this.formatNumber(total)}</td>
                `;
                tableBody.appendChild(tr);
                
                // Add to select options
                const option = document.createElement('option');
                option.value = currency;
                option.textContent = currency;
                currencySelect.appendChild(option);
            });
    },
    
    /**
     * Format number for display
     */
    formatNumber(num) {
        if (num >= 1000000) {
            return num.toFixed(2);
        } else if (num >= 1) {
            return num.toFixed(8);
        } else {
            return num.toFixed(10);
        }
    },
    
    /**
     * Top up balance
     */
    async topUpBalance() {
        try {
            const currency = document.getElementById('top-up-currency').value;
            const amount = document.getElementById('top-up-amount').value;
            
            if (!currency || !amount || isNaN(amount) || parseFloat(amount) <= 0) {
                App.showAlert('danger', 'Please select a currency and enter a valid amount');
                return;
            }
            
            // Show loading
            const submitBtn = document.getElementById('top-up-submit');
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Processing...';
            
            // Top up balance
            await API.topUpBotBalance(currency, amount);
            
            // Show success message
            App.showAlert('success', `Successfully topped up ${amount} ${currency}`);
            
            // Reset form
            document.getElementById('top-up-form').reset();
            
            // Reload balances
            this.loadBalances();
        } catch (error) {
            console.error('Error topping up balance:', error);
            App.showAlert('danger', `Error topping up balance: ${error.message}`);
        } finally {
            // Reset button
            const submitBtn = document.getElementById('top-up-submit');
            submitBtn.disabled = false;
            submitBtn.textContent = 'Top Up';
        }
    }
};

// Initialize the module after the page is loaded
document.addEventListener('DOMContentLoaded', () => {
    // Module will be initialized by App.js
}); 