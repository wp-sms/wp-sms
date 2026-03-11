<?php
defined('ABSPATH') || exit;

get_header();
?>
<div id="wsms-auth" data-route="<?php echo esc_attr(get_query_var('wsms_auth_route', '')); ?>"></div>
<?php
get_footer();
