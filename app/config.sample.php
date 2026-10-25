<?php
/**
 * Copy this file to config.php to override defaults.
 * config.php is ignored by git, so each environment can have its own.
 */

return [
    // Path to WordPress's wp-load.php. The default (parent folder) works when
    // the app sits inside the WordPress root, e.g. unstrucksomatics.com/app.
    // For a subdomain later, point this at the main site's WordPress folder:
    // 'wp_load_path' => '/var/www/unstrucksomatics.com/wp-load.php',

    // Timezone given to new users until they choose their own.
    'default_timezone' => 'America/Chicago',
];
