/**
 * Main application module
 */
const App = {
    /**
     * Initialization of the application
     */
    init() {
        // Initialize modules
        BotList.init();
        BotDetails.init();
        BotForm.init();
        
        // Add event handlers
        document.getElementById('create-bot-btn').addEventListener('click', () => {
            this.showBotForm();
        });
        
        document.getElementById('nav-bots').addEventListener('click', (e) => {
            e.preventDefault();
            this.showBotList();
        });
        
        // Initialize the delete confirmation modal
        this.deleteModal = new bootstrap.Modal(document.getElementById('delete-confirm-modal'));
        document.getElementById('confirm-delete-btn').addEventListener('click', async () => {
            await this.deleteBot();
        });
        
        // Initialize tooltips
        this.initTooltips();
        
        // Show the list of bots
        this.showBotList();
    },
    
    /**
     * Initialization of tooltips
     */
    initTooltips() {
        const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
        tooltipTriggerList.map(function (tooltipTriggerEl) {
            return new bootstrap.Tooltip(tooltipTriggerEl, {
                html: true,
                placement: 'right',
                container: 'body'
            });
        });
    },
    
    /**
     * Show the list of bots
     */
    showBotList() {
        // Hide other containers
        document.getElementById('bot-details-container').classList.add('d-none');
        document.getElementById('bot-form-container').classList.add('d-none');
        
        // Show the list of bots
        document.getElementById('bot-list-container').classList.remove('d-none');
        
        // Update the list of bots
        BotList.loadBots();
    },
    
    /**
     * Show the bot details
     */
    showBotDetails(id) {
        // Hide other containers
        document.getElementById('bot-list-container').classList.add('d-none');
        document.getElementById('bot-form-container').classList.add('d-none');
        
        // Show the bot details
        document.getElementById('bot-details-container').classList.remove('d-none');
        
        // Load the bot details
        BotDetails.loadBotDetails(id);
    },
    
    /**
     * Show the bot creation/editing form
     */
    showBotForm(id = null) {
        // Hide other containers
        document.getElementById('bot-list-container').classList.add('d-none');
        document.getElementById('bot-details-container').classList.add('d-none');
        
        // Show the form
        document.getElementById('bot-form-container').classList.remove('d-none');
        
        // Clear the form
        BotForm.clearForm();
        
        // Load available pairs
        BotForm.loadAvailablePairs();
        
        // If an ID is passed, load the bot data for editing
        if (id) {
            BotForm.loadBotForEdit(id);
        }
    },
    
    /**
     * Show the delete confirmation
     */
    showDeleteConfirmation(id, market) {
        this.botToDelete = { id, market };
        document.getElementById('delete-bot-name').textContent = market;
        this.deleteModal.show();
    },
    
    /**
     * Delete a bot
     */
    async deleteBot() {
        try {
            // Close the modal
            this.deleteModal.hide();
            
            // Show the loading indicator
            this.showAlert('info', `Deleting bot ${this.botToDelete.market}...`, false);
            
            // Delete the bot
            await API.deleteBot(this.botToDelete.id);
            
            // Show the success message
            this.showAlert('success', `Bot ${this.botToDelete.market} successfully deleted`);
            
            // Return to the list of bots
            this.showBotList();
        } catch (error) {
            // Show the error message
            this.showAlert('danger', `Error deleting bot: ${error.message}`);
        }
    },
    
    /**
     * Show the notification
     */
    showAlert(type, message, autoHide = true) {
        // Create the notification element
        const alertElement = document.createElement('div');
        alertElement.className = `alert alert-${type} alert-dismissible fade show`;
        alertElement.innerHTML = `
            ${message}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        `;
        
        // Add the notification to the page
        const alertsContainer = document.getElementById('alerts-container');
        alertsContainer.appendChild(alertElement);
        
        // Automatically hide the notification after 5 seconds
        if (autoHide) {
            setTimeout(() => {
                alertElement.classList.remove('show');
                setTimeout(() => {
                    alertElement.remove();
                }, 150);
            }, 5000);
        }
        
        return alertElement;
    }
};

// Initialize the application after the page is loaded
document.addEventListener('DOMContentLoaded', () => {
    App.init();
}); 