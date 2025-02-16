<div class="c-section__title">
    <span class="c-section__step">Step <?php echo $index ?> of 7</span>
    <h1 class=" u-m-0">Set Up Your SMS Gateway</h1>
    <p class="u-m-0">
        To get started with sending messages, enter your SMS gateway credentials. This connects your WordPress site
        directly to the SMS service. </p>
</div>
<div class="c-form c-form--medium u-flex u-content-center u-align-center u-flex--column">
    <form method="post" action="<?php echo $ctas['next']['url'] ?>">
        <div class="c-table__wrapper">
            <div class="wpsms-skeleton wpsms-skeleton__table"></div>
            <table class="c-table c-table-gateway js-table-gateway js-table">
                <thead>
                <tr>
                    <th>Gateway
                        <span data-tooltip="Gateway tooltip" data-tooltip-font-size="12px">
                                          <i class="wps-tooltip-icon"></i>
                                        </span>
                        <i class="c-table__sort-arrow"></i>
                    </th>
                    <th class="u-text-center">Bulk SMS <span data-tooltip="Bulk SMS tooltip" data-tooltip-font-size="12px"><i class="wps-tooltip-icon"></i></span></th>
                    <th class="u-text-center">MMS <span data-tooltip="MMS tooltip" data-tooltip-font-size="12px"><i class="wps-tooltip-icon"></i></span></th>
                    <th>Gateway Access <span data-tooltip="Gateway Access tooltip" data-tooltip-font-size="12px"><i class="wps-tooltip-icon"></i></span> <i class="c-table__sort-arrow"></i></th>
                </tr>
                </thead>
                <tbody>
                <tr>
                    <td>
                        <input id="gateway-name-1" name="name" type="radio">
                        <label for="gateway-name-1">Gateway name 1</label>
                    </td>
                    <td class="u-text-center"><span class="checked"></span></td>
                    <td class="u-text-center"><span class="unchecked"></span></td>
                    <td class="u-flex u-align-center u-content-sp">
                        <span class="c-table__availability c-table__availability--success">Available</span>
                    </td>
                </tr>
                <tr class="disabled">
                    <td>
                        <span data-tooltip="Pro Version Required" data-tooltip-font-size="12px"><span class="icon-lock"></span></span>
                        <span>Gateway name 2</span>
                    </td>
                    <td class="u-text-center"><span class="checked"></span></td>
                    <td class="u-text-center"><span class="unchecked"></span></td>
                    <td class="u-flex u-align-center u-content-sp">
                        <a title="Pro Version Required" target="_blank" href="https://wp-sms-pro.com/buy/?utm_source=wp-sms&utm_medium=link&utm_campaign=onboarding" class="c-table__availability c-table__availability--pro">Pro Version Required</a>
                    </td>
                </tr>

                <tr class="disabled">
                    <td>
                        <span data-tooltip="Pro Version Required" data-tooltip-font-size="12px"><span class="icon-lock"></span></span>
                        <span>Gateway name</span>
                    </td>
                    <td class="u-text-center"><span class="checked"></span></td>
                    <td class="u-text-center"><span class="unchecked"></span></td>
                    <td class="u-flex u-align-center u-content-sp">
                        <a title="Pro Version Required" target="_blank" href="https://wp-sms-pro.com/buy/?utm_source=wp-sms&utm_medium=link&utm_campaign=onboarding" class="c-table__availability c-table__availability--pro">Pro Version Required</a>
                    </td>
                </tr>
                <tr>
                    <td>
                        <input id="gateway-name-4" name="name" type="radio">
                        <label for="gateway-name-4">Gateway name</label>
                    </td>
                    <td class="u-text-center"><span class="checked"></span></td>
                    <td class="u-text-center"><span class="unchecked"></span></td>
                    <td class="u-flex u-align-center u-content-sp">
                        <span class="c-table__availability c-table__availability--success">Available</span>
                    </td>
                </tr>
                <tr>
                    <td>
                        <input id="gateway-name-5" name="name" type="radio">
                        <label for="gateway-name-5">Gateway name 5</label>
                    </td>
                    <td class="u-text-center"><span class="checked"></span></td>
                    <td class="u-text-center"><span class="checked"></span></td>
                    <td class="u-flex u-align-center u-content-sp">
                        <span class="c-table__availability c-table__availability--success">Available</span>
                    </td>
                </tr>
                <tr class="disabled">
                    <td>
                        <span data-tooltip="Pro Version Required" data-tooltip-font-size="12px"><span class="icon-lock"></span></span>
                        <span>Gateway name 6</span>
                    </td>
                    <td class="u-text-center"><span class="checked"></span></td>
                    <td class="u-text-center"><span class="unchecked"></span></td>
                    <td class="u-flex u-align-center u-content-sp">
                        <a title="Pro Version Required" target="_blank" href="https://wp-sms-pro.com/buy/?utm_source=wp-sms&utm_medium=link&utm_campaign=onboarding" class="c-table__availability c-table__availability--pro">Pro Version Required</a>
                    </td>
                </tr>
                </tbody>
            </table>
        </div>
        <div class="c-getway__offer u-mb-38">
            <span>Don’t have SMS gateway?</span>
            <a class="c-link" href="https://wp-sms-pro.com/gateways/?utm_source=wp-sms&utm_medium=link&utm_campaign=onboarding" target="_blank" title="Check out our recommended SMS gateways for optimized service.">
                Check out our recommended SMS gateways for optimized service.
            </a>
        </div>
        <div class="c-form__footer u-content-sp u-align-center">
            <a class="c-form__footer--last-step" href="<?php echo $ctas['back']['url'] ?>"><?php echo $ctas['back']['text'] ?></a>
            <!--            <input class="c-btn c-btn--primary" disabled type="submit" value="No gateway selected"/>-->
            <input class="c-btn c-btn--primary" type="submit" value="<?php echo $ctas['next']['text'] ?>"/>
        </div>
    </form>
</div>