/**
 * Page Messagerie interne (bonus) — boîte de réception, envoyés, composition.
 * Les destinataires autorisés dépendent du rôle (renvoyés par l'API).
 */
import { api } from '../api.js';
import { esc, pageHeader, openModal, toast, spinner } from '../ui.js';

let tab = 'inbox';

export async function render(container) {
    container.innerHTML = pageHeader('Messagerie',
        `<button class="btn btn-primary" id="compose"><i class="bi bi-pencil-square me-1"></i>Nouveau message</button>`)
        + `<ul class="nav nav-tabs mb-3">
            <li class="nav-item"><button class="nav-link ${tab==='inbox'?'active':''}" data-tab="inbox"><i class="bi bi-inbox me-1"></i>Reçus</button></li>
            <li class="nav-item"><button class="nav-link ${tab==='sent'?'active':''}" data-tab="sent"><i class="bi bi-send me-1"></i>Envoyés</button></li>
        </ul><div id="msg-list">${spinner()}</div>`;

    container.querySelectorAll('[data-tab]').forEach(b => b.addEventListener('click', () => {
        tab = b.dataset.tab;
        container.querySelectorAll('[data-tab]').forEach(x => x.classList.toggle('active', x.dataset.tab === tab));
        load();
    }));
    document.getElementById('compose').addEventListener('click', compose);
    load();
}

async function load() {
    const target = document.getElementById('msg-list');
    const { data } = await api.get(tab === 'inbox' ? 'messages' : 'messages/sent');
    if (!data.length) { target.innerHTML = `<div class="alert alert-info">Aucun message.</div>`; return; }

    target.innerHTML = `<div class="list-group shadow-sm">${data.map(m => {
        const who = tab === 'inbox' ? m.sender_name : `À : ${m.recipient_name}`;
        const unread = tab === 'inbox' && Number(m.is_read) === 0;
        return `<button class="list-group-item list-group-item-action ${unread?'fw-semibold':''}" data-msg="${m.id}">
            <div class="d-flex justify-content-between">
                <span>${unread?'<i class="bi bi-circle-fill text-primary me-1" style="font-size:.5rem;vertical-align:middle"></i>':''}${esc(who)}</span>
                <small class="text-muted">${esc((m.sent_at||'').slice(0,16).replace('T',' '))}</small></div>
            <div>${esc(m.subject)}</div></button>`;
    }).join('')}</div>`;

    target.querySelectorAll('[data-msg]').forEach(b => b.addEventListener('click', () => openMessage(b.dataset.msg)));
}

async function openMessage(id) {
    const { data: m } = await api.get(`messages/${id}`);
    openModal({
        title: esc(m.subject),
        bodyHtml: `<p class="text-muted small mb-2">De : ${esc(m.sender_name)} · ${esc((m.sent_at||'').slice(0,16).replace('T',' '))}</p>
            <p style="white-space:pre-wrap">${esc(m.body)}</p>`,
        footerHtml: `<button class="btn btn-secondary" data-bs-dismiss="modal">Fermer</button>`,
    });
    if (tab === 'inbox') setTimeout(() => { load(); window.SC?.refreshNotifications(); }, 400);
}

async function compose() {
    const { data: recipients } = await api.get('messages/recipients/list');
    if (!recipients.length) { toast('Aucun destinataire disponible pour votre rôle.', 'warning'); return; }
    const roleLabel = { admin: 'Admin', teacher: 'Enseignant', student: 'Étudiant' };
    const body = `<form id="msg-form">
        <div class="mb-2"><label class="form-label">Destinataire *</label><select name="recipient_id" class="form-select">
            ${recipients.map(r => `<option value="${r.id}">${esc(r.name)} — ${roleLabel[r.role]||r.role}</option>`).join('')}</select></div>
        <div class="mb-2"><label class="form-label">Sujet *</label><input name="subject" class="form-control"></div>
        <div class="mb-2"><label class="form-label">Message *</label><textarea name="body" class="form-control" rows="5"></textarea></div>
    </form>`;
    const footer = `<button class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button><button class="btn btn-primary" id="send">Envoyer</button>`;
    const { modal, modalEl } = openModal({ title: 'Nouveau message', bodyHtml: body, footerHtml: footer });

    modalEl.querySelector('#send').addEventListener('click', async () => {
        const p = Object.fromEntries(new FormData(modalEl.querySelector('#msg-form')).entries());
        try { await api.post('messages', p); modal.hide(); toast('Message envoyé.'); if (tab==='sent') load(); }
        catch (ex) { toast(ex.message, 'danger'); }
    });
}
