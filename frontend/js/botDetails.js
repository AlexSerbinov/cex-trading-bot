/**
 * Module for displaying bot details
 */
const BotDetails = {
    /**
     * Initialization of the module
     */
    init() {
        this.detailsContainer = document.getElementById('bot-details-container');
        this.detailsElement = document.getElementById('bot-details');
        this.balancesElement = document.getElementById('bot-balances-details');
        this.toggleBotBtn = document.getElementById('toggle-bot-btn');
        this.editBotBtn = document.getElementById('edit-bot-btn');
        this.deleteBotBtn = document.getElementById('delete-bot-btn');
        this.backBtn = document.getElementById('back-from-details-btn');
        this.balanceUpdateTimer = null;

        // Add event listeners
        this.backBtn.addEventListener('click', () => {
            // Clear the balance update timer when leaving the page
            this.clearBalanceUpdateTimer();
            App.showBotList();
        });

        this.toggleBotBtn.addEventListener('click', async () => {
            await this.toggleBotStatus();
        });

        this.editBotBtn.addEventListener('click', () => {
            // Clear the balance update timer when editing
            this.clearBalanceUpdateTimer();
            App.showBotForm(this.currentBot.id);
        });

        this.deleteBotBtn.addEventListener('click', () => {
            App.showDeleteConfirmation(this.currentBot.id, this.currentBot.market);
        });
    },

    /**
     * Clear balance update timer
     */
    clearBalanceUpdateTimer() {
        if (this.balanceUpdateTimer) {
            clearInterval(this.balanceUpdateTimer);
            this.balanceUpdateTimer = null;
        }
    },

    /**
     * Loading bot details
     */
    async loadBotDetails(id) {
        try {
            // Clear previous balance update timer if exists
            this.clearBalanceUpdateTimer();
            
            // Get the bot data
            const bot = await API.getBotById(id);
            this.currentBot = bot;

            // Display the bot details
            this.renderBotDetails(bot);

            // Update the toggle button
            this.updateToggleButton(bot);

            // Initialize balances updates
            await this.loadAndRenderBalances();
            
            // Set up interval for balance updates (every 1 second)
            this.balanceUpdateTimer = setInterval(async () => {
                await this.loadAndRenderBalances();
            }, 1000);

            // Initialize the tooltips
            setTimeout(() => {
                App.initTooltips();
            }, 100);
        } catch (error) {
            // Show the error message
            App.showAlert('danger', `Error loading bot details: ${error.message}`);
        }
    },

    /**
     * Load and render balances for the current bot
     */
    async loadAndRenderBalances() {
        try {
            if (!this.currentBot || !this.currentBot.market) return;
            
            // Extract base and quote currencies from the market pair
            const [baseCurrency, quoteCurrency] = this.currentBot.market.split('_');
            
            // Get all balances
            const balances = await API.getBotBalances();
            
            // Create balance display for both currencies
            let balanceHtml = `
                <div class="card mt-4">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">Bot Balances</h5>
                        <span class="badge bg-info">Live Updates</span>
                    </div>
                    <div class="card-body">
                        <div class="alert alert-warning mb-3">
                            <i class="bi bi-exclamation-triangle-fill me-2"></i>
                            <strong>Note:</strong> Balances can change even if this bot is stopped. This happens when other active bots are trading the same currencies (e.g., if BTC is used in pairs BTC_USDT, BTC_ETH, BTC_USDC).
                        </div>
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Currency</th>
                                        <th>Available</th>
                                        <th>Frozen</th>
                                        <th>Total</th>
                                    </tr>
                                </thead>
                                <tbody>
            `;
            
            // Add base currency balance
            if (balances[baseCurrency]) {
                const baseAvailable = parseFloat(balances[baseCurrency].available);
                const baseFrozen = parseFloat(balances[baseCurrency].freeze);
                const baseTotal = baseAvailable + baseFrozen;
                
                balanceHtml += `
                    <tr>
                        <td><strong>${baseCurrency}</strong></td>
                        <td>${this.formatNumber(baseAvailable)}</td>
                        <td>${this.formatNumber(baseFrozen)}</td>
                        <td>${this.formatNumber(baseTotal)}</td>
                    </tr>
                `;
            } else {
                balanceHtml += `
                    <tr>
                        <td><strong>${baseCurrency}</strong></td>
                        <td colspan="3">No balance data available</td>
                    </tr>
                `;
            }
            
            // Add quote currency balance
            if (balances[quoteCurrency]) {
                const quoteAvailable = parseFloat(balances[quoteCurrency].available);
                const quoteFrozen = parseFloat(balances[quoteCurrency].freeze);
                const quoteTotal = quoteAvailable + quoteFrozen;
                
                balanceHtml += `
                    <tr>
                        <td><strong>${quoteCurrency}</strong></td>
                        <td>${this.formatNumber(quoteAvailable)}</td>
                        <td>${this.formatNumber(quoteFrozen)}</td>
                        <td>${this.formatNumber(quoteTotal)}</td>
                    </tr>
                `;
            } else {
                balanceHtml += `
                    <tr>
                        <td><strong>${quoteCurrency}</strong></td>
                        <td colspan="3">No balance data available</td>
                    </tr>
                `;
            }
            
            balanceHtml += `
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            `;
            
            // Update the balances element
            this.balancesElement.innerHTML = balanceHtml;
            
        } catch (error) {
            console.error('Error loading balances:', error);
            this.balancesElement.innerHTML = `
                <div class="card mt-4">
                    <div class="card-header">
                        <h5 class="mb-0">Bot Balances</h5>
                    </div>
                    <div class="card-body">
                        <div class="alert alert-danger">
                            Error loading balances: ${error.message}
                        </div>
                    </div>
                </div>
            `;
        }
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
     * Displaying bot details
     */
    renderBotDetails(bot) {
        // Format the dates
        const createdAt = this.formatDate(bot.created_at);
        const updatedAt = this.formatDate(bot.updated_at);

        // Create the HTML for the bot details
        this.detailsElement.innerHTML = `
            <div class="bot-detail-row">
                <div class="bot-detail-label">ID:</div>
                <div class="bot-detail-value">${bot.id}</div>
            </div>
            <div class="bot-detail-row">
                <div class="bot-detail-label">Trading pair:</div>
                <div class="bot-detail-value">${bot.market}</div>
            </div>
            <div class="bot-detail-row">
                <div class="bot-detail-label">Exchange:</div>
                <div class="bot-detail-value">${bot.exchange}</div>
            </div>
            <div class="bot-detail-row">
                <div class="bot-detail-label">Status:</div>
                <div class="bot-detail-value">
                    <span class="status-badge ${bot.isActive ? 'status-active' : 'status-inactive'}">
                        ${bot.isActive ? 'active' : 'inactive'}
                    </span>
                </div>
            </div>
            <div class="bot-detail-row">
                <div class="bot-detail-label">Min/Max orders:</div>
                <div class="bot-detail-value">
                    ${bot.settings.min_orders || 2} / ${bot.settings.max_orders || 4}
                    <i class="bi bi-info-circle text-primary info-icon" data-bs-toggle="tooltip" 
                       title="Minimum and maximum number of orders that the bot will maintain in the order book"></i>
                </div>
            </div>
            <div class="bot-detail-row">
                <div class="bot-detail-label">Trade amount (min/max):</div>
                <div class="bot-detail-value">${bot.settings.trade_amount_min} / ${bot.settings.trade_amount_max}</div>
            </div>
            <div class="bot-detail-row">
                <div class="bot-detail-label">Frequency (min/max):</div>
                <div class="bot-detail-value">${bot.settings.frequency_from} / ${bot.settings.frequency_to} seconds</div>
            </div>
            <div class="bot-detail-row">
                <div class="bot-detail-label">Price deviation:</div>
                <div class="bot-detail-value">${bot.settings.price_factor}%</div>
            </div>
            <div class="bot-detail-row">
                <div class="bot-detail-label">Market gap:</div>
                <div class="bot-detail-value">${bot.settings.market_gap}%</div>
            </div>
            <div class="bot-detail-row">
                <div class="bot-detail-label">Market maker order probability:</div>
                <div class="bot-detail-value">${bot.settings.market_maker_order_probability}%</div>
            </div>
            <div class="bot-detail-row">
                <div class="bot-detail-label">Created at:</div>
                <div class="bot-detail-value">${createdAt}</div>
            </div>
            <div class="bot-detail-row">
                <div class="bot-detail-label">Updated at:</div>
                <div class="bot-detail-value">${updatedAt}</div>
            </div>
        `;

        // Initialize the tooltips
        setTimeout(() => {
            App.initTooltips();
        }, 100);
    },

    /**
     * Updating the enable/disable button
     */
    updateToggleButton(bot) {
        if (bot.isActive) {
            this.toggleBotBtn.classList.remove('btn-success');
            this.toggleBotBtn.classList.add('btn-warning');
            this.toggleBotBtn.innerHTML = '<i class="bi bi-pause-fill"></i> Disable';
        } else {
            this.toggleBotBtn.classList.remove('btn-warning');
            this.toggleBotBtn.classList.add('btn-success');
            this.toggleBotBtn.innerHTML = '<i class="bi bi-play-fill"></i> Enable';
        }
    },

    /**
     * Changing the bot status (enable/disable)
     */
    async toggleBotStatus() {
        try {
            const isActive = this.currentBot.isActive;

            // Show the loading indicator
            App.showAlert('info', `${isActive ? 'Disabling' : 'Enabling'} bot...`, true);

            // Change the bot status
            if (isActive) {
                await API.disableBot(this.currentBot.id);
            } else {
                await API.enableBot(this.currentBot.id);
            }

            // Update the bot details
            await this.loadBotDetails(this.currentBot.id);

            // Show the success message
            App.showAlert('success', `Bot successfully ${isActive ? 'disabled' : 'enabled'}`);
        } catch (error) {
            // Show the error message
            App.showAlert('danger', `Error changing bot status: ${error.message}`);
        }
    },

    /**
     * Formatting the date
     */
    formatDate(dateString) {
        const date = new Date(dateString.replace(' ', 'T'));
        return date.toLocaleString('en-US', {
            year: 'numeric',
            month: '2-digit',
            day: '2-digit',
            hour: '2-digit',
            minute: '2-digit',
            second: '2-digit',
        });
    },
};
