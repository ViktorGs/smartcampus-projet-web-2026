/**
 * Routeur côté client basé sur le hash (#/...).
 * Gère le rendu des pages (chargées dynamiquement) et le contrôle d'accès
 * par rôle. Une seule page HTML, un contenu dynamique => véritable SPA.
 */
import { store } from './store.js';
import { spinner } from './ui.js';

let routes = [];
let onRouteChange = null;

/** Enregistre la table des routes. Chaque route : {path, roles, load}. */
export function configure(routeTable, hook) {
    routes = routeTable;
    onRouteChange = hook;
}

/** Découpe une route "courses/:id" et l'URL courante, renvoie les params. */
function matchRoute(pattern, segments) {
    const parts = pattern.split('/').filter(Boolean);
    if (parts.length !== segments.length) return null;
    const params = {};
    for (let i = 0; i < parts.length; i++) {
        if (parts[i].startsWith(':')) params[parts[i].slice(1)] = decodeURIComponent(segments[i]);
        else if (parts[i] !== segments[i]) return null;
    }
    return params;
}

async function render() {
    const hash = location.hash.replace(/^#\/?/, '');
    const segments = hash.split('/').filter(Boolean);
    const content = document.getElementById('page-content');

    // Route par défaut
    if (segments.length === 0) { location.hash = '#/dashboard'; return; }

    for (const route of routes) {
        const params = matchRoute(route.path, segments);
        if (!params) continue;

        // Contrôle d'accès par rôle
        if (route.roles && !route.roles.includes(store.role)) {
            content.innerHTML = `<div class="alert alert-danger m-4">
                <i class="bi bi-shield-lock me-2"></i>Accès refusé : cette page n'est pas disponible pour votre rôle.</div>`;
            if (onRouteChange) onRouteChange(route);
            return;
        }

        content.innerHTML = spinner();
        try {
            const mod = await route.load();
            await mod.render(content, params);
        } catch (e) {
            content.innerHTML = `<div class="alert alert-danger m-4">Erreur de chargement : ${e.message}</div>`;
        }
        if (onRouteChange) onRouteChange(route);
        return;
    }

    content.innerHTML = `<div class="alert alert-warning m-4">Page introuvable.</div>`;
}

export function startRouter() {
    window.addEventListener('hashchange', render);
    render();
}

export function navigate(path) { location.hash = path; }
