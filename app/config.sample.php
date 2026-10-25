<?php
/**
 * Copy this file to config.php to override defaults: config.php is ignored by git.
 */

return [
    // When using WP to handle auth and DB connectors, this is the path to wp-load.php. 
    // The default (parent folder) works when the app sits inside the WordPress root
    // For a subdomain later, point this at the main site's WordPress folder:
    #'wp_load_path' => '/var/www/.../wp-load.php',

    // Contact email shown on the Terms & Privacy page.
    'contact_email' => 'yourname@email.com',

    // Timezone given to new users until they choose their own.
    'default_timezone' => 'America/Chicago',

    // Link to this app's source code, shown on the Terms & Privacy page. If you
    // run a modified version, point this to your modified source (AGPL section 13).
    #'source_url' => 'https://github.com/your-name/your-fork',

    // By default, the footer links to the app's own Terms & Privacy page
    // (legal.php, generated from LEGAL.md). Set this to link elsewhere instead.
    #'legal_url' => 'https://yoursite.com/terms',

    // The app icon comes from assets/icon-192.png. Without that file, set a
    // URL here (e.g. your WordPress site icon); without either, a drawn lotus.
    #'icon_url' => 'https://yoursite.com/wp-content/uploads/site-icon-192x192.png',
];
