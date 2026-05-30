/**
 * =====================================================================
 *  Point d'entrée du frontend SmartCampus.
 * =====================================================================
 *  - vérifie la session (auth/me) ;
 *  - affiche l'écran de connexion OU l'application ;
 *  - construit le menu latéral selon le rôle ;
 *  - configure le routeur et les notifications.
 */
import { store } from './store.js';
import { api, ApiError } from './api.js';
import { fetchMe, login, register, logout } from './auth.js';
import { configure, startRouter, navigate } from './router.js';
import { esc, toast } from './ui.js';

// ---------------------------------------------------------------------
//  TABLE DES ROUTES (chargement dynamique des pages)
// ---------------------------------------------------------------------
const routes = [
    { path: 'dashboard', roles: ['admin','teacher','student'], load: () => import('./pages/dashboard.js') },
    { path: 'profile',   roles: ['admin','teacher','student'], load: () => import('./pages/profile.js') },
    { path: 'students',      roles: ['admin','teacher'], load: () => import('./pages/students.js') },
    { path: 'students/:id',  roles: ['admin','teacher','student'], load: () => import('./pages/studentDetail.js') },
    { path: 'teachers',      roles: ['admin','teacher'], load: () => import('./pages/teachers.js') },
    { path: 'courses',       roles: ['admin','teacher','student'], load: () => import('./pages/courses.js') },
    { path: 'courses/:id',   roles: ['admin','teacher','student'], load: () => import('./pages/courseDetail.js') },
    { path: 'my-courses',    roles: ['student'], load: () => import('./pages/myCourses.js') },
    { path: 'grades',        roles: ['student'], load: () => import('./pages/grades.js') },
    { path: 'schedule',      roles: ['admin','teacher','student'], load: () => import('./pages/schedule.js') },
    { path: 'messages',      roles: ['admin','teacher','student'], load: () => import('./pages/messages.js') },
    { path: 'users',         roles: ['admin'], load: () => import('./pages/users.js') },
    { path: 'stats',         roles: ['admin'], load: () => import('./pages/stats.js') },
];

// ---------------------------------------------------------------------
//  MENU LATÉRAL par rôle
// ---------------------------------------------------------------------
const MENUS = {
    admin: [
        { section: 'Pilotage' },
        { href: '#/dashboard', icon: 'speedometer2', label: 'Tableau de bord' },
        { href: '#/stats', icon: 'bar-chart-line', label: 'Statistiques' },
        { section: 'Gestion académique' },
        { href: '#/students', icon: 'mortarboard', label: 'Étudiants' },
        { href: '#/teachers', icon: 'person-video3', label: 'Enseignants' },
        { href: '#/courses', icon: 'journal-bookmark', label: 'Cours' },
        { href: '#/schedule', icon: 'calendar3', label: 'Emploi du temps' },
        { section: 'Administration' },
        { href: '#/users', icon: 'people', label: 'Utilisateurs' },
        { href: '#/messages', icon: 'envelope', label: 'Messagerie' },
    ],
    teacher: [
        { section: 'Enseignement' },
        { href: '#/dashboard', icon: 'speedometer2', label: 'Tableau de bord' },
        { href: '#/courses', icon: 'journal-bookmark', label: 'Mes cours' },
        { href: '#/students', icon: 'mortarboard', label: 'Étudiants' },
        { href: '#/schedule', icon: 'calendar3', label: 'Emploi du temps' },
        { section: 'Communication' },
        { href: '#/messages', icon: 'envelope', label: 'Messagerie' },
    ],
    student: [
        { section: 'Mon espace' },
        { href: '#/dashboard', icon: 'speedometer2', label: 'Tableau de bord' },
        { href: '#/my-courses', icon: 'collection', label: 'Mes cours' },
        { href: '#/grades', icon: 'card-checklist', label: 'Mes notes' },
        { href: '#/schedule', icon: 'calendar3', label: 'Emploi du temps' },
        { section: 'Inscriptions' },
        { href: '#/courses', icon: 'journal-bookmark', label: 'Catalogue des cours' },
        { href: '#/messages', icon: 'envelope', label: 'Messagerie' },
    ],
};

const ROLE_LABEL = { admin: 'Administrateur', teacher: 'Enseignant', student: 'Étudiant' };

function buildSidebar() {
    const nav = document.getElementById('sidebar-nav');
    nav.innerHTML = (MENUS[store.role] || []).map(item => {
        if (item.section) return `<div class="sidebar-section">${esc(item.section)}</div>`;
        return `<a class="nav-link" href="${item.href}">
            <i class="bi bi-${item.icon}"></i><span>${esc(item.label)}</span></a>`;
    }).join('');
}

