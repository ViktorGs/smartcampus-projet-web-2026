/**
 * Page Cours — catalogue filtrable et triable.
 *  - admin    : CRUD complet + archivage ;
 *  - enseignant : voit ses cours (et le catalogue) ;
 *  - étudiant  : catalogue + bouton d'inscription (avec retours des règles métier).
 */
import { api, ApiError } from '../api.js';
import { store } from '../store.js';
import { esc, pageHeader, openModal, confirmDialog, toast, showFormErrors, spinner } from '../ui.js';

const NIVEAUX = ['L1','L2','L3','M1','M2'];
const SEMESTRES = ['S1','S2','S3','S4','S5','S6','S7','S8','S9','S10'];
let state = { q:'', niveau:'', semester:'', department:'', sort:'code', dir:'asc' };

export async function render(container) {
    const isAdmin = store.role === 'admin';
    const title = store.role === 'teacher' ? 'Mes cours' : (store.role === 'student' ? 'Catalogue des cours' : 'Gestion des cours');
    // Par défaut, l'enseignant voit ses propres cours
    if (store.role === 'teacher') state.teacher_id = store.id; else delete state.teacher_id;

    container.innerHTML = pageHeader(title,
        isAdmin ? `<button class="btn btn-primary" id="add-course"><i class="bi bi-plus-lg me-1"></i>Nouveau cours</button>` : '')
        + filterBar() + `<div id="courses-list">${spinner()}</div>`;

    bindFilters(container);
    if (isAdmin) document.getElementById('add-course').addEventListener('click', () => openForm(container));
    load(container);
}

function filterBar() {
    return `<div class="card shadow-sm mb-3"><div class="card-body"><div class="row g-2 align-items-end">
        <div class="col-md-3"><label class="form-label small mb-1">Recherche</label><input class="form-control" id="f-q" placeholder="Code, intitulé…"></div>
        <div class="col-md-2"><label class="form-label small mb-1">Niveau</label><select class="form-select" id="f-niveau">
            <option value="">Tous</option>${NIVEAUX.map(n=>`<option>${n}</option>`).join('')}</select></div>
        <div class="col-md-2"><label class="form-label small mb-1">Semestre</label><select class="form-select" id="f-sem">
            <option value="">Tous</option>${SEMESTRES.map(s=>`<option>${s}</option>`).join('')}</select></div>
        <div class="col-md-3"><label class="form-label small mb-1">Département</label><input class="form-control" id="f-dep" placeholder="Tous"></div>
        <div class="col-md-2"><label class="form-label small mb-1">Trier</label><select class="form-select" id="f-sort">
            <option value="code">Code</option><option value="name">Intitulé</option><option value="credits">ECTS</option>
            <option value="niveau">Niveau</option><option value="semester">Semestre</option></select></div>
    </div></div></div>`;
}

function bindFilters(container) {
    const reload = () => load(container);
    let timer;
    const deb = (fn) => { clearTimeout(timer); timer = setTimeout(fn, 300); };
    container.querySelector('#f-q').addEventListener('input', e => deb(() => { state.q = e.target.value; reload(); }));
    container.querySelector('#f-dep').addEventListener('input', e => deb(() => { state.department = e.target.value; reload(); }));
    container.querySelector('#f-niveau').addEventListener('change', e => { state.niveau = e.target.value; reload(); });
    container.querySelector('#f-sem').addEventListener('change', e => { state.semester = e.target.value; reload(); });
    container.querySelector('#f-sort').addEventListener('change', e => { state.sort = e.target.value; reload(); });
}

async function load(container) {
    const params = new URLSearchParams(state).toString();
    const { data } = await api.get(`courses?${params}`);
    const target = document.getElementById('courses-list');
    if (!data.length) { target.innerHTML = `<div class="alert alert-info">Aucun cours.</div>`; return; }

    target.innerHTML = `<div class="row g-3">${data.map(c => courseCard(c)).join('')}</div>`;
    bindCardActions(container, target);
}

function courseCard(c) {
    const full = Number(c.enrolled) >= Number(c.capacity);
    const isAdmin = store.role === 'admin';
    const isStudent = store.role === 'student';
    return `<div class="col-md-6 col-xl-4"><div class="card shadow-sm h-100">
        <div class="card-body d-flex flex-column">
            <div class="d-flex justify-content-between mb-2">
                <span class="badge text-bg-primary">${esc(c.code)}</span>
                <span class="badge text-bg-light border">${esc(c.niveau)} · ${esc(c.semester)}</span>
            </div>
            <h5 class="card-title">${esc(c.name)}</h5>
            <p class="card-text small text-muted flex-grow-1">${esc((c.description||'').slice(0,90))}${(c.description||'').length>90?'…':''}</p>
            <div class="small mb-2">
                <div><i class="bi bi-person-video3 me-1"></i>${esc(c.teacher_name || 'Non assigné')}</div>
                <div><i class="bi bi-award me-1"></i>${esc(c.credits)} ECTS · ${esc(c.department)}</div>
                <div><i class="bi bi-people me-1"></i>${c.enrolled}/${c.capacity} inscrits ${full?'<span class="badge text-bg-danger ms-1">Complet</span>':''}</div>
            </div>
            <div class="d-flex gap-2 mt-auto table-actions">
                <a class="btn btn-sm btn-outline-secondary flex-fill" href="#/courses/${c.id}"><i class="bi bi-eye me-1"></i>Détails</a>
                ${isStudent ? `<button class="btn btn-sm btn-success flex-fill" data-enroll="${c.id}" ${full?'disabled':''}><i class="bi bi-plus-circle me-1"></i>S'inscrire</button>` : ''}
                ${isAdmin ? `<button class="btn btn-sm btn-outline-primary" data-edit="${c.id}"><i class="bi bi-pencil"></i></button>
                    <button class="btn btn-sm btn-outline-warning" data-archive="${c.id}" title="Archiver"><i class="bi bi-archive"></i></button>
                    <button class="btn btn-sm btn-outline-danger" data-del="${c.id}"><i class="bi bi-trash"></i></button>` : ''}
            </div>
        </div></div></div>`;
}

function bindCardActions(container, target) {
    target.querySelectorAll('[data-enroll]').forEach(b => b.addEventListener('click', () => enroll(container, b.dataset.enroll)));
    target.querySelectorAll('[data-edit]').forEach(b => b.addEventListener('click', () => openForm(container, b.dataset.edit)));
    target.querySelectorAll('[data-del]').forEach(b => b.addEventListener('click', () => removeCourse(container, b.dataset.del)));
    target.querySelectorAll('[data-archive]').forEach(b => b.addEventListener('click', () => archive(container, b.dataset.archive)));
}

async function enroll(container, courseId) {
    try {
        await api.post('enrollments', { course_id: Number(courseId) });
        toast('Inscription enregistrée !');
        window.SC?.refreshNotifications();
        load(container);
    } catch (ex) {
        // Les messages des règles métier (capacité, prérequis, conflit…) remontent ici
        toast(ex.message, 'warning');
    }
}

async function openForm(container, id = null) {
    const teachers = (await api.get('teachers')).data;
    let c = { code:'', name:'', description:'', credits:3, semester:'S1', niveau:'L1', department:'', teacher_id:'', capacity:30 };
    if (id) c = (await api.get(`courses/${id}`)).data;

    const body = `<form id="course-form"><div class="row g-2">
        <div class="col-4"><label class="form-label">Code *</label><input name="code" class="form-control" value="${esc(c.code)}"></div>
        <div class="col-8"><label class="form-label">Intitulé *</label><input name="name" class="form-control" value="${esc(c.name)}"></div>
        <div class="col-12"><label class="form-label">Description</label><textarea name="description" class="form-control" rows="2">${esc(c.description||'')}</textarea></div>
        <div class="col-4"><label class="form-label">Niveau *</label><select name="niveau" class="form-select">${NIVEAUX.map(n=>`<option ${c.niveau===n?'selected':''}>${n}</option>`).join('')}</select></div>
        <div class="col-4"><label class="form-label">Semestre *</label><select name="semester" class="form-select">${SEMESTRES.map(s=>`<option ${c.semester===s?'selected':''}>${s}</option>`).join('')}</select></div>
        <div class="col-4"><label class="form-label">ECTS *</label><input name="credits" type="number" min="1" max="30" class="form-control" value="${esc(c.credits)}"></div>
        <div class="col-6"><label class="form-label">Département *</label><input name="department" class="form-control" value="${esc(c.department)}"></div>
        <div class="col-6"><label class="form-label">Capacité *</label><input name="capacity" type="number" min="1" max="500" class="form-control" value="${esc(c.capacity)}"></div>
        <div class="col-12"><label class="form-label">Enseignant responsable</label><select name="teacher_id" class="form-select">
            <option value="">— Non assigné —</option>
            ${teachers.map(t => `<option value="${t.id}" ${String(c.teacher_id)===String(t.id)?'selected':''}>${esc(t.last_name)} ${esc(t.first_name)} (${esc(t.department)})</option>`).join('')}
        </select></div>
    </div></form>`;
    const footer = `<button class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button><button class="btn btn-primary" id="save">Enregistrer</button>`;
    const { modal, modalEl } = openModal({ title: id ? 'Modifier le cours' : 'Nouveau cours', bodyHtml: body, footerHtml: footer, size: 'modal-lg' });

    modalEl.querySelector('#save').addEventListener('click', async () => {
        const form = modalEl.querySelector('#course-form');
        const payload = Object.fromEntries(new FormData(form).entries());
        try {
            if (id) await api.put(`courses/${id}`, payload); else await api.post('courses', payload);
            modal.hide(); toast('Cours enregistré.'); load(container);
        } catch (ex) { showFormErrors(form, ex); toast(ex.message, 'danger'); }
    });
}

async function archive(container, id) {
    if (!await confirmDialog('Archiver ce cours ? Il ne sera plus proposé aux inscriptions.', 'Archiver')) return;
    try { await api.patch(`courses/${id}/archive`, { archived: true }); toast('Cours archivé.'); load(container); }
    catch (ex) { toast(ex.message, 'danger'); }
}

async function removeCourse(container, id) {
    if (!await confirmDialog('Supprimer définitivement ce cours (et ses inscriptions/notes) ?', 'Supprimer')) return;
    try { await api.del(`courses/${id}`); toast('Cours supprimé.'); load(container); }
    catch (ex) { toast(ex.message, 'danger'); }
}
