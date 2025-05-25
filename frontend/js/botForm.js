/**
 * Module for creating/editing a bot form
 */
const BotForm = {
    /**
     * Initialization of the module
     */
    init() {
        this.formContainer = document.getElementById('bot-form-container');
        this.form = document.getElementById('bot-form');
        this.formTitle = document.getElementById('form-title');
        this.backBtn = document.getElementById('back-to-list-btn');
        this.marketSelect = document.getElementById('market');
        
        // Add event listeners
        this.backBtn.addEventListener('click', () => {
            App.showBotList();
        });
        
        this.form.addEventListener('submit', async (e) => {
            e.preventDefault();
            await this.saveBot();
        });
        
        // Load available pairs when the form is shown
        this.loadAvailablePairs();
    },
    
    /**
     * Clearing the form
     */
    clearForm() {
        this.form.reset();
        this.currentBotId = null;
        this.formTitle.textContent = 'Creating a new bot';
        
        // Update the tooltips after displaying the form
        setTimeout(() => {
            App.initTooltips();
        }, 100);
    },
    
    /**
     * Loading the bot data for editing
     */
    async loadBotForEdit(id) {
        try {
            // Get the bot data
            const bot = await API.getBotById(id);
            this.currentBotId = id;
            
            // Make sure pairs are loaded before setting the value
            await this.loadAvailablePairs();
            
            // Fill the form with the bot data
            this.form.market.value = bot.market;
            this.form.exchange.value = bot.exchange;
            this.form.min_orders.value = bot.settings.min_orders;
            this.form.trade_amount_min.value = bot.settings.trade_amount_min;
            this.form.trade_amount_max.value = bot.settings.trade_amount_max;
            this.form.frequency_from.value = bot.settings.frequency_from;
            this.form.frequency_to.value = bot.settings.frequency_to;
            this.form.price_factor.value = bot.settings.price_factor;
            this.form.market_gap.value = bot.settings.market_gap;
            // this.form.market_maker_order_probability.value = bot.settings.market_maker_order_probability;
            
            // Change the form title
            this.formTitle.textContent = `Editing bot ${bot.market}`;
            
            // Disable only the market field, allow exchange to be changed
            this.form.market.disabled = true;
            this.form.exchange.disabled = false;
        } catch (error) {
            // Show the error message
            App.showAlert('danger', `Error loading bot data: ${error.message}`);
        }
    },
    
    /**
     * Loading available trading pairs
     */
    async loadAvailablePairs() {
        try {
            const response = await API.getAvailablePairs();
            if (response && response.pairs && response.pairs.length > 0) {
                // Clear existing options except the first one
                while (this.marketSelect.options.length > 1) {
                    this.marketSelect.remove(1);
                }
                
                // Add new options
                response.pairs.forEach(pair => {
                    const option = document.createElement('option');
                    option.value = pair.name;
                    option.textContent = pair.name;
                    this.marketSelect.appendChild(option);
                });
            }
        } catch (error) {
            console.error('Error loading available pairs:', error);
            App.showAlert('warning', `Could not load available pairs: ${error.message}`);
        }
    },
    
    /**
     * Saving the bot
     */
    async saveBot() {
        try {
            // Get the data from the form
            const botData = {
                market: this.form.market.value,
                exchange: this.form.exchange.value,
                settings: {
                    min_orders: parseInt(this.form.min_orders.value),
                    // We use the same value for max_orders as there is only one "Orders" field in the UI now
                    trade_amount_min: parseFloat(this.form.trade_amount_min.value),
                    trade_amount_max: parseFloat(this.form.trade_amount_max.value),
                    frequency_from: parseInt(this.form.frequency_from.value),
                    frequency_to: parseInt(this.form.frequency_to.value),
                    price_factor: parseFloat(this.form.price_factor.value),
                    market_gap: parseFloat(this.form.market_gap.value),
                    // market_maker_order_probability: parseInt(this.form.market_maker_order_probability.value)
                }
            };
            
            // Show the loading indicator
            App.showAlert('info', 'Saving bot...', false);
            
            let bot;
            
            // Create or update the bot
            if (this.currentBotId) {
                bot = await API.updateBot(this.currentBotId, botData);
                App.showAlert('success', `Bot ${bot.market} successfully updated`);
            } else {
                bot = await API.createBot(botData);
                App.showAlert('success', `Bot ${bot.market} successfully created`);
            }
            
            // Return to the list of bots
            App.showBotList();
            
            // Update the list of bots
            BotList.loadBots();
        } catch (error) {
            // Show the error message
            App.showAlert('danger', `Error saving bot: ${error.message}`);
        }
    }
};
