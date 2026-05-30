/**
 * Client API — couche d'accès unique au backend REST.
 * Centralise : URL de base, en-tête CSRF, parsing JSON, gestion des erreurs.
 */
import { store } from './store.js';

// Base relative : fonctionne que l'app soit servie à la racine ou sous /smartcampus/
const API_BASE = 'api';

/** Erreur applicative enrichie (message + erreurs de champ + code HTTP). */
export class ApiError extends Error {
    constructor(message, status, fields = {}) {
        super(message);
        this.status = status;
        this.fields = fields;
    }
}

async function request(method, path, body = null) {
    const headers = {};
    const options = { method, headers, credentials: 'same-origin' };

    if (body !== null) {
        headers['Content-Type'] = 'application/json';
        options.body = JSON.stringify(body);
    }
    // Jeton CSRF sur toute requête qui modifie l'état
    if (!['GET', 'HEAD'].includes(method) && store.csrf) {
        headers['X-CSRF-Token'] = store.csrf;
    }

    const res = await fetch(`${API_BASE}/${path}`, options);

    // 204 No Content
    if (res.status === 204) return null;

    let data = null;
    try { data = await res.json(); } catch (_) { /* réponse non JSON */ }

    if (!res.ok) {
        const msg = (data && data.error) || `Erreur ${res.status}`;
        throw new ApiError(msg, res.status, (data && data.fields) || {});
    }
    return data;
}

export const api = {
    get:  (p)        => request('GET', p),
    post: (p, body)  => request('POST', p, body),
    put:  (p, body)  => request('PUT', p, body),
    patch:(p, body)  => request('PATCH', p, body),
    del:  (p, body)  => request('DELETE', p, body ?? {}),
};
