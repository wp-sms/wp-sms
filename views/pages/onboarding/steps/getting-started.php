<div class="c-section__title u-border-b">
    <span class="c-section__step">Step <?php echo $index ?> of 7</span>
    <h1 class=" u-m-0 u-text-orange">Welcome to WP SMS!</h1>
    <p class="u-m-0">
        Set up SMS functionality for your WordPress site by following a few simple steps. </p>
</div>
<div class="c-form c-form--medium u-flex u-content-center">
    <form method="post" action="<?php echo $ctas['next']['url'] ?>">
        <p class="c-form__title">Get Notifications Where You Need Them</p>
        <div class="c-form__fieldgroup u-mb-32">
            <label for="countries">
                Country Code
                <span data-tooltip="tooltip data" data-tooltip-font-size="12px">
                                  <i class="wps-tooltip-icon"></i>
                            </span>
                <span class="u-text-red">*</span>
            </label>
            <div class="wpsms-skeleton wpsms-skeleton__select wpsms-skeleton__select--step1"></div>
            <select id="countries" name="countries">
                <option value="Global">No country code (Global)</option>
                <option value="Albania">Albania</option>
                <option value="Algeria">Algeria</option>
                <option value="Andorra">Andorra</option>
                <option value="Angola">Angola</option>
            </select>
            <p class="c-form__description">Choose your country code from the list for accurate delivery. If your number is international and doesn't fit any specific country code, select 'Global'.</p>
        </div>
        <div class="c-form__fieldgroup u-mb-38">
            <label for="tel">Your Mobile Number <span class="u-text-red">*</span></label>
            <input name="tel" id="tel" placeholder="Enter your mobile number" type="tel"/>
            <p class="c-form__description">Enter the phone number where you'd like to receive management alerts.</p>
        </div>
        <div class="c-form__footer u-flex-end">
            <input class="c-btn c-btn--primary" type="submit" value="<?php echo $ctas['next']['text'] ?>"/>
        </div>
    </form>
</div>

