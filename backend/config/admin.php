<?php

/**
 * DEPRECATED: This configuration file is deprecated.
 * Admin permissions are now managed through the database using the 'role' column in the users table.
 * To grant admin access, set user's role to 'admin' in the database.
 * This file is kept for backward compatibility but is no longer used by IsAdmin middleware.
 */

return [
    'emails' => [
        // DEPRECATED: Use database role instead
        // 'admin@aprovaai.com',
        // 'admin@example.com',
    ],
];
