const introScreen = document.getElementById('introScreen');
const mainContainer = document.getElementById('mainContainer');

let entered = false;

function enterStore() {
    if (entered) return;
    entered = true;

    introScreen.classList.add('hide');
    mainContainer.classList.add('reveal');

    // Focus email field once the login screen is visible
    setTimeout(() => {
        introScreen.remove();
        const emailField = document.getElementById('email');
        if (emailField) emailField.focus();
    }, 750);
}

// Auto-enter once the intro animation has played through
const AUTO_ENTER_DELAY = 6000; // ms
const autoEnterTimer = setTimeout(enterStore, AUTO_ENTER_DELAY);

// Allow the user to skip the animation by tapping/clicking anywhere
introScreen.addEventListener('click', () => {
    clearTimeout(autoEnterTimer);
    enterStore();
});

// Also allow skipping with any key press
document.addEventListener('keydown', () => {
    if (!entered) {
        clearTimeout(autoEnterTimer);
        enterStore();
    }
});
