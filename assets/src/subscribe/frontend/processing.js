export function showProcessing() {
  jQuery(".wpsms-subscribe__overlay").css('display', 'flex');
}

export function hideProcessing() {
  jQuery(".wpsms-subscribe__overlay").css('display', 'none');
}

export function disableSubmitBtn() {
  jQuery("#wpsms-submit").attr('disabled', 'disabled');
}

export function enableSubmitBtn() {
  jQuery("#wpsms-submit").removeAttr('disabled');
}

export function disableActivationBtn() {
  jQuery("#activation").attr('disabled', 'disabled');
}

export function enableActivationBtn() {
  jQuery("#activation").removeAttr('disabled');
}

export function hideFirstStep() {
  jQuery("#wpsms-step-1").hide();
}

export function showSecondStep() {
  jQuery("#wpsms-step-2").show();
}

export function hideSecondStep() {
  jQuery("#wpsms-step-2").hide();
}