function highlightNav() {
    const current = '#/' + location.hash.replace(/^#\/?/, '').split('/')[0];
    document.querySelectorAll('#sidebar-nav .nav-link').forEach(a => {
        a.classList.toggle('active', a.getAttribute('href') === current);
    });
}

// ---------------------------------------------------------------------
//  NOTIFICATIONS (cloche)
// ---------------------------------------------------------------------
async function refreshNotifications() {
    if (!store.user) return;
    try {
        const res = await api.get('notifications');
        const badge = document.getElementById('notif-count');
        const list = document.getElementById('notif-list');
        if (res.unread > 0) { badge.textContent = res.unread; badge.classList.remove('d-none'); }
        else badge.classList.add('d-none');

        if (res.data.length === 0) {
            list.innerHTML = `<div class="p-3 text-muted small text-center">Aucune notification.</div>`;
            return;
        }
        list.innerHTML = `<div class="d-flex justify-content-between align-items-center px-3 py-2 border-bottom">
                <strong class="small">Notifications</strong>
                <button class="btn btn-sm btn-link p-0" id="notif-read-all">Tout marquer lu</button></div>`
            + res.data.slice(0, 10).map(n => `
            <a href="#/${esc(n.link || 'dashboard')}" class="dropdown-item notif-item ${n.is_read ? '' : 'unread'} py-2"
               data-notif-id="${n.id}">
                <div class="fw-semibold small">${esc(n.title)}</div>
                <div class="small text-muted text-truncate">${esc(n.content || '')}</div>
            </a>`).join('');

        document.getElementById('notif-read-all')?.addEventListener('click', async (e) => {
            e.preventDefault();
            await api.post('notifications/read-all', {});
            refreshNotifications();
        });
        list.querySelectorAll('[data-notif-id]').forEach(a => {
            a.addEventListener('click', async () => {
                await api.patch(`notifications/${a.dataset.notifId}/read`, {});
                setTimeout(refreshNotifications, 300);
            });
        });
    } catch (_) { /* silencieux */ }
}

// ---------------------------------------------------------------------
//  ÉCRAN D'AUTHENTIFICATION
// ---------------------------------------------------------------------
function showAuth() {
    document.getElementById('app-view').classList.add('d-none');
    document.getElementById('auth-view').classList.remove('d-none');
}
function showApp() {
    document.getElementById('auth-view').classList.add('d-none');
    document.getElementById('app-view').classList.remove('d-none');
    document.getElementById('nav-username').textContent = `${store.user.first_name} ${store.user.last_name}`;
    document.getElementById('nav-role').textContent = ROLE_LABEL[store.role] || store.role;
    buildSidebar();
    refreshNotifications();
}

function bindAuthForms() {
    // Onglets
    document.querySelectorAll('[data-auth-tab]').forEach(btn => {
        btn.addEventListener('click', () => {
            document.querySelectorAll('[data-auth-tab]').forEach(b => b.classList.remove('active'));
            btn.classList.add('active');
            const tab = btn.dataset.authTab;
            document.getElementById('login-form').classList.toggle('d-none', tab !== 'login');
            document.getElementById('register-form').classList.toggle('d-none', tab !== 'register');
        });
    });

    // Connexion
    document.getElementById('login-form').addEventListener('submit', async (e) => {
        e.preventDefault();
        const form = e.target;
        const err = form.querySelector('[data-error]');
        err.classList.add('d-none');
        try {
            await login(form.email.value.trim(), form.password.value);
            startApp();
        } catch (ex) {
            err.textContent = ex.message; err.classList.remove('d-none');
        }
    });

    // Inscription
    document.getElementById('register-form').addEventListener('submit', async (e) => {
        e.preventDefault();
        const form = e.target;
        const err = form.querySelector('[data-error]');
        err.classList.add('d-none');
        try {
            await register({
                first_name: form.first_name.value.trim(), last_name: form.last_name.value.trim(),
                email: form.email.value.trim(), password: form.password.value,
                filiere: form.filiere.value.trim(), niveau: form.niveau.value,
            });
            toast('Bienvenue sur SmartCampus !');
            startApp();
        } catch (ex) {
            err.textContent = ex.message; err.classList.remove('d-none');
        }
    });
}

function startApp() {
    showApp();
    if (!location.hash || location.hash === '#') location.hash = '#/dashboard';
    else startRouterOnce();
    startRouterOnce();
}

let routerStarted = false;
function startRouterOnce() {
    if (routerStarted) return;
    routerStarted = true;
    startRouter();
}

// ---------------------------------------------------------------------
//  INITIALISATION
// ---------------------------------------------------------------------
async function init() {
    configure(routes, () => highlightNav());
    bindAuthForms();

    // Déconnexion
    document.getElementById('logout-btn').addEventListener('click', async () => {
        await logout();
        location.hash = '';
        showAuth();
    });
    // Sidebar mobile
    document.getElementById('sidebar-toggle').addEventListener('click', () => {
        document.getElementById('sidebar').classList.toggle('open');
    });

    const user = await fetchMe();
    if (user) { showApp(); startRouterOnce(); if (!location.hash) location.hash = '#/dashboard'; }
    else showAuth();

    // Rafraîchit les notifications périodiquement
    setInterval(refreshNotifications, 30000);
}

// Expose la navigation pour les pages (liens programmatiques)
window.SC = { navigate, refreshNotifications };

init();
