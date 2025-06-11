document.addEventListener('DOMContentLoaded', () => {
    const skipButtons = document.querySelectorAll('.js-wp-sms-aioModalClose');
    const modal = document.querySelector('.js-wp-sms-aioModal');
    const premiumStepsContent = document.querySelector('.js-wp-sms-aioModalSteps');
    const premiumSteps = document.querySelectorAll('.js-wp-sms-aioModalStep');
    const premiumWelcomeSteps = document.querySelectorAll('.js-wp-sms-aioModal-welcome .js-wp-sms-aioModalStep');
    const welcomeSection = document.querySelector('.js-wp-sms-aioModal-welcome');
    const premiumFeatures = document.querySelectorAll('.js-wp-sms-aioStepFeature');
    const upgradeButtonBox = document.querySelectorAll('.wp-sms-aio-step__action-container');
    const premiumBtn = document.querySelectorAll('.js-wp-sms-openAioModal')
    const premiumStepsTitle = document.querySelectorAll('.js-wp-sms-aio-steps__title');
    const firstStepHeader = document.querySelectorAll('.js-wp-sms-aio-first-step__head');
    const dynamicTitle = document.querySelector('.js-wp-sms-dynamic-title');

    let autoSlideInterval;
    let currentStepIndex = 1;

    if (premiumBtn.length > 0) {
         premiumBtn.forEach(button => {
            button.addEventListener('click', (event) => {
                event.preventDefault();
                event.stopPropagation();
                const href = button.getAttribute('href');
                const target = button.getAttribute('data-target');

                if (target === 'first-step' && !document.querySelector('.js-wp-sms-aioModal-welcome')) {
                    const welcomeDiv = document.createElement('div');
                    welcomeDiv.classList.add('js-wp-sms-aioModal-welcome');
                    welcomeDiv.style.display = 'block';

                    const modal = document.querySelector('.wp-sms-modal--aio');

                    if (modal) {
                         welcomeDiv.appendChild(modal);
                        document.body.appendChild(welcomeDiv);
                        showWelcomeModal()
                    }
                 } else {
                    openModal(target, href);
                }
            });
        });
    }

    if (skipButtons.length > 0) {
        skipButtons.forEach(button => {
            button.addEventListener('click', (event) => {
                event.preventDefault();
                event.stopPropagation();
                closeModal();
            });
        });
    }


    const closeModal = () => {
        if (modal) {
            modal.style.display = 'none';
            modal.classList.remove('wp-sms-modal--open');
            document.body.style.overflow = '';
        }
    }

    const setMaxHeightForAllSteps = () => {
        if (window.innerWidth <= 768 || premiumSteps.length === 0) return;
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
            loadModalImages();
            showStep(currentStepIndex + 1);
            premiumStepsContent.style.display = 'block';
            stopAutoSlide();
        }
    }


    // Hide the premium steps initially
    premiumSteps.forEach(step => {
        step.classList.remove('wp-sms-modal__aio-step--active');
    });


    const loadModalImages = () => {
        document.querySelectorAll('.wp-sms-aio-step__image').forEach((img) => {
            img.src = img.dataset.src;
        });
    }

    // Function to show a specific step and sync the sidebar
    const showStep = (index) => {
        const premiumSteps = document.querySelectorAll('.js-wp-sms-aioModalStep');

        if (!premiumSteps || index < 0 || index >= premiumSteps.length) {
            return;
        }

        setTimeout(() => {
            setMaxHeightForAllSteps();
        }, 100);


        const activeStep = premiumSteps[index];
         premiumSteps.forEach(step => step.classList.remove('wp-sms-modal__aio-step--active'));

        if(activeStep && activeStep !=='undefined'){
             const stepTitle = activeStep.querySelector('.js-wp-sms-aio-step__title');
             if (dynamicTitle && stepTitle) {
                 dynamicTitle.textContent = stepTitle.textContent.trim() || 'Default Title';
             }
            activeStep.classList.add('wp-sms-modal__aio-step--active');
         }




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
        if (premiumStepsTitle && premiumStepsTitle.length > 0) {
            premiumStepsTitle.forEach(p => {
                if (p) {
                    p.classList.remove('active')
                }
            });
            if (premiumStepsTitle[index - 1]) {
                premiumStepsTitle[index - 1].classList.add('active');
            }
        }

        premiumFeatures.forEach(feature => feature.classList.remove('active'));
        premiumSteps[index].classList.add('wp-sms-modal__aio-step--active');

        const toggleDisplay = (elements, displayStyle) => {
            elements.forEach(element => {
                element.style.display = displayStyle;
            });
        };


        if (index > 0) {
            toggleDisplay(firstStepHeader, 'none');
            premiumFeatures[index - 1].classList.add('active');
        } else {
            toggleDisplay(firstStepHeader, 'block');
        }

    }

    // Function to start the auto-slide process
    const startAutoSlide = () => {
        autoSlideInterval = setInterval(() => {
            const premiumWelcomeSteps = document.querySelectorAll('.js-wp-sms-aioModal-welcome .js-wp-sms-aioModalStep');
            if (premiumWelcomeSteps.length === 0) {
                 stopAutoSlide();
                return;
            }
            currentStepIndex = (currentStepIndex + 1) % premiumWelcomeSteps.length;
            showStep(currentStepIndex);
        }, 5000); // Adjust time interval to 5 seconds
    }

    const showWelcomeModal=()=>{
         const welcomeModal = document.querySelector('.js-wp-sms-aioModal-welcome');
        if (!welcomeModal) {
             return;
        }
        const premiumSteps = document.querySelectorAll('.js-wp-sms-aioModalStep');
        if (premiumSteps.length === 0) {
             return;
        }
        currentStepIndex = 0;
        loadModalImages();

        if (premiumStepsContent) {
            premiumStepsContent.style.display = 'block';
        }
        const modal = document.querySelector('.wp-sms-modal--aio');
        if (modal) {
            modal.style.display = 'block';
            modal.classList.add('wp-sms-modal--open');
            document.body.style.overflow = 'hidden';
        }
        showStep(currentStepIndex);
        startAutoSlide();
    }
    if (welcomeSection) {
        showWelcomeModal();
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


            const selectSender = jQuery('#select_sender');
            if (selectSender.length) {
                selectSender.select2({
                    minimumResultsForSearch: Infinity,
                    dropdownCssClass: 'wpsms-sendsms-select2-dropdown',
                    templateResult: formatOptions,
                    templateSelection: formatOptions
                });
                function formatOptions(data) {
                    if (!data.element) {
                        return data.text;
                    }
                    var formattedOption = data.text;
                    if (jQuery(data.element).data('target')) {
                        formattedOption = jQuery(`<span class='js-wp-sms-openAioModal' data-target='${jQuery(data.element).data('target')}'>${data.text}</span>`);
                    }
                    return formattedOption;
                }
                selectSender.on('select2:select', (event) => {
                    const selectedOption = event.target.selectedOptions[0];
                    if (selectedOption && selectedOption.classList.contains('js-wp-sms-openAioModal')) {
                        event.preventDefault();
                        const target = selectedOption.getAttribute('data-target');
                        const href = selectedOption.getAttribute('href') || '#';
                        openModal(target, href);
                    }
                });

                jQuery(document).on('click', '.wpsms-sendsms-select2-dropdown .js-wp-sms-openAioModal', function(event) {
                    event.stopPropagation();
                    const target = jQuery(this).attr('data-target');
                    const href = jQuery(this).attr('href') || '#';
                    openModal(target, href);
                });
            }
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
            if (modal && !modal.classList.contains('wp-sms-modal--open')) {
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
});