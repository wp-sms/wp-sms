<div class="c-section__title">
    <span class="c-section__step">Step 3 of 7</span>
    <h1 class=" u-m-0">Set Up Your SMS Gateway</h1>
    <p class="u-m-0">
        To get started with sending messages, enter your SMS gateway credentials. This connects your WordPress site
        directly to the SMS service. </p>
</div>
<div class="c-form c-form--medium u-flex u-content-center u-align-center u-flex--column">
    <form action="/step4-industry.html">
        <div class="c-form__fieldgroup u-mb-24">
            <label for="username">API username <span class="u-text-red">*</span></label>
            <input id="username" placeholder="" type="text"/>
            <p class="c-form__description">Enter the username provided by your SMS gateway.</p>
        </div>
        <div class="c-form__fieldgroup u-mb-24">
            <label for="password">API password <span class="u-text-red">*</span></label>
            <input id="password" placeholder="" type="password"/>
            <p class="c-form__description">Enter the password associated with your SMS gateway account.</p>
        </div>
        <div class="c-form__fieldgroup u-mb-24">
            <label for="tel">Sender number <span class="u-text-red">*</span></label>
            <input id="tel" placeholder="" type="tel"/>
            <p class="c-form__description">This is the number that will appear on recipients’ devices when they receive
                your messages.</p>
        </div>
        <ul class="c-form__result c-form__result--success">
            Connection Status
            <li class="c-form__result-item u-flex u-content-sp u-align-center u-mb-16">
                <span class="c-form__result-title">Status</span>
                <span class="c-form__result-status">
                    <span class="c-form__result-status--sucsess">Active</span>
                    <span class="c-form__result-description">Your SMS gateway is successfully connected and ready to use.</span>
                  </span>
            </li>
            <li class="c-form__result-item u-flex u-content-sp u-align-center u-mb-16">
                <span class="c-form__result-title">Balance</span>
                <span class="c-form__result-status">
                    <span class="c-form__result-status--primary">$23</span>
                    <span class="c-form__result-description">This is the current credit in your SMS account.</span>
                  </span>
            </li>
            <li class="c-form__result-item u-flex u-content-sp u-align-center u-mb-16">
                <span class="c-form__result-title">Incoming message</span>
                <span class="c-form__result-status">
                    <span class="c-form__result-status--sucsess">Supported</span>
                    <span class="c-form__result-description">You can receive SMS messages on your configured number.</span>
                  </span>
            </li>
            <li class="c-form__result-item u-flex u-content-sp u-align-center u-mb-16">
                <span class="c-form__result-title">Bulk SMS</span>
                <span class="c-form__result-status">
                    <span class="c-form__result-status--sucsess">Supported</span>
                    <span class="c-form__result-description">You can send bulk SMS messages.</span>
                  </span>
            </li>
            <li class="c-form__result-item u-flex u-content-sp u-align-center">
                <span class="c-form__result-title">Send MMS</span>
                <span class="c-form__result-status">
                    <span class="c-form__result-status--sucsess">Supported</span>
                    <span class="c-form__result-description">Multimedia Messaging Service (MMS) is enabled.</span>
                  </span>
            </li>
        </ul>
        <ul class="c-form__result c-form__result--danger">
            Connection Status
            <li class="c-form__result-item u-flex u-content-sp u-align-center u-mb-16">
                <span class="c-form__result-title">Status</span>
                <span class="c-form__result-status">
                    <span class="c-form__result-status--danger">Deactivated!</span>
                    <span class="c-form__result-description">There is an issue with the SMS gateway connection. Please check your settings.</span>
                  </span>
            </li>
            <li class="c-form__result-item u-flex u-content-sp u-align-center u-mb-16">
                <span class="c-form__result-title">Balance</span>
                <span class="c-form__result-status">
                    <span class="c-form__result-status--primary">-</span>
                    <span class="c-form__result-description">This is the current credit in your SMS account.</span>
                  </span>
            </li>
            <li class="c-form__result-item u-flex u-content-sp u-align-center u-mb-16">
                <span class="c-form__result-title">Incoming message</span>
                <span class="c-form__result-status">
                    <span class="c-form__result-status--danger">Does not support!</span>
                    <span class="c-form__result-description">Receiving SMS messages is not supported with the current gateway. Choose another gateway for this feature.</span>
                  </span>
            </li>
            <li class="c-form__result-item u-flex u-content-sp u-align-center u-mb-16">
                <span class="c-form__result-title">Bulk SMS</span>
                <span class="c-form__result-status">
                    <span class="c-form__result-status--danger">Does not support!</span>
                    <span class="c-form__result-description">You cannot send bulk SMS messages with the current gateway setup. To enable this feature, please select a gateway that offers bulk messaging.</span>
                  </span>
            </li>
            <li class="c-form__result-item u-flex u-content-sp u-align-center">
                <span class="c-form__result-title">Send MMS</span>
                <span class="c-form__result-status">
                    <span class="c-form__result-status--danger">Does not support!</span>
                    <span class="c-form__result-description">Your gateway does not support sending MMS. For this service, please select a gateway that offers MMS capabilities.</span>
                  </span>
            </li>
        </ul>
    </form>
</div>