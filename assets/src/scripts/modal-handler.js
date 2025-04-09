document.addEventListener('DOMContentLoaded', () => {
    const modal = document.querySelector('.js-wp-sms-premiumModal');
    const welcomeContent = document.querySelector('.js-wp-sms-premiumModalWelcomeContent');
    const premiumStepsContent = document.querySelector('.js-wp-sms-premiumModalSteps');
    const premiumSteps = document.querySelectorAll('.js-wp-sms-premiumModalStep');
    const premiumWelcomeSteps = document.querySelectorAll('.js-wp-sms-premiumModal-welcome .js-wp-sms-premiumModalStep');
    const exploreButton = document.querySelector('.js-wp-sms-premiumModalExploreBtn');
    const premiumFeatures = document.querySelectorAll('.js-wp-sms-premiumStepFeature');
    const upgradeButtonBox = document.querySelectorAll('.wp-sms-premium-step__action-container');
    const premiumBtn = document.querySelectorAll('.js-wp-sms-openPremiumModal');
    let autoSlideInterval;
    let currentStepIndex = 1;
    if (premiumBtn.length > 0) {
        premiumBtn.forEach(button => {
            button.addEventListener('click', (event) => {
                event.preventDefault();
                const href = button.getAttribute('href');
                const target = button.getAttribute('data-target');
                openModal(target, href);
            });
        });
    }

    const skipButtons = document.querySelectorAll('.js-wp-sms-premiumModalClose');
    if (skipButtons.length > 0) {
        skipButtons.forEach(button => {
            button.addEventListener('click', closeModal);
        });
    }


    const closeModal = () => {
        if (modal) {
            modal.style.display = 'none';
            document.body.style.overflow = '';
        }
    }

    const setMaxHeightForAllSteps = () => {
        if (premiumSteps.length === 0) {
            return;
        }
        let maxStepHeight = 0;
        premiumSteps.forEach(step => {
            const originalDisplay = step.style.display;
            step.style.display = 'block';
            step.style.minHeight = 'auto';
            let stepHeight = step.getBoundingClientRect().height;
            maxStepHeight = Math.max(maxStepHeight, stepHeight);
            step.style.display = originalDisplay;
        });
        premiumSteps.forEach(step => {
            step.style.minHeight = `${maxStepHeight}px`;
        });
    };


// Optionally, re-run the function when the window is resized
    window.addEventListener('resize', setMaxHeightForAllSteps);
    const openModal = (target, href) => {
        if (modal) {
            modal.style.display = 'block';
            document.body.style.overflow = 'hidden';
        }
        const targetIndex = Array.from(premiumFeatures).findIndex(step => step.getAttribute('data-modal') === target);
        if (targetIndex !== -1) {
            currentStepIndex = targetIndex;
            if (welcomeContent) {
                welcomeContent.style.display = 'none';
            }
            loadModalImages();
            showStep(currentStepIndex + 1);
            premiumStepsContent.style.display = 'block';
            stopAutoSlide();
        }
    }


// Hide the premium steps initially
    premiumSteps.forEach(step => {
        step.classList.remove('wp-sms-modal__premium-step--active');
    });

    if (exploreButton) {
        exploreButton.addEventListener('click', function () {
            currentStepIndex = 0;
            loadModalImages();
            welcomeContent.style.display = 'none';
            premiumStepsContent.style.display = 'block';
            showStep(currentStepIndex);
            startAutoSlide();
        });
    }

    const loadModalImages = () => {
        document.querySelectorAll('.wp-sms-premium-step__image').forEach((img) => {
            img.src = img.dataset.src;
        });
    }

// Function to show a specific step and sync the sidebar
    const showStep = (index) => {
        setTimeout(() => {
            setMaxHeightForAllSteps();
        }, 100);

        if (index < 0 || index >= premiumSteps.length) return;
        premiumSteps.forEach(step => step.classList.remove('wp-sms-modal__premium-step--active'));
        if (upgradeButtonBox && upgradeButtonBox.length > 0) {
            upgradeButtonBox.forEach(btn => {
                if (btn) {
                    btn.classList.remove('active');
                }
            });
            if (upgradeButtonBox[index - 1]) {
                upgradeButtonBox[index - 1].classList.add('active');
            }
        }
        premiumFeatures.forEach(feature => feature.classList.remove('active'));
        premiumSteps[index].classList.add('wp-sms-modal__premium-step--active');

        if (index > 0) {
            premiumFeatures[index - 1].classList.add('active');
        }

    }

// Function to start the auto-slide process
    const startAutoSlide = () => {
        autoSlideInterval = setInterval(() => {
            currentStepIndex = (currentStepIndex + 1) % premiumWelcomeSteps.length; // Loop through steps
            showStep(currentStepIndex); // Show the current step and sync sidebar
        }, 5000); // Adjust time interval to 5 seconds
    }

    const stopAutoSlide = () => {
        clearInterval(autoSlideInterval);
    };

// Event listeners for each premium step feature
    if (premiumFeatures.length > 0) {
        premiumFeatures.forEach((feature, index) => {
            feature.addEventListener('click', function () {
                stopAutoSlide(); // Stop auto-slide when user interacts
                currentStepIndex = index + 1
                showStep(currentStepIndex);
            });
        });
    }


    class ModalHandler {
        constructor() {
            this.init();
        }

        init() {
            document.addEventListener('click', (event) => {
                const button = event.target.closest('[class*="js-openModal-"]');
                if (button) {
                    const modalId = this.extractModalIdFromClass(button.classList);
                    if (modalId) {
                        this.openModal(modalId);
                    }
                }
                const actionButton = event.target.closest('button[data-action]');
                if (actionButton) {
                    const action = actionButton.getAttribute('data-action');
                    if (action) {
                        const modal = actionButton.closest('.wp-sms-modal');
                        this.handleModalAction(modal, action);
                    }
                }
            });
            this.attachOpenEvent();
            this.attachCloseEvent();
        }

        // Event delegation for opening modals
        attachOpenEvent() {
            document.addEventListener('click', (event) => {
                // Check if the clicked element or its parent matches the selector
                const button = event.target.closest('[class*="js-openModal-"]');
                if (button) {
                    const modalId = this.extractModalIdFromClass(button.classList);
                    if (modalId) {
                        this.openModal(modalId);
                    }
                }
            });
        }

        extractModalIdFromClass(classList) {
            for (let className of classList) {
                if (className.startsWith('js-openModal-')) {
                    return className.replace('js-openModal-', '').toLowerCase();
                }
            }
            return null;
        }

        openModal(modalId) {
            const modal = document.getElementById(modalId);
            if (modal) {
                modal.classList.add('wp-sms-modal--open');
            } else {
                console.error(`Modal with ID "${modalId}" not found.`);
            }
        }

        // Event delegation for closing modals
        attachCloseEvent() {
            document.addEventListener('click', (event) => {
                const button = event.target.closest('.wp-sms-modal__close');
                if (button) {
                    const modal = button.closest('.wp-sms-modal');
                    if (modal) {
                        modal.classList.remove('wp-sms-modal--open');
                    }
                }
            });
        }

        handleModalAction(modal, action) {
            switch (action) {
                case 'resolve':
                    break;
                case 'closeModal':
                    this.closeModal(modal);
                    break;
                default:
                    console.warn('Unknown action:', action);
            }
        }

        closeModal(modal) {
            modal.classList.remove('wp-sms-modal--open');
        }
    }

    new ModalHandler();
})