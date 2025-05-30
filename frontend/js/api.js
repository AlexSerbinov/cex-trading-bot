/**
 * Module for working with the API
 */
const API = {
    // Base API URL, obtained from configuration or using default value
    get baseUrl() {
        if (window.CONFIG && window.CONFIG.apiUrl) {
            return window.CONFIG.apiUrl;
        }
        return 'http://localhost:8080/api'; // Default value for local development
    },

    /**
     * Getting a list of all bots
     */
    async getAllBots() {
        try {
            const response = await fetch(`${this.baseUrl}/bots`);
            console.log('API Response:', response);
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            const data = await response.json();
            console.log('Bots data:', data);
            return data;
        } catch (error) {
            console.error('Error fetching bots:', error);
            throw error;
        }
    },

    /**
     * Getting a bot by ID
     */
    async getBotById(id) {
        try {
            const response = await fetch(`${this.baseUrl}/bots/${id}`);
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            return await response.json();
        } catch (error) {
            console.error(`Error fetching bot ${id}:`, error);
            throw error;
        }
    },

    /**
     * Creating a new bot
     */
    async createBot(botData) {
        try {
            const response = await fetch(`${this.baseUrl}/bots`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify(botData),
            });

            if (!response.ok) {
                const errorData = await response.json();
                throw new Error(errorData.error || `HTTP error! status: ${response.status}`);
            }

            return await response.json();
        } catch (error) {
            console.error('Error creating bot:', error);
            throw error;
        }
    },

    /**
     * Updating a bot
     */
    async updateBot(id, botData) {
        try {
            const response = await fetch(`${this.baseUrl}/bots/${id}`, {
                method: 'PUT',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify(botData),
            });

            if (!response.ok) {
                const errorData = await response.json();
                throw new Error(errorData.error || `HTTP error! status: ${response.status}`);
            }

            return await response.json();
        } catch (error) {
            console.error(`Error updating bot ${id}:`, error);
            throw error;
        }
    },

    /**
     * Deleting a bot
     */
    async deleteBot(id) {
        try {
            const response = await fetch(`${this.baseUrl}/bots/${id}`, {
                method: 'DELETE',
            });

            if (!response.ok) {
                const errorData = await response.json();
                throw new Error(errorData.error || `HTTP error! status: ${response.status}`);
            }

            return true;
        } catch (error) {
            console.error(`Error deleting bot ${id}:`, error);
            throw error;
        }
    },

    /**
     * Enabling a bot
     */
    async enableBot(id) {
        try {
            const response = await fetch(`${this.baseUrl}/bots/${id}/enable`, {
                method: 'PUT',
            });

            if (!response.ok) {
                const errorData = await response.json();
                throw new Error(errorData.error || `HTTP error! status: ${response.status}`);
            }

            return await response.json();
        } catch (error) {
            console.error(`Error enabling bot ${id}:`, error);
            throw error;
        }
    },

    /**
     * Disabling a bot
     */
    async disableBot(id) {
        try {
            const response = await fetch(`${this.baseUrl}/bots/${id}/disable`, {
                method: 'PUT',
            });

            if (!response.ok) {
                const errorData = await response.json();
                throw new Error(errorData.error || `HTTP error! status: ${response.status}`);
            }

            return await response.json();
        } catch (error) {
            console.error(`Error disabling bot ${id}:`, error);
            throw error;
        }
    },

    /**
     * Getting configuration
     */
    async getConfig() {
        try {
            const response = await fetch(`${this.baseUrl}/config`);
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            return await response.json();
        } catch (error) {
            console.error('Error fetching config:', error);
            throw error;
        }
    },

    /**
     * Get list of available pairs
     */
    async getAvailablePairs() {
        try {
            const response = await fetch(`${this.baseUrl}/pairs`);
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            return await response.json();
        } catch (error) {
            console.error('Error fetching available pairs:', error);
            throw error;
        }
    },
    
    /**
     * Get bot balances
     */
    async getBotBalances() {
        try {
            const response = await fetch(`${this.baseUrl}/balances`);
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            return await response.json();
        } catch (error) {
            console.error('Error fetching balances:', error);
            throw error;
        }
    },
    
    /**
     * Top up bot balance
     */
    async topUpBotBalance(currency, amount) {
        try {
            const response = await fetch(`${this.baseUrl}/balances/topup`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({ currency, amount }),
            });

            if (!response.ok) {
                const errorData = await response.json();
                throw new Error(errorData.error || `HTTP error! status: ${response.status}`);
            }

            return await response.json();
        } catch (error) {
            console.error('Error topping up balance:', error);
            throw error;
        }
    },
};
