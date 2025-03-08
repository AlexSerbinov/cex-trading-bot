/**
 * Module for displaying the list of bots
 */
const BotList = {
    /**
     * Initialization of the module
     */
    init() {
        this.botListElement = document.getElementById('bot-list');
        this.tradeServerInfoElement = document.getElementById('trade-server-info');
        this.loadConfig();
        this.loadBots();
    },
    
    /**
     * Loading configuration
     */
    async loadConfig() {
        try {
            const config = await API.getConfig();
            if (config && config.tradeServerUrl) {
                this.tradeServerInfoElement.innerHTML = `Trade Server URL: <span class="text-primary">${config.tradeServerUrl}</span>`;
            }
        } catch (error) {
            console.error('Error loading config:', error);
            // Не показуємо помилку користувачу, просто логуємо
        }
    },
    
    /**
     * Loading the list of bots
     */
    async loadBots() {
        try {
            // Show the loading indicator
            this.botListElement.innerHTML = `
                <tr>
                    <td colspan="7" class="text-center">
                        <div class="spinner-border text-primary" role="status">
                            <span class="visually-hidden">Loading...</span>
                        </div>
                        <span class="ms-2">Loading bots...</span>
                    </td>
                </tr>
            `;
            
            // Get the list of bots
            console.log('Fetching bots...');
            const bots = await API.getAllBots();
            console.log('Received bots:', bots);
            
            // If there are no bots, show the message
            if (bots.length === 0) {
                this.botListElement.innerHTML = `
                    <tr>
                        <td colspan="7" class="text-center">No available bots</td>
                    </tr>
                `;
                return;
            }
            
            // Display the list of bots
            this.renderBots(bots);
        } catch (error) {
            // Show the error message
            console.error('Error in loadBots:', error);
            this.botListElement.innerHTML = `
                <tr>
                    <td colspan="7" class="text-center text-danger">
                        Error loading bots: ${error.message}
                    </td>
                </tr>
            `;
            App.showAlert('danger', `Error loading bots: ${error.message}`);
        }
    },
    
    /**
     * Displaying the list of bots
     */
    renderBots(bots) {
        // Clear the list
        this.botListElement.innerHTML = '';
        
        // Add each bot to the list
        bots.forEach(bot => {
            const row = document.createElement('tr');
            
            // Format the dates
            const createdAt = this.formatDate(bot.created_at);
            const updatedAt = this.formatDate(bot.updated_at);
            
            // Create the HTML for the row
            row.innerHTML = `
                <td>${bot.id}</td>
                <td>${bot.market}</td>
                <td>${bot.exchange}</td>
                <td>
                    <span class="status-badge ${bot.isActive ? 'status-active' : 'status-inactive'}">
                        ${bot.isActive ? 'active' : 'inactive'}
                    </span>
                </td>
                <td>${bot.settings.min_orders || 2} / ${bot.settings.max_orders || 4}
                    <i class="bi bi-info-circle text-primary info-icon" data-bs-toggle="tooltip" 
                       title="Minimum and maximum number of orders that the bot will maintain in the order book"></i>
                </td>
                <td>${bot.settings.trade_amount_min} / ${bot.settings.trade_amount_max}</td>
                <td>${bot.settings.frequency_from} / ${bot.settings.frequency_to}</td>
                <td>${bot.settings.price_factor}% / ${bot.settings.market_gap}%</td>
                <td>${bot.settings.market_maker_order_probability || 30}%
                    <i class="bi bi-info-circle text-primary info-icon" data-bs-toggle="tooltip" 
                       title="Probability of creating market maker orders"></i>
                </td>
                <td>
                    <div class="btn-group" role="group">
                        <button type="button" class="btn btn-sm btn-primary view-bot" data-id="${bot.id}">
                            <i class="bi bi-eye"></i>
                        </button>
                        <button type="button" class="btn btn-sm btn-success ${bot.isActive ? 'd-none' : ''} enable-bot" data-id="${bot.id}">
                            <i class="bi bi-play-fill"></i>
                        </button>
                        <button type="button" class="btn btn-sm btn-warning ${!bot.isActive ? 'd-none' : ''} disable-bot" data-id="${bot.id}">
                            <i class="bi bi-pause-fill"></i>
                        </button>
                        <button type="button" class="btn btn-sm btn-danger delete-bot" data-id="${bot.id}" data-market="${bot.market}">
                            <i class="bi bi-trash"></i>
                        </button>
                    </div>
                </td>
            `;
            
            // Add event handlers for the buttons
            const viewBtn = row.querySelector('.view-bot');
            viewBtn.addEventListener('click', () => {
                App.showBotDetails(bot.id);
            });
            
            const enableBtn = row.querySelector('.enable-bot');
            enableBtn.addEventListener('click', async (e) => {
                e.stopPropagation();
                await this.toggleBotStatus(bot.id, true);
            });
            
            const disableBtn = row.querySelector('.disable-bot');
            disableBtn.addEventListener('click', async (e) => {
                e.stopPropagation();
                await this.toggleBotStatus(bot.id, false);
            });
            
            const deleteBtn = row.querySelector('.delete-bot');
            deleteBtn.addEventListener('click', (e) => {
                e.stopPropagation();
                App.showDeleteConfirmation(bot.id, bot.market);
            });
            
            // Add the row to the table
            this.botListElement.appendChild(row);
        });
        
        // Initialize tooltips
        setTimeout(() => {
            App.initTooltips();
        }, 100);
    },
    
    /**
     * Changing the bot status (enable/disable)
     */
    async toggleBotStatus(id, enable) {
        try {
            // Show the loading indicator
            App.showAlert('info', `${enable ? 'Enabling' : 'Disabling'} bot...`, false);
            
            // Change the bot status
            if (enable) {
                await API.enableBot(id);
            } else {
                await API.disableBot(id);
            }
            
            // Update the list of bots
            await this.loadBots();
            
            // Show the success message
            App.showAlert('success', `Bot successfully ${enable ? 'enabled' : 'disabled'}`);
        } catch (error) {
            // Show the error message
            App.showAlert('danger', `Error ${enable ? 'enabling' : 'disabling'} bot: ${error.message}`);
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