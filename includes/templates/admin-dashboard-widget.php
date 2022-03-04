<div class="inside">
    <div class="wp-mail-smtp-dash-widget wp-mail-smtp-dash-widget--lite">

        <div class="wp-mail-smtp-dash-widget-block wp-mail-smtp-dash-widget-block-settings">
            <div>
                <select id="wp-mail-smtp-dash-widget-email-type" class="wp-mail-smtp-dash-widget-select-email-type" title="Select email type">
                    <option value="all">
                        All Emails
                    </option>
                    <option value="delivered" disabled="">
                        Sent Emails
                    </option>
                    <option value="unsent" disabled="">
                        Failed Emails
                    </option>
                </select>

            </div>
            <div>
                <select id="wp-mail-smtp-dash-widget-timespan" class="wp-mail-smtp-dash-widget-select-timespan" title="Select timespan">
                    <option value="all">
                        All Time
                    </option>
                    <option value="7" disabled="">
                        Last 7 days
                    </option>
                    <option value="14" disabled="">
                        Last 14 days
                    </option>
                    <option value="30" disabled="">
                        Last 30 days
                    </option>
                </select>

                <div class="wp-mail-smtp-dash-widget-settings-container">
                    <button id="wp-mail-smtp-dash-widget-settings-button" class="wp-mail-smtp-dash-widget-settings-button button" type="button">
                        <span class="dashicons dashicons-admin-generic"></span>
                    </button>
                    <div class="wp-mail-smtp-dash-widget-settings-menu">
                        <div class="wp-mail-smtp-dash-widget-settings-menu--style">
                            <h4>Graph Style</h4>
                            <div>
                                <div class="wp-mail-smtp-dash-widget-settings-menu-item">
                                    <input type="radio" id="wp-mail-smtp-dash-widget-settings-style-bar" name="style" value="bar" disabled="">
                                    <label for="wp-mail-smtp-dash-widget-settings-style-bar">Bar</label>
                                </div>
                                <div class="wp-mail-smtp-dash-widget-settings-menu-item">
                                    <input type="radio" id="wp-mail-smtp-dash-widget-settings-style-line" name="style" value="line" checked="" disabled="">
                                    <label for="wp-mail-smtp-dash-widget-settings-style-line">Line</label>
                                </div>
                            </div>
                        </div>
                        <div class="wp-mail-smtp-dash-widget-settings-menu--color">
                            <h4>Color Scheme</h4>
                            <div>
                                <div class="wp-mail-smtp-dash-widget-settings-menu-item">
                                    <input type="radio" id="wp-mail-smtp-dash-widget-settings-color-smtp" name="color" value="smtp" disabled="">
                                    <label for="wp-mail-smtp-dash-widget-settings-color-smtp">WP Mail SMTP</label>
                                </div>
                                <div class="wp-mail-smtp-dash-widget-settings-menu-item">
                                    <input type="radio" id="wp-mail-smtp-dash-widget-settings-color-wp" name="color" value="wp" checked="" disabled="">
                                    <label for="wp-mail-smtp-dash-widget-settings-color-wp">WordPress</label>
                                </div>
                            </div>
                        </div>
                        <button type="button" class="button wp-mail-smtp-dash-widget-settings-menu-save" disabled="">Save Changes</button>
                    </div>
                </div>
            </div>
        </div>

        <div id="wp-mail-smtp-dash-widget-email-stats-block" class="wp-mail-smtp-dash-widget-block wp-mail-smtp-dash-widget-email-stats-block">

            <table id="wp-mail-smtp-dash-widget-email-stats-table" cellspacing="0">
                <tbody>
                <tr>
                    <td class="wp-mail-smtp-dash-widget-email-stats-table-cell wp-mail-smtp-dash-widget-email-stats-table-cell--all wp-mail-smtp-dash-widget-email-stats-table-cell--3">
                        <div class="wp-mail-smtp-dash-widget-email-stats-table-cell-container">
                            <img src="https://wordpress.dev/wp-content/plugins/wp-mail-smtp/assets/images/dash-widget/wp/total.svg" alt="Table cell icon">
                            <span>
								101 total							</span>
                        </div>
                    </td>
                    <td class="wp-mail-smtp-dash-widget-email-stats-table-cell wp-mail-smtp-dash-widget-email-stats-table-cell--delivered wp-mail-smtp-dash-widget-email-stats-table-cell--3">
                        <div class="wp-mail-smtp-dash-widget-email-stats-table-cell-container">
                            <img src="https://wordpress.dev/wp-content/plugins/wp-mail-smtp/assets/images/dash-widget/wp/delivered.svg" alt="Table cell icon">
                            <span>
								Sent N/A							</span>
                        </div>
                    </td>
                    <td class="wp-mail-smtp-dash-widget-email-stats-table-cell wp-mail-smtp-dash-widget-email-stats-table-cell--unsent wp-mail-smtp-dash-widget-email-stats-table-cell--3">
                        <div class="wp-mail-smtp-dash-widget-email-stats-table-cell-container">
                            <img src="https://wordpress.dev/wp-content/plugins/wp-mail-smtp/assets/images/dash-widget/wp/unsent.svg" alt="Table cell icon">
                            <span>
								Failed N/A							</span>
                        </div>
                    </td>
                </tr>
                </tbody>
            </table>

        </div>

        <canvas id="myChart" width="400" height="400"></canvas>


        <div id="wp-mail-smtp-dash-widget-upgrade-footer" class="wp-mail-smtp-dash-widget-block wp-mail-smtp-dash-widget-upgrade-footer wp-mail-smtp-dash-widget-upgrade-footer--show">
            <p>
                <a href="https://wpmailsmtp.com/lite-upgrade/?utm_source=WordPress&amp;utm_medium=dashboard-widget&amp;utm_campaign=liteplugin&amp;utm_content=upgrade-to-pro" target="_blank" rel="noopener noreferrer">Upgrade to Pro</a> for detailed stats, email logs, and more! </p>
        </div>
    </div>
</div>
