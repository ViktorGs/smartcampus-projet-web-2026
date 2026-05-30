/**
 * Helpers d'interface réutilisables : échappement HTML (anti-XSS côté rendu),
 * toasts, fenêtres de confirmation, formatage, états de chargement.
 */

/** Échappe le HTML pour éviter toute injection lors de l'insertion en innerHTML. */
export function esc(value) {
    if (value === null || value === undefined) return '';
    return String(value)
        .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;').replace(/'/g, '&#039;');
}

/** Toast éphémère (coin bas-droit). type: success | danger | warning | info */
export function toast(message, type = 'success') {
    const container = document.getElementById('toast-container');
    const el = document.createElement('div');
    el.className = `toast align-items-center text-bg-${type} border-0`;
    el.setAttribute('role', 'alert');
    el.innerHTML = `<div class="d-flex">
        <div class="toast-body">${esc(message)}</div>
        <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
    </div>`;
    container.appendChild(el);
    const t = new bootstrap.Toast(el, { delay: 3500 });
    t.show();
    el.addEventListener('hidden.bs.toast', () => el.remove());
}

/** Fenêtre de confirmation (Promise<boolean>). */
export function confirmDialog(message, title = 'Confirmation') {
    return new Promise((resolve) => {
        const id = 'confirm-' + Date.now();
        const wrapper = document.createElement('div');
        wrapper.innerHTML = `
        <div class="modal fade" id="${id}" tabindex="-1">
          <div class="modal-dialog"><div class="modal-content">
            <div class="modal-header"><h5 class="modal-title">${esc(title)}</h5>
              <button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <div class="modal-body">${esc(message)}</div>
            <div class="modal-footer">
              <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
              <button type="button" class="btn btn-danger" data-confirm>Confirmer</button>
            </div>
          </div></div>
        </div>`;
        document.body.appendChild(wrapper);
        const modalEl = wrapper.querySelector('.modal');
        const modal = new bootstrap.Modal(modalEl);
        modal.show();
        let confirmed = false;
        wrapper.querySelector('[data-confirm]').addEventListener('click', () => { confirmed = true; modal.hide(); });
        modalEl.addEventListener('hidden.bs.modal', () => { wrapper.remove(); resolve(confirmed); });
    });
}

/**
 * Affiche une modale générique avec contenu HTML.
 * Renvoie l'instance bootstrap.Modal pour la piloter (ex: cacher après save).
 */
export function openModal({ title, bodyHtml, footerHtml = '', size = '' }) {
    const id = 'modal-' + Date.now();
    const wrapper = document.createElement('div');
    wrapper.innerHTML = `
    <div class="modal fade" id="${id}" tabindex="-1">
      <div class="modal-dialog ${size}"><div class="modal-content">
        <div class="modal-header"><h5 class="modal-title">${title}</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
        <div class="modal-body">${bodyHtml}</div>
        ${footerHtml ? `<div class="modal-footer">${footerHtml}</div>` : ''}
      </div></div>
    </div>`;
    document.body.appendChild(wrapper);
    const modalEl = wrapper.querySelector('.modal');
    const modal = new bootstrap.Modal(modalEl);
    modal.show();
    modalEl.addEventListener('hidden.bs.modal', () => wrapper.remove());
    return { modal, modalEl };
}

/** Indicateur de chargement centré. */
export function spinner() {
    return `<div class="text-center py-5"><div class="spinner-border text-primary"></div></div>`;
}

/** En-tête de page standard (titre + zone d'actions à droite). */
export function pageHeader(title, actionsHtml = '') {
    return `<div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-2">
        <h2 class="page-title mb-0">${esc(title)}</h2>
        <div class="d-flex gap-2">${actionsHtml}</div>
    </div>`;
}

const DAYS = ['', 'Lundi', 'Mardi', 'Mercredi', 'Jeudi', 'Vendredi', 'Samedi', 'Dimanche'];
export const dayName = (n) => DAYS[n] || '';
export const hhmm = (t) => (t ? String(t).slice(0, 5) : '');

/** Affiche les erreurs de validation d'un ApiError dans un formulaire. */
export function showFormErrors(formEl, error) {
    formEl.querySelectorAll('.is-invalid').forEach(e => e.classList.remove('is-invalid'));
    formEl.querySelectorAll('.field-error').forEach(e => e.remove());
    const fields = error.fields || {};
    Object.entries(fields).forEach(([name, msg]) => {
        const input = formEl.querySelector(`[name="${name}"]`);
        if (input && typeof msg === 'string') {
            input.classList.add('is-invalid');
            const div = document.createElement('div');
            div.className = 'invalid-feedback field-error d-block';
            div.textContent = msg;
            input.after(div);
        }
    });
}
