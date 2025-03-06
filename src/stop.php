<?php
// Script for stopping bots
require_once __DIR__ . '/Logger.php';

$logger = new Logger();

// Loading the PIDs of running bots
if (!file_exists(__DIR__ . '/running_bots.json')) {
    $logger->log('No running bots');
    exit(0);
}

$processes = json_decode(file_get_contents(__DIR__ . '/running_bots.json'), true);

foreach ($processes as $pair => $pid) {
    // Stopping the process
    exec("kill $pid");
    $logger->log(sprintf('Stopped bot for pair %s (PID: %s)', $pair, $pid));
}

// Deleting the PIDs file
unlink(__DIR__ . '/running_bots.json');
$logger->log('All bots stopped'); 