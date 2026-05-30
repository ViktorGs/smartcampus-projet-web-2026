/**
 * Page Enseignants — liste, recherche, et CRUD pour l'administrateur.
 */
import { api, ApiError } from '../api.js';
import { store } from '../store.js';
import { esc, pageHeader, openModal, confirmDialog, toast, showFormErrors, spinner } from '../ui.js';

const GRADES = ['Professeur','Maître de conférences','Maître assistant','Vacataire'];
let q = '';

export async function render(container) {
    const isAdmin = store.role === 'admin';
    container.innerHTML = pageHeader('Gestion des enseignants',
        isAdmin ? `<button class="btn btn-primary" id="add-teacher"><i class="bi bi-person-plus me-1"></i>Ajouter</button>` : '')
        + `<div class="card shadow-sm mb-3"><div class="card-body">
            <input class="form-control" id="t-q" placeholder="Rechercher par nom ou email…"></div></div>
        <div id="teachers-table">${spinner()}</div>`;

    const search = container.querySelector('#t-q');
    let timer;
    search.addEventListener('input', () => { clearTimeout(timer); timer = setTimeout(() => { q = search.value; load(container); }, 300); });
    if (isAdmin) document.getElementById('add-teacher').addEventListener('click', () => openForm(container));
    load(container);
}

async function load(container) {
    const { data } = await api.get(`teachers?q=${encodeURIComponent(q)}`);
    const isAdmin = store.role === 'admin';
    const target = document.getElementById('teachers-table');
    if (!data.length) { target.innerHTML = `<div class="alert alert-info">Aucun enseignant.</div>`; return; }

    target.innerHTML = `<div class="card shadow-sm"><div class="table-responsive"><table class="table table-hover align-middle mb-0">
        <thead class="table-light"><tr><th>N°</th><th>Nom</th><th>Email</th><th>Département</th><th>Grade</th><th class="text-center">Cours</th><th class="text-end">Actions</th></tr></thead>
        <tbody>${data.map(t => `<tr>
            <td><span class="badge text-bg-light border">${esc(t.employee_number)}</span></td>
            <td class="fw-semibold">${esc(t.last_name)} ${esc(t.first_name)}</td>
            <td class="small text-muted">${esc(t.email)}</td>
            <td>${esc(t.department)}</td><td>${esc(t.grade)}</td>
            <td class="text-center"><span class="badge text-bg-secondary">${t.course_count}</span></td>
            <td class="text-end table-actions">
                ${isAdmin ? `<button class="btn btn-sm btn-outline-primary" data-edit="${t.id}"><i class="bi bi-pencil"></i></button>
                <button class="btn btn-sm btn-outline-danger" data-del="${t.id}" data-name="${esc(t.last_name)} ${esc(t.first_name)}"><i class="bi bi-trash"></i></button>` : '<span class="text-muted">—</span>'}
            </td></tr>`).join('')}
        </tbody></table></div></div>`;

    if (isAdmin) {
        target.querySelectorAll('[data-edit]').forEach(b => b.addEventListener('click', () => openForm(container, b.dataset.edit)));
        target.querySelectorAll('[data-del]').forEach(b => b.addEventListener('click', () => removeTeacher(container, b.dataset.del, b.dataset.name)));
    }
}

async function openForm(container, id = null) {
    let t = { first_name:'', last_name:'', email:'', department:'', grade:'Maître assistant', office:'', phone:'', gender:'' };
    if (id) t = (await api.get(`teachers/${id}`)).data;

    const body = `<form id="teacher-form"><div class="row g-2">
        <div class="col-6"><label class="form-label">Prénom *</label><input name="first_name" class="form-control" value="${esc(t.first_name)}"></div>
        <div class="col-6"><label class="form-label">Nom *</label><input name="last_name" class="form-control" value="${esc(t.last_name)}"></div>
        <div class="col-8"><label class="form-label">E-mail *</label><input name="email" type="email" class="form-control" value="${esc(t.email)}"></div>
        <div class="col-4"><label class="form-label">Sexe</label><select name="gender" class="form-select">
            <option value="">—</option><option value="M" ${t.gender==='M'?'selected':''}>M</option>
            <option value="F" ${t.gender==='F'?'selected':''}>F</option></select></div>
        ${id ? '' : `<div class="col-12"><label class="form-label">Mot de passe * (min. 8)</label><input name="password" type="password" class="form-control"></div>`}
        <div class="col-6"><label class="form-label">Département *</label><input name="department" class="form-control" value="${esc(t.department)}"></div>
        <div class="col-6"><label class="form-label">Grade</label><select name="grade" class="form-select">
            ${GRADES.map(g => `<option ${t.grade===g?'selected':''}>${g}</option>`).join('')}</select></div>
        <div class="col-6"><label class="form-label">Bureau</label><input name="office" class="form-control" value="${esc(t.office||'')}"></div>
        <div class="col-6"><label class="form-label">Téléphone</label><input name="phone" class="form-control" value="${esc(t.phone||'')}"></div>
    </div></form>`;
    const footer = `<button class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button><button class="btn btn-primary" id="save">Enregistrer</button>`;
    const { modal, modalEl } = openModal({ title: id ? 'Modifier un enseignant' : 'Ajouter un enseignant', bodyHtml: body, footerHtml: footer, size: 'modal-lg' });

    modalEl.querySelector('#save').addEventListener('click', async () => {
        const form = modalEl.querySelector('#teacher-form');
        const payload = Object.fromEntries(new FormData(form).entries());
        try {
            if (id) await api.put(`teachers/${id}`, payload); else await api.post('teachers', payload);
            modal.hide(); toast('Enregistré.'); load(container);
        } catch (ex) { showFormErrors(form, ex); toast(ex.message, 'danger'); }
    });
}

async function removeTeacher(container, id, name) {
    if (!await confirmDialog(`Supprimer l'enseignant ${name} ?`, 'Supprimer')) return;
    try { await api.del(`teachers/${id}`); toast('Enseignant supprimé.'); load(container); }
    catch (ex) { toast(ex.message, 'danger'); }
}
