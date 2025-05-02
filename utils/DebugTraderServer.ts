import axios from 'axios';
import chalk from 'chalk';

// Configuration (Consider using environment variables or command-line args for flexibility)
// const TRADE_SERVER_URL = 'http://195.7.7.93:18080'; // 93 demo
const TRADE_SERVER_URL = 'http://164.68.117.90:18080'; // 90 dev
const USER_ID = 5;

/**
 * Makes a single request to the balance.query endpoint and logs the result or error.
 */
async function debugGetBalances() {
    console.log(chalk.cyan(`Attempting to fetch balance from ${TRADE_SERVER_URL} for User ID: ${USER_ID}...`));

    try {
        const requestPayload = {
            method: "balance.query",
            params: [USER_ID],
            id: Date.now() // Use timestamp for unique ID
        };

        console.log(chalk.yellow('Sending request payload:'));
        console.log(JSON.stringify(requestPayload, null, 2));

        const response = await axios.post(TRADE_SERVER_URL, requestPayload, {
            timeout: 10000 // Add a timeout (e.g., 10 seconds)
        });

        console.log(chalk.green('\nReceived successful response:'));
        console.log(JSON.stringify(response.data, null, 2));

    } catch (error: any) {
        console.error(chalk.red('\nRequest failed:'));

        if (axios.isAxiosError(error)) {
            // Detailed Axios error information
            console.error(chalk.red(`Error Message: ${error.message}`));
            if (error.response) {
                // The request was made and the server responded with a status code
                // that falls out of the range of 2xx
                console.error(chalk.red(`Status Code: ${error.response.status}`));
                console.error(chalk.red('Response Headers:'));
                console.log(JSON.stringify(error.response.headers, null, 2));
                console.error(chalk.red('Response Data:'));
                console.log(JSON.stringify(error.response.data, null, 2));
            } else if (error.request) {
                // The request was made but no response was received
                console.error(chalk.red('No response received from server.'));
                // console.error(error.request); // Can be verbose
            } else {
                // Something happened in setting up the request that triggered an Error
                console.error(chalk.red('Error setting up request:'), error.message);
            }
            console.error(chalk.red('Axios Config:'));
            console.log(JSON.stringify(error.config, null, 2));
        } else {
            // Non-Axios error
            console.error(chalk.red('An unexpected error occurred:'), error);
        }
    }
}

// Run the debug function
debugGetBalances(); 