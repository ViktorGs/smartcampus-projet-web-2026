/**
 * Authentification côté client : connexion, inscription, déconnexion,
 * récupération de la session courante.
 */
import { api } from './api.js';
import { store } from './store.js';

export async function fetchMe() {
    try {
        const res = await api.get('auth/me');
        store.set(res.user, res.csrf);
        return res.user;
    } catch (_) {
        store.clear();
        return null;
    }
}

export async function login(email, password) {
    const res = await api.post('auth/login', { email, password });
    store.set(res.user, res.csrf);
    return res.user;
}

export async function register(payload) {
    const res = await api.post('auth/register', payload);
    store.set(res.user, res.csrf);
    return res.user;
}

export async function logout() {
    try { await api.post('auth/logout', {}); } catch (_) {}
    store.clear();
}
