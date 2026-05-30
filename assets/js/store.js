/**
 * État partagé de l'application côté client (utilisateur connecté + jeton CSRF).
 * Volontairement minimal : pas de framework, juste un objet partagé.
 */
export const store = {
    user: null,   // { id, role, first_name, last_name, ... }
    csrf: null,   // jeton CSRF de la session

    set(user, csrf) {
        this.user = user;
        if (csrf) this.csrf = csrf;
    },
    clear() {
        this.user = null;
        this.csrf = null;
    },
    get role() { return this.user ? this.user.role : null; },
    get id()   { return this.user ? this.user.id : null; },
};
