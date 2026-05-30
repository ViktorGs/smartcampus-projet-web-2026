/**
 * Page Étudiants — liste avec recherche / filtres / tri.
 * L'admin peut ajouter, modifier, supprimer. L'enseignant consulte uniquement.
 */
import { api, ApiError } from '../api.js';
import { store } from '../store.js';
import { esc, pageHeader, openModal, confirmDialog, toast, showFormErrors, spinner } from '../ui.js';

const NIVEAUX = ['L1','L2','L3','M1','M2'];
let state = { q: '', filiere: '', niveau: '', sort: 'name', dir: 'asc' };

export async function render(container) {
    const isAdmin = store.role === 'admin';
    container.innerHTML = pageHeader('Gestion des étudiants',
        isAdmin ? `<button class="btn btn-primary" id="add-student"><i class="bi bi-person-plus me-1"></i>Ajouter</button>` : '')
        + filterBar() + `<div id="students-table">${spinner()}</div>`;

    bindFilters(container, () => load(container));
    if (isAdmin) document.getElementById('add-student').addEventListener('click', () => openForm(container));
    load(container);
}

function filterBar() {
    return `<div class="card shadow-sm mb-3"><div class="card-body">
        <div class="row g-2 align-items-end">
            <div class="col-md-4"><label class="form-label small mb-1">Recherche</label>
                <input class="form-control" id="f-q" placeholder="Nom, n° étudiant, email…"></div>
            <div class="col-md-3"><label class="form-label small mb-1">Filière</label>
                <input class="form-control" id="f-filiere" placeholder="Toutes"></div>
            <div class="col-md-2"><label class="form-label small mb-1">Niveau</label>
                <select class="form-select" id="f-niveau"><option value="">Tous</option>
                ${NIVEAUX.map(n => `<option>${n}</option>`).join('')}</select></div>
            <div class="col-md-3"><label class="form-label small mb-1">Trier par</label>
                <select class="form-select" id="f-sort">
                    <option value="name">Nom</option><option value="number">N° étudiant</option>
                    <option value="niveau">Niveau</option><option value="filiere">Filière</option>
                    <option value="date">Date d'inscription</option></select></div>
        </div></div></div>`;
}

function bindFilters(container, reload) {
    const q = container.querySelector('#f-q');
    let timer;
    q.addEventListener('input', () => { clearTimeout(timer); timer = setTimeout(() => { state.q = q.value; reload(); }, 300); });
    container.querySelector('#f-filiere').addEventListener('input', (e) => { clearTimeout(timer); timer = setTimeout(() => { state.filiere = e.target.value; reload(); }, 300); });
    container.querySelector('#f-niveau').addEventListener('change', (e) => { state.niveau = e.target.value; reload(); });
    container.querySelector('#f-sort').addEventListener('change', (e) => { state.sort = e.target.value; reload(); });
}

async function load(container) {
    const params = new URLSearchParams(state).toString();
    const { data } = await api.get(`students?${params}`);
    const isAdmin = store.role === 'admin';
    const target = document.getElementById('students-table');

    if (!data.length) { target.innerHTML = `<div class="alert alert-info">Aucun étudiant trouvé.</div>`; return; }

    target.innerHTML = `<div class="card shadow-sm"><div class="table-responsive"><table class="table table-hover align-middle mb-0">
        <thead class="table-light"><tr>
            <th>N° étudiant</th><th>Nom</th><th>Email</th><th>Filière</th><th>Niveau</th><th>Groupe</th><th class="text-end">Actions</th>
        </tr></thead><tbody>
        ${data.map(s => `<tr>
            <td><span class="badge text-bg-light border">${esc(s.student_number)}</span></td>
            <td class="fw-semibold">${esc(s.last_name)} ${esc(s.first_name)}</td>
            <td class="small text-muted">${esc(s.email)}</td>
            <td>${esc(s.filiere)}</td>
            <td><span class="badge text-bg-secondary">${esc(s.niveau)}</span></td>
            <td>${esc(s.group_td || '—')}</td>
            <td class="text-end table-actions">
                <a class="btn btn-sm btn-outline-secondary" href="#/students/${s.id}" title="Profil"><i class="bi bi-eye"></i></a>
                ${isAdmin ? `<button class="btn btn-sm btn-outline-primary" data-edit="${s.id}"><i class="bi bi-pencil"></i></button>
                <button class="btn btn-sm btn-outline-danger" data-del="${s.id}" data-name="${esc(s.last_name)} ${esc(s.first_name)}"><i class="bi bi-trash"></i></button>` : ''}
            </td></tr>`).join('')}
        </tbody></table></div></div>
        <p class="text-muted small mt-2">${data.length} étudiant(s).</p>`;

    if (isAdmin) {
        target.querySelectorAll('[data-edit]').forEach(b => b.addEventListener('click', () => openForm(container, b.dataset.edit)));
        target.querySelectorAll('[data-del]').forEach(b => b.addEventListener('click', () => removeStudent(container, b.dataset.del, b.dataset.name)));
    }
}

