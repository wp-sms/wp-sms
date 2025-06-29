import {getElements} from "../utils/utilities";

const initAddonActions = () => {
    const actionButtons = getElements('.js-addon-show-more');

    if (actionButtons.length === 0) {
        return;
    }

    actionButtons.map( (button) => {
        button.addEventListener('click', function (event) {
            event.stopPropagation();

            const isActive = this.parentElement.classList.contains('active');

            document.querySelectorAll('.js-addon-show-more').forEach(function (otherButton) {
                otherButton.parentElement.classList.remove('active');
            });

            if (!isActive) {
                this.parentElement.classList.add('active');
            }
        });

        document.addEventListener('click', function (e) {
            if (!e.target.closest('.wpsms-addon--submenus')) {
                button.parentElement.classList.remove('active');
            }
        })
    });

    document.body.addEventListener('click', function () {
        document.querySelectorAll('.js-addon-show-more').forEach(function (button) {
            button.parentElement.classList.remove('active');
        });
    });
}

export {initAddonActions}