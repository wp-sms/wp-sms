
<?php
/**
 * File: asset-dependencies.php
 * Purpose: Specifies dependencies and version for the WordPress block or script.
 */

return array(
    'dependencies' => array(
        'wp-block-editor', // Required for integrating with the block editor
        'wp-blocks',       // For registering and building custom blocks
        'wp-components',   // Access to WordPress UI components
        'wp-element',      // React abstraction layer
        'wp-i18n',         // For internationalization support
        'wp-polyfill'      // Ensures backward compatibility for older browsers
    ),
    'version' => '0329ab422101c3d49b7b', // Automatically generated version hash
);
