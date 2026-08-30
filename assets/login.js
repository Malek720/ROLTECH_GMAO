const navItems = document.querySelectorAll('.rtn-nav-item');
const mainArea = document.getElementById('rtn-main-slot');
const card = document.getElementById('rtn-card');

const FORM_TEMPLATE = (roleLabel, roleKey) => `
    <div class="rtn-form-wrap">
        <div class="rtn-eyebrow">Connexion</div>
        <h1 class="rtn-heading">Espace ${roleLabel}</h1>
        <form method="post" action="/login_check" class="rtn-login-form" data-turbo="false">
            <label class="rtn-field">
                <span class="rtn-field-label">Email</span>
                <input type="email" name="_username" value="${window.rtnLastUsername || ''}" placeholder="vous@roltechsystems.com" required autofocus>
            </label>
            <label class="rtn-field">
                <span class="rtn-field-label">Mot de passe</span>
                <input type="password" name="_password" required>
            </label>
            <input type="hidden" name="_csrf_token" value="${window.rtnCsrfToken}">
            <input type="hidden" name="_role_hint" value="${roleKey}">
            <button type="submit" class="rtn-submit-btn">Se connecter</button>
        </form>
    </div>
`;

navItems.forEach(item => {
    item.addEventListener('click', () => {
        navItems.forEach(i => i.classList.remove('rtn-active'));
        item.classList.add('rtn-active');

        const accent = item.dataset.accent;
        const label = item.dataset.label;
        const role = item.dataset.role;

        card.style.setProperty('--rtn-accent', accent);
        mainArea.innerHTML = FORM_TEMPLATE(label, role);
    });
});

// Select the first role by default on load
if (navItems.length) {
    navItems[0].click();
}
