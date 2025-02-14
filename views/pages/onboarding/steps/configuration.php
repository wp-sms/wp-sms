<div class="c-section__title">
    <span class="c-section__step">Step 3 of 7</span>
    <h1 class=" u-m-0">Set Up Your SMS Gateway</h1>
    <p class="u-m-0">
        To get started with sending messages, enter your SMS gateway credentials. This connects your WordPress site
        directly to the SMS service. </p>
</div>

<div class="c-form c-form--medium u-flex u-content-center u-align-center u-flex--column">
    <form method="post" action="https://vl.test/wp-admin/admin.php?page=wp-sms-onboarding&step=configuration&action=next">
        <div class="c-form__fieldgroup u-mb-24">
            <label for="username">API username <span class="u-text-red">*</span></label>
            <input id="username" name="username" placeholder="" type="text"/>
            <p class="c-form__description">Enter the username provided by your SMS gateway.</p>
        </div>
        <div class="c-form__fieldgroup u-mb-24">
            <label for="password">API password <span class="u-text-red">*</span></label>
            <input id="password" name="password" placeholder="" type="password"/>
            <p class="c-form__description">Enter the password associated with your SMS gateway account.</p>
        </div>
        <div class="c-form__fieldgroup u-mb-38">
            <label for="tel">Sender number <span class="u-text-red">*</span></label>
            <input id="tel" name="tel" placeholder="" type="tel"/>
            <p class="c-form__description">This is the number that will appear on recipients’ devices when they receive
                your messages.</p>
        </div>
        <div class="c-form__footer u-content-sp u-align-center">
            <a class="c-form__footer--last-step" href="<?= $ctas['back']['url'] ?>"><?= $ctas['back']['text'] ?></a>
            <!--            <input class="c-btn c-btn--primary" disabled type="submit" value="No gateway selected"/>-->
            <input class="c-btn c-btn--primary" type="submit" value="<?= $ctas['next']['text'] ?>"/>
        </div>
    </form>
</div>
