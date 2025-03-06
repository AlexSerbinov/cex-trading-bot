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
        this.toggleBotBtn = document.getElementById('toggle-bot-btn');
        this.editBotBtn = document.getElementById('edit-bot-btn');
        this.deleteBotBtn = document.getElementById('delete-bot-btn');
        this.backBtn = document.getElementById('back-from-details-btn');
        
        // Add event listeners
        this.backBtn.addEventListener('click', () => {
            App.showBotList();
        });
        
        this.toggleBotBtn.addEventListener('click', async () => {
            await this.toggleBotStatus();
        });
        
        this.editBotBtn.addEventListener('click', () => {
            App.showBotForm(this.currentBot.id);
        });
        
        this.deleteBotBtn.addEventListener('click', () => {
            App.showDeleteConfirmation(this.currentBot.id, this.currentBot.market);
        });
    },
    
    /**
     * Loading bot details
     */
    async loadBotDetails(id) {
        try {
            // Get the bot data
            const bot = await API.getBotById(id);
            this.currentBot = bot;
            
            // Display the bot details
            this.renderBotDetails(bot);
            
            // Update the toggle button
            this.updateToggleButton(bot);
            
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
     * Displaying bot details
     */
    renderBotDetails(bot) {
        // Format the dates
        const createdAt = this.formatDate(bot.created_at);
        const updatedAt = this.formatDate(bot.updated_at);
        
        // Create the HTML for the bot details
        const html = `
            <div class="bot-detail-row">
                <div class="bot-detail-label">ID:</div>
                <div class="bot-detail-value">${bot.id}</div>
            </div>
            <div class="bot-detail-row">
                <div class="bot-detail-label">Pair: <i class="bi bi-info-circle text-primary info-icon" data-bs-toggle="tooltip" title="Trading pair in format BASE_QUOTE (e.g., BTC_USDT). This defines which cryptocurrencies the bot will trade. The first currency (BASE) is what you're buying/selling, and the second (QUOTE) is what you're paying/receiving."></i></div>
                <div class="bot-detail-value">${bot.market}</div>
            </div>
            <div class="bot-detail-row">
                <div class="bot-detail-label">Exchange: <i class="bi bi-info-circle text-primary info-icon" data-bs-toggle="tooltip" title="The exchange from which the bot will copy the OrderBook. The bot retrieves the OrderBook from the selected exchange, taking, for example, the 15 closest buy and sell orders and placing them on our exchange. Additionally, parameters such as MarketGap and Price Percentage are applied to adjust the orders accordingly."></i></div>
                <div class="bot-detail-value">${bot.exchange}</div>
            </div>
            <div class="bot-detail-row">
                <div class="bot-detail-label">Status: <i class="bi bi-info-circle text-primary info-icon" data-bs-toggle="tooltip" title="Current status of the bot - Active (running) or Inactive (stopped). You can change the status using the Enable/Disable button."></i></div>
                <div class="bot-detail-value">
                    <span class="status-badge ${bot.isActive ? 'status-active' : 'status-inactive'}">
                        ${bot.isActive ? 'Active' : 'Inactive'}
                    </span>
                </div>
            </div>
            <div class="bot-detail-row">
                <div class="bot-detail-label">Minimum trade amount: <i class="bi bi-info-circle text-primary info-icon" data-bs-toggle="tooltip" title="Minimum amount of the base currency for each trade. The bot will randomly choose a trade amount between min and max values. Lower values create more frequent but smaller trades, while higher values create less frequent but larger trades. This affects the bot's trading volume and risk exposure."></i></div>
                <div class="bot-detail-value">${bot.settings.trade_amount_min}</div>
            </div>
            <div class="bot-detail-row">
                <div class="bot-detail-label">Maximum trade amount: <i class="bi bi-info-circle text-primary info-icon" data-bs-toggle="tooltip" title="Maximum amount of the base currency for each trade. The bot will randomly choose a trade amount between min and max values. Higher values allow for larger trades, which can create more significant price movements and higher trading volume. This should not exceed your available balance."></i></div>
                <div class="bot-detail-value">${bot.settings.trade_amount_max}</div>
            </div>
            <div class="bot-detail-row">
                <div class="bot-detail-label">Frequency from (sec): <i class="bi bi-info-circle text-primary info-icon" data-bs-toggle="tooltip" title="Minimum time interval (in seconds) between bot actions. The bot will randomly wait between min and max frequency values before performing the next action. Lower values create more frequent trading activity, which increases liquidity but may also increase fees and system load."></i></div>
                <div class="bot-detail-value">${bot.settings.frequency_from}</div>
            </div>
            <div class="bot-detail-row">
                <div class="bot-detail-label">Frequency to (sec): <i class="bi bi-info-circle text-primary info-icon" data-bs-toggle="tooltip" title="Maximum time interval (in seconds) between bot actions. The bot will randomly wait between min and max frequency values before performing the next action. Higher values create less frequent trading, which can make the bot's behavior more unpredictable and natural-looking."></i></div>
                <div class="bot-detail-value">${bot.settings.frequency_to}</div>
            </div>
            <div class="bot-detail-row">
                <div class="bot-detail-label">Price deviation (%): <i class="bi bi-info-circle text-primary info-icon" data-bs-toggle="tooltip" title="Maximum percentage deviation from the market price when creating orders. This parameter determines how far from the current market price the bot will place its orders. Higher values create a wider price range for orders, simulating more volatile market conditions. Lower values keep orders closer to the current market price, creating tighter spreads."></i></div>
                <div class="bot-detail-value">${bot.settings.price_factor}</div>
            </div>
            <div class="bot-detail-row">
                <div class="bot-detail-label">Market gap (%): <i class="bi bi-info-circle text-primary info-icon" data-bs-toggle="tooltip" title="Percentage step from the best price on the external exchange. This parameter controls the gap between the best buy and sell orders, thereby regulating the spread. A higher Market Gap creates a larger spread between buy and sell prices, which acts as a protective mechanism to control liquidity and reduce risks. For example, with a 1% Market Gap, if BTC is trading at $100,000, with best buy at $100,002 and best sell at $99,999, your bot will place orders at $99,002 for buying and $100,999 for selling, creating a wider spread."></i></div>
                <div class="bot-detail-value">${bot.settings.market_gap || 0.05}</div>
            </div>
            <div class="bot-detail-row">
                <div class="bot-detail-label">Created:</div>
                <div class="bot-detail-value">${createdAt}</div>
            </div>
            <div class="bot-detail-row">
                <div class="bot-detail-label">Updated:</div>
                <div class="bot-detail-value">${updatedAt}</div>
            </div>
        `;
        
        this.detailsElement.innerHTML = html;
        
        // Update the tooltips after displaying the details
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
            App.showAlert('info', `${isActive ? 'Disabling' : 'Enabling'} bot...`, false);
            
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
            second: '2-digit'
        });
    }
}; 