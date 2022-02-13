<div class="wrap wpsms-wrap">
	<?php $option = get_option( 'wpsms_settings' ); ?>
    <div class="wpsms-header-banner">
		<?php if (! is_plugin_active( 'wp-sms-pro/wp-sms-pro.php' )) : ?>
        <div class="license-status license-status--free">
            <h3><a href="https://wp-sms-pro.com/" target="_blank">Get Pro Pack!</a></h3>
            <span>You are using the free version, to enable the premium features, get the pro pack version.</span>
        </div>
		<?php elseif (isset( $option['license_wp-sms-pro_status'] ) and $option['license_wp-sms-pro_status']) : ?>
        <div class="license-status license-status--valid">
            <h3>Pro License</h3>
            <span>Your license is enabled</span>
        </div>
		<?php else : ?>
        <div class="license-status license-status--invalid">
            <h3>Pro License</h3>
            <span>Your license is not enabled</span>
        </div>
		<?php endif; ?>
    </div>

    <div class="wpsms-wrap__main">
        <div class="wrap wpsms-two-way-inbox-page">
            <div class="info">
                <h2 class="page-title">{{__('Inbox (Incoming Messages)', 'wp-sms-two-way')}}</h2>
                @if(isset($_REQUEST['s']))
                    <p class="search-label">Search results for: <strong>{{$_REQUEST['s']}}</strong> |
                        <a class="see-all" href="{{$_SERVER['REQUEST_URI']}}">See all messages</a></p>
                @endif
            </div>
            <form method="post">
                <div class="searchbox">
                    @php
                        wp_referer_field();
                        $table->search_box(__('Search', 'wp-sms-two-way'), 'messages');
                    @endphp
                </div>
            </form>
            <form method="post">
                <div class="wpsms-tw-inbox-table">
                    @php $table->display(); @endphp
                </div>
            </form>
        </div>
    </div>
</div>