async function openForm(container, id = null) {
    let s = { first_name:'', last_name:'', email:'', filiere:'', niveau:'L1', group_td:'', date_naissance:'', phone:'', gender:'', address:'' };
    if (id) { s = (await api.get(`students/${id}`)).data; }

    const body = `<form id="student-form">
        <div class="row g-2">
            <div class="col-6"><label class="form-label">Prénom *</label><input name="first_name" class="form-control" value="${esc(s.first_name)}"></div>
            <div class="col-6"><label class="form-label">Nom *</label><input name="last_name" class="form-control" value="${esc(s.last_name)}"></div>
            <div class="col-8"><label class="form-label">E-mail *</label><input name="email" type="email" class="form-control" value="${esc(s.email)}"></div>
            <div class="col-4"><label class="form-label">Sexe</label><select name="gender" class="form-select">
                <option value="">—</option><option value="M" ${s.gender==='M'?'selected':''}>M</option>
                <option value="F" ${s.gender==='F'?'selected':''}>F</option><option value="Autre" ${s.gender==='Autre'?'selected':''}>Autre</option></select></div>
            ${id ? '' : `<div class="col-12"><label class="form-label">Mot de passe * (min. 8)</label><input name="password" type="password" class="form-control"></div>`}
            <div class="col-7"><label class="form-label">Filière *</label><input name="filiere" class="form-control" value="${esc(s.filiere)}"></div>
            <div class="col-5"><label class="form-label">Niveau *</label><select name="niveau" class="form-select">
                ${NIVEAUX.map(n => `<option ${s.niveau===n?'selected':''}>${n}</option>`).join('')}</select></div>
            <div class="col-6"><label class="form-label">Groupe TD</label><input name="group_td" class="form-control" value="${esc(s.group_td||'')}"></div>
            <div class="col-6"><label class="form-label">Date de naissance</label><input name="date_naissance" type="date" class="form-control" value="${esc((s.date_naissance||'').slice(0,10))}"></div>
            <div class="col-6"><label class="form-label">Téléphone</label><input name="phone" class="form-control" value="${esc(s.phone||'')}"></div>
            <div class="col-12"><label class="form-label">Adresse</label><input name="address" class="form-control" value="${esc(s.address||'')}"></div>
        </div></form>`;
    const footer = `<button class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
        <button class="btn btn-primary" id="save-student">Enregistrer</button>`;
    const { modal, modalEl } = openModal({ title: id ? 'Modifier un étudiant' : 'Ajouter un étudiant', bodyHtml: body, footerHtml: footer, size: 'modal-lg' });

    modalEl.querySelector('#save-student').addEventListener('click', async () => {
        const form = modalEl.querySelector('#student-form');
        const payload = Object.fromEntries(new FormData(form).entries());
        try {
            if (id) await api.put(`students/${id}`, payload);
            else await api.post('students', payload);
            modal.hide();
            toast(id ? 'Étudiant mis à jour.' : 'Étudiant créé.');
            load(container);
        } catch (ex) {
            if (ex instanceof ApiError && ex.status === 422 || ex.fields) showFormErrors(form, ex);
            toast(ex.message, 'danger');
        }
    });
}

async function removeStudent(container, id, name) {
    if (!await confirmDialog(`Supprimer l'étudiant ${name} ? Cette action est irréversible.`, 'Supprimer')) return;
    try { await api.del(`students/${id}`); toast('Étudiant supprimé.'); load(container); }
    catch (ex) { toast(ex.message, 'danger'); }
}
