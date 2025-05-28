document.addEventListener('DOMContentLoaded', function() {
    function initializeMobileMenu() {
        const mobileMenuToggle = document.getElementById('mobile-menu');
        const navMenu = document.querySelector('.nav-menu');
        if (mobileMenuToggle && navMenu) {
            mobileMenuToggle.addEventListener('click', () => {
                navMenu.classList.toggle('active');
                mobileMenuToggle.classList.toggle('active');
            });
        }
    }

    function initializeRegistrationFormValidation() {
        const registrationForm = document.getElementById('registration-form');
        if (registrationForm) {
            registrationForm.addEventListener('submit', function(event) {
                let isValid = true;
                const password = document.getElementById('password');
                const confirmPassword = document.getElementById('confirm_password');
                const passwordError = document.getElementById('password-error');
                const email = document.getElementById('email');
                const emailError = document.getElementById('email-error');
                if(passwordError) passwordError.textContent = '';
                if(emailError) emailError.textContent = '';
                if(password) password.classList.remove('is-invalid');
                if(confirmPassword) confirmPassword.classList.remove('is-invalid');
                if(email) email.classList.remove('is-invalid');
                if (password && confirmPassword && password.value !== confirmPassword.value) {
                    if (passwordError) passwordError.textContent = 'Passwords do not match.';
                    if(password) password.classList.add('is-invalid');
                    if(confirmPassword) confirmPassword.classList.add('is-invalid');
                    isValid = false;
                }
                if (email && email.value.trim() !== '' && !validateEmail(email.value)) {
                    if(emailError) emailError.textContent = 'Please enter a valid email address.';
                    if(email) email.classList.add('is-invalid');
                    isValid = false;
                } else if (email && email.value.trim() === '') {
                    if(emailError) emailError.textContent = 'Email is required.';
                    if(email) email.classList.add('is-invalid');
                    isValid = false;
                }
                if (password && password.value === '') {
                    if (passwordError && !passwordError.textContent) passwordError.textContent = 'Password is required.';
                    if(password) password.classList.add('is-invalid');
                    isValid = false;
                } else if (password && password.value.length > 0 && password.value.length < 8) {
                    if (passwordError && !passwordError.textContent) passwordError.textContent = 'Password must be at least 8 characters long.';
                    if(password) password.classList.add('is-invalid');
                    isValid = false;
                }
                if (!isValid) event.preventDefault();
            });
        }
    }

    function addDataLabelsToCartTable() {
        const cartTables = document.querySelectorAll('.cart-table');
        cartTables.forEach(cartTable => {
            if (cartTable) {
                const headers = Array.from(cartTable.querySelectorAll('thead th')).map(th => th.textContent.trim());
                const rows = cartTable.querySelectorAll('tbody tr');
                rows.forEach(row => {
                    const cells = row.querySelectorAll('td');
                    cells.forEach((cell, index) => {
                        if (headers[index]) cell.setAttribute('data-label', headers[index]);
                    });
                });
            }
        });
    }

    function initializeConfirmActionLinks() {
        document.body.addEventListener('click', function(event) {
            let targetElement = event.target;
            while (targetElement && targetElement !== document.body) {
                if (targetElement.matches('.confirm-action-link')) {
                    const message = targetElement.getAttribute('data-confirm-message') || 'Are you sure you want to perform this action?';
                    if (!confirm(message)) event.preventDefault();
                    return;
                }
                targetElement = targetElement.parentNode;
            }
        });
    }

    function initializeProductTabs() {
        const tabButtons = document.querySelectorAll('#productTab .nav-link');
        const tabPanes = document.querySelectorAll('#productTabContent .tab-pane');
        if (tabButtons.length > 0 && tabPanes.length > 0) {
            tabButtons.forEach(button => {
                button.addEventListener('click', function(event) {
                    event.preventDefault();
                    tabButtons.forEach(btn => {
                        btn.classList.remove('active');
                        btn.setAttribute('aria-selected', 'false');
                        btn.style.borderColor = 'transparent';
                        btn.style.color = 'var(--text-color, #333)';
                        const paneId = btn.getAttribute('data-bs-target') || btn.getAttribute('href');
                        if(paneId){
                            const pane = document.querySelector(paneId);
                            if(pane) pane.classList.remove('show', 'active');
                        }
                    });
                    tabPanes.forEach(pane => {
                        pane.classList.remove('show', 'active');
                    });
                    this.classList.add('active');
                    this.setAttribute('aria-selected', 'true');
                    this.style.borderColor = 'var(--primary-color, #FF9494)';
                    this.style.color = 'var(--primary-color, #FF9494)';
                    const targetPaneId = this.getAttribute('data-bs-target') || this.getAttribute('href');
                    if(targetPaneId){
                        const targetPane = document.querySelector(targetPaneId);
                        if (targetPane) targetPane.classList.add('show', 'active');
                    }
                });
            });
            const initialActiveButton = document.querySelector('#productTab .nav-link.active');
            if(initialActiveButton){
                initialActiveButton.style.borderColor = 'var(--primary-color, #FF9494)';
                initialActiveButton.style.color = 'var(--primary-color, #FF9494)';
                const initialTargetPaneId = initialActiveButton.getAttribute('data-bs-target') || initialActiveButton.getAttribute('href');
                if (initialTargetPaneId) {
                    const initialPane = document.querySelector(initialTargetPaneId);
                    if (initialPane && !initialPane.classList.contains('active')) initialPane.classList.add('show', 'active');
                }
            }
        }
    }

    function initializeFlashSaleCountdowns() {
        const countdownElements = document.querySelectorAll('.flash-sale-countdown');
        countdownElements.forEach(element => {
            const endTimeString = element.getAttribute('data-end-time');
            if (!endTimeString) {
                const noTimeSpan = element.querySelector('.time-left');
                if(noTimeSpan) noTimeSpan.textContent = 'Offer available!';
                return;
            }
            const endTime = new Date(endTimeString.replace(" ", "T")).getTime();
            if (isNaN(endTime)) {
                const errorSpan = element.querySelector('.time-left');
                if(errorSpan) errorSpan.textContent = 'Sale end time unclear.';
                return;
            }
            const timeLeftSpan = element.querySelector('.time-left');
            if (!timeLeftSpan) return;
            function updateCountdown() {
                const now = new Date().getTime();
                const distance = endTime - now;
                if (distance < 0) {
                    timeLeftSpan.textContent = "EXPIRED!";
                    element.closest('.product-card.flash-sale-item')?.classList.add('expired-sale');
                    clearInterval(interval);
                    return;
                }
                const days = Math.floor(distance / (1000 * 60 * 60 * 24));
                const hours = Math.floor((distance % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60));
                const minutes = Math.floor((distance % (1000 * 60 * 60)) / (1000 * 60));
                const seconds = Math.floor((distance % (1000 * 60)) / 1000);
                let countdownText = "";
                if (days > 0) countdownText += days + "d ";
                if (days > 0 || hours > 0) countdownText += hours + "h ";
                if (days > 0 || hours > 0 || minutes > 0) countdownText += minutes + "m ";
                countdownText += seconds + "s";
                timeLeftSpan.textContent = countdownText;
            }
            const interval = setInterval(updateCountdown, 1000);
            updateCountdown();
        });
    }

    initializeMobileMenu();
    initializeRegistrationFormValidation();
    addDataLabelsToCartTable();
    initializeConfirmActionLinks();
    initializeProductTabs();
    initializeFlashSaleCountdowns();
});

function validateEmail(email) {
    const re = /^(?:[a-z0-9!#$%&'*+/=?^_`{|}~-]+(?:\.[a-z0-9!#$%&'*+/=?^_`{|}~-]+)*|"(?:[\x01-\x08\x0b\x0c\x0e-\x1f\x21\x23-\x5b\x5d-\x7f]|\\[\x01-\x09\x0b\x0c\x0e-\x7f])*")@(?:(?:[a-z0-9](?:[a-z0-9-]*[a-z0-9])?\.)+[a-z0-9](?:[a-z0-9-]*[a-z0-9])?|\[(?:(?:(2(5[0-5]|[0-4][0-9])|1[0-9][0-9]|[1-9]?[0-9]))\.){3}(?:(2(5[0-5]|[0-4][0-9])|1[0-9][0-9]|[1-9]?[0-9])|[a-z0-9-]*[a-z0-9]:(?:[\x01-\x08\x0b\x0c\x0e-\x1f\x21-\x5a\x53-\x7f]|\\[\x01-\x09\x0b\x0c\x0e-\x7f])+)\])$/;
    return re.test(String(email).toLowerCase());
}

function confirmAction(message) {
    return confirm(message || 'Are you sure you want to perform this action?');
}
