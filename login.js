const loginForm = document.getElementById('loginForm');
const togglePassword = document.getElementById('togglePassword');
const eyeIcon = document.getElementById('eyeIcon');
const passwordField = document.getElementById('password');

// Toggle password visibility
togglePassword.addEventListener('click', () => {
    if (passwordField.type === 'password') {
        passwordField.type = 'text';
        eyeIcon.classList.remove('fa-eye');
        eyeIcon.classList.add('fa-eye-slash');
    } else {
        passwordField.type = 'password';
        eyeIcon.classList.remove('fa-eye-slash');
        eyeIcon.classList.add('fa-eye');
    }
});

// Contact Admin
document.getElementById('contactAdmin').addEventListener('click', (e) => {
    e.preventDefault();
    alert("📧 Contact Administrator:\n\nEmail: admin@ramyumstore.com\nPhone: (555) 123-4567");
});

// Backend endpoint (same-origin PHP server, e.g. via XAMPP/php -S)
const LOGIN_API_URL = 'login.php';

// Login Handler
loginForm.addEventListener('submit', async function(e) {
    e.preventDefault();
    
    const email = document.getElementById('email').value.trim();
    const password = document.getElementById('password').value.trim();
    const loginBtn = document.getElementById('loginBtn');
    const originalBtn = loginBtn.innerHTML;

    // Simple client-side validation (server re-checks and audits regardless)
    if (!email.includes('@')) {
        alert("Please enter a valid email address.");
        return;
    }
    if (password.length < 3) {
        alert("Password must be at least 3 characters.");
        return;
    }

    loginBtn.innerHTML = `<span class="spinner-border spinner-border-sm me-2"></span> LOGGING IN...`;
    loginBtn.disabled = true;

    try {
        const response = await fetch(LOGIN_API_URL, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ email, password }),
        });
        const data = await response.json();

        loginBtn.innerHTML = originalBtn;
        loginBtn.disabled = false;

        if (!response.ok || !data.ok) {
            alert(`⚠️ ${data.message || 'Login failed. Please try again.'}`);
            return;
        }

        createConfetti();
        setTimeout(() => {
            window.location.href = data.redirect || 'dashboard.php';
        }, 900);
    } catch (err) {
        loginBtn.innerHTML = originalBtn;
        loginBtn.disabled = false;
        alert("⚠️ Couldn't reach the login server. Please try again.");
        console.error('Login request failed:', err);
    }
});

function createConfetti() {
    for (let i = 0; i < 80; i++) {
        setTimeout(() => {
            const confetti = document.createElement('div');
            confetti.textContent = ['🍜', '🥢', '🍣', '🥟'][Math.floor(Math.random() * 4)];
            confetti.style.position = 'fixed';
            confetti.style.left = Math.random() * 100 + 'vw';
            confetti.style.top = '-50px';
            confetti.style.fontSize = '2rem';
            confetti.style.zIndex = '9999';
            confetti.style.transition = 'all 3s';
            document.body.appendChild(confetti);
            
            setTimeout(() => {
                confetti.style.transform = `translateY(${window.innerHeight + 100}px) rotate(${Math.random() * 800}deg)`;
                confetti.style.opacity = '0';
            }, 50);
            
            setTimeout(() => confetti.remove(), 3500);
        }, i * 30);
    }
}