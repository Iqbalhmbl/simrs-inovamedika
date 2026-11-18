<?php

return [
    'adminEmail' => 'admin@example.com',
    'senderEmail' => 'noreply@example.com',
    'senderName' => 'SIMRS Inova Medika',

    // JWT secret for API auth (components/JwtHttpBearerAuth.php, controllers/AuthController.php).
    // Override via the JWT_SECRET env var in production; this default is only for local/dev use.
    'jwtSecret' => getenv('JWT_SECRET') ?: 'dev-only-change-me-in-production-simrs-2026',
    'jwtIssuer' => 'simrs-yii',
];
