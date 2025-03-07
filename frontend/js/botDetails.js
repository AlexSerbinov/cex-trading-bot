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
            second: '2-digit',
        });
    },
};
