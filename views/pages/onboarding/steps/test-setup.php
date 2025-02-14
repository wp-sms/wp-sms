<div class="c-section__title">
    <span class="c-section__step">Step 4 of 7</span>
    <h1 class=" u-m-0">Test Your Setup</h1>
    <p class="u-m-0">
        Send a test SMS to the administrator's phone number to confirm everything is working as it should. </p>
</div>
<form method="post" action="https://vl.test/wp-admin/admin.php?page=wp-sms-onboarding&step=test-setup&action=next">
    <div class="c-section__test">
        <p class="c-section__test--title">A test message will be sent to the phone number you provided earlier. Please
            check your device for the message.</p>
        <p class="c-section__test--title">Did you receive the test SMS?</p>
        <p class="c-section__test--description">If you've received the test SMS, clicking 'Yes, I received it!' will
            confirm your setup is correct and take you to the next step. If not, select 'No, I didn't receive it.' for
            troubleshooting options.</p>
    </div>
    <div class="c-form__footer u-content-sp u-align-center">
        <a class="c-form__footer--last-step" href="<?= $ctas['back']['url'] ?>"><?= $ctas['back']['text'] ?></a>
        <!--            <input class="c-btn c-btn--primary" disabled type="submit" value="No gateway selected"/>-->
        <input class="c-btn c-btn--primary" type="submit" value="<?= $ctas['next']['text'] ?>"/>
    </div>
</form>