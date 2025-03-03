<?php
// Script for stopping bots
require_once __DIR__ . '/Logger.php';

$logger = new Logger();

// Load PIDs of running bots
if (!file_exists(__DIR__ . '/running_bots.json')) {
    $logger->log('No running bots found');
    exit(0);
}

$processes = json_decode(file_get_contents(__DIR__ . '/running_bots.json'), true);

foreach ($processes as $pair => $pid) {
    // Stop the process
    exec("kill $pid");
    $logger->log(sprintf('Stopped bot for pair %s (PID: %s)', $pair, $pid));
}

// Remove the PIDs file
unlink(__DIR__ . '/running_bots.json');
$logger->log('All bots stopped'); 