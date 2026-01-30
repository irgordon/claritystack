<?php
// tests/reproduce_installer.php

// Adjust path to point to the controller
$controllerPath = __DIR__ . '/../clarity_app/api/controllers/InstallController.php';

if (!file_exists($controllerPath)) {
    die("Controller not found at $controllerPath\n");
}

require_once $controllerPath;

echo "InstallController loaded successfully.\n";

$controller = new InstallController();
echo "Instance created.\n";

// Use Reflection to test private method checkRequirements
try {
    $reflection = new ReflectionClass($controller);
    $method = $reflection->getMethod('checkRequirements');
    $method->setAccessible(true);

    echo "Testing checkRequirements()...\n";
    $method->invoke($controller);
    echo "checkRequirements() passed.\n";
} catch (ReflectionException $e) {
    echo "Reflection Error: " . $e->getMessage() . "\n";
} catch (Exception $e) {
    echo "checkRequirements() failed (EXPECTED if env missing, UNEXPECTED if env is good): " . $e->getMessage() . "\n";
    // Depending on the environment, this might fail.
    // If it fails, we know the check is working!
    // If it passes, we know the environment is good.
}
