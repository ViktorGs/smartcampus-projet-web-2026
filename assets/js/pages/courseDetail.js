/**
 * Page détail d'un cours.
 *  - Tous : informations, prérequis, créneaux.
 *  - Enseignant (responsable) / admin : grille de saisie des notes + validation
 *    finale (verrouillage), liste des inscrits, inscription d'un étudiant (admin).
 *  - Étudiant : bouton s'inscrire / se désinscrire.
 */
import { api } from '../api.js';
import { store } from '../store.js';
import { esc, pageHeader, openModal, confirmDialog, toast, dayName, hhmm, spinner } from '../ui.js';

const EVAL_TYPES = ['CC1','CC2','DS','Projet','Examen'];
let course = null;

export async function render(container, params) {
    const id = Number(params.id);
    course = (await api.get(`courses/${id}`)).data;

    const canManage = store.role === 'admin' || (store.role === 'teacher' && String(course.teacher_id) === String(store.id));

    container.innerHTML = pageHeader(`${esc(course.code)} — ${esc(course.name)}`,
        `<a class="btn btn-outline-secondary" href="#/courses"><i class="bi bi-arrow-left me-1"></i>Retour</a>`)
        + `<div class="row g-3 mb-3">
            <div class="col-lg-8"><div class="card shadow-sm h-100"><div class="card-body">
                <p>${esc(course.description || 'Pas de description.')}</p>
                <div class="row small">
                    <div class="col-sm-4 mb-2"><span class="text-muted">Enseignant</span><br><strong>${esc(course.teacher_name || 'Non assigné')}</strong></div>
                    <div class="col-sm-4 mb-2"><span class="text-muted">Niveau / Semestre</span><br><strong>${esc(course.niveau)} · ${esc(course.semester)}</strong></div>
                    <div class="col-sm-4 mb-2"><span class="text-muted">Crédits ECTS</span><br><strong>${esc(course.credits)}</strong></div>
                    <div class="col-sm-4 mb-2"><span class="text-muted">Département</span><br><strong>${esc(course.department)}</strong></div>
                    <div class="col-sm-4 mb-2"><span class="text-muted">Places</span><br><strong>${course.enrolled}/${course.capacity}</strong></div>
                </div>
                ${course.prerequisites.length ? `<div class="mt-2"><span class="text-muted small">Prérequis :</span>
                    ${course.prerequisites.map(p => `<span class="badge text-bg-light border">${esc(p.code)}</span>`).join(' ')}</div>` : ''}
            </div></div></div>
            <div class="col-lg-4"><div class="card shadow-sm h-100"><div class="card-header bg-white fw-semibold">
                <i class="bi bi-calendar3 me-2"></i>Créneaux</div>
                <ul class="list-group list-group-flush">
                ${course.slots.length ? course.slots.map(s => `<li class="list-group-item small d-flex justify-content-between">
                    <span>${dayName(s.day_of_week)} ${hhmm(s.start_time)}–${hhmm(s.end_time)}</span><span class="text-muted">${esc(s.room)}</span></li>`).join('')
                    : '<li class="list-group-item text-muted small">Aucun créneau défini.</li>'}
                </ul></div></div>
        </div>
        <div id="course-section">${spinner()}</div>`;

    if (canManage) renderGradeGrid(id);
    else if (store.role === 'student') renderStudentBox(id);
    else document.getElementById('course-section').innerHTML = '';
}

// ---- Vue gestion (enseignant / admin) : grille des notes -------------
async function renderGradeGrid(courseId) {
    const { data: students } = await api.get(`grades/course/${courseId}`);
    const section = document.getElementById('course-section');
    const anyLocked = students.some(s => s.grades.some(g => Number(g.is_locked) === 1));

    section.innerHTML = `
        <div class="d-flex justify-content-between align-items-center mb-2">
            <h4 class="mb-0"><i class="bi bi-card-checklist me-2"></i>Notes des étudiants inscrits</h4>
            <div class="d-flex gap-2">
                ${store.role === 'admin' ? `<button class="btn btn-outline-primary btn-sm" id="enroll-student"><i class="bi bi-person-plus me-1"></i>Inscrire un étudiant</button>` : ''}
                <button class="btn btn-success btn-sm" id="validate-grades" ${anyLocked?'disabled':''}>
                    <i class="bi bi-lock me-1"></i>${anyLocked ? 'Notes validées' : 'Valider les notes'}</button>
            </div>
        </div>
        ${students.length ? `<div class="card shadow-sm"><div class="table-responsive"><table class="table table-bordered align-middle mb-0 text-center">
            <thead class="table-light"><tr><th class="text-start">Étudiant</th>
                ${EVAL_TYPES.map(t => `<th>${t}</th>`).join('')}
                <th>Moyenne</th><th>Résultat</th></tr></thead>
            <tbody>${students.map(s => gradeRow(courseId, s)).join('')}</tbody>
        </table></div></div>
        <p class="small text-muted mt-2">Cliquez sur une cellule pour saisir/modifier une note. <i class="bi bi-lock-fill text-danger"></i> = note validée (verrouillée).</p>`
        : '<div class="alert alert-info">Aucun étudiant inscrit à ce cours.</div>'}`;

    section.querySelectorAll('[data-cell]').forEach(td => {
        td.addEventListener('click', () => {
            const { student, sname, type, gid, value, coef, locked } = td.dataset;
            if (Number(locked) === 1) { toast('Note validée : non modifiable.', 'warning'); return; }
            openGradeModal(courseId, { studentId: student, sname, type, gid, value, coef });
        });
    });
    document.getElementById('validate-grades')?.addEventListener('click', () => validateGrades(courseId));
    document.getElementById('enroll-student')?.addEventListener('click', () => enrollStudentModal(courseId));
}

function gradeRow(courseId, s) {
    const byType = {};
    s.grades.forEach(g => byType[g.eval_type] = g);
    const cells = EVAL_TYPES.map(type => {
        const g = byType[type];
        const locked = g && Number(g.is_locked) === 1;
        const content = g ? `${g.value}${locked ? ' <i class="bi bi-lock-fill text-danger small"></i>' : ''}` : '<span class="text-muted">+</span>';
        return `<td class="cursor-pointer ${locked?'locked-grade':''}" data-cell
            data-student="${s.id}" data-sname="${esc(s.last_name)} ${esc(s.first_name)}"
            data-type="${type}" data-gid="${g?g.id:''}" data-value="${g?g.value:''}"
            data-coef="${g?g.coefficient:'1'}" data-locked="${locked?1:0}">${content}</td>`;
    }).join('');
    const result = s.result ? `<span class="badge ${s.result==='Admis'?'badge-result-admis':'badge-result-ajourne'}">${s.result}</span>` : '—';
    return `<tr><td class="text-start fw-semibold">${esc(s.last_name)} ${esc(s.first_name)}<br><span class="small text-muted">${esc(s.student_number)}</span></td>
        ${cells}<td class="fw-bold">${s.average!==null?s.average:'—'}</td><td>${result}</td></tr>`;
}

function openGradeModal(courseId, { studentId, sname, type, gid, value, coef }) {
    const body = `<form id="grade-form">
        <p class="mb-2"><strong>${esc(sname)}</strong> — évaluation <span class="badge text-bg-primary">${esc(type)}</span></p>
        <div class="row g-2">
            <div class="col-6"><label class="form-label">Note / 20 *</label><input name="value" type="number" step="0.25" min="0" max="20" class="form-control" value="${esc(value)}"></div>
            <div class="col-6"><label class="form-label">Coefficient</label><input name="coefficient" type="number" step="0.5" min="0.5" max="10" class="form-control" value="${esc(coef||'1')}"></div>
            <div class="col-12"><label class="form-label">Commentaire</label><input name="comment" class="form-control"></div>
        </div></form>`;
    const footer = `${gid ? `<button class="btn btn-outline-danger me-auto" id="del-grade">Supprimer</button>` : ''}
        <button class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button><button class="btn btn-primary" id="save-grade">Enregistrer</button>`;
    const { modal, modalEl } = openModal({ title: gid ? 'Modifier la note' : 'Saisir une note', bodyHtml: body, footerHtml: footer });

    modalEl.querySelector('#save-grade').addEventListener('click', async () => {
        const form = modalEl.querySelector('#grade-form');
        const p = Object.fromEntries(new FormData(form).entries());
        try {
            if (gid) await api.put(`grades/${gid}`, p);
            else await api.post('grades', { student_id: Number(studentId), course_id: courseId, eval_type: type, ...p });
            modal.hide(); toast('Note enregistrée.'); renderGradeGrid(courseId);
        } catch (ex) { toast(ex.message, 'danger'); }
    });
    modalEl.querySelector('#del-grade')?.addEventListener('click', async () => {
        try { await api.del(`grades/${gid}`); modal.hide(); toast('Note supprimée.'); renderGradeGrid(courseId); }
        catch (ex) { toast(ex.message, 'danger'); }
    });
}

async function validateGrades(courseId) {
    if (!await confirmDialog('Valider définitivement toutes les notes de ce cours ? Elles ne pourront plus être modifiées.', 'Validation finale')) return;
    try { const r = await api.post(`grades/course/${courseId}/validate`, {}); toast(`Validé (${r.locked} notes verrouillées).`); renderGradeGrid(courseId); }
    catch (ex) { toast(ex.message, 'danger'); }
}

async function enrollStudentModal(courseId) {
    const { data: students } = await api.get('students');
    const options = students.map(s => `<option value="${s.id}">${esc(s.last_name)} ${esc(s.first_name)} (${esc(s.student_number)})</option>`).join('');
    const { modal, modalEl } = openModal({
        title: 'Inscrire un étudiant à ce cours',
        bodyHtml: `<form id="enr-form"><label class="form-label">Étudiant</label><select name="student_id" class="form-select">${options}</select>
            <p class="form-text">Les règles métier (capacité, prérequis, conflit horaire) seront vérifiées.</p></form>`,
        footerHtml: `<button class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button><button class="btn btn-primary" id="do-enr">Inscrire</button>`,
    });
    modalEl.querySelector('#do-enr').addEventListener('click', async () => {
        const sid = modalEl.querySelector('[name=student_id]').value;
        try { await api.post('enrollments', { course_id: courseId, student_id: Number(sid) }); modal.hide(); toast('Étudiant inscrit.'); renderGradeGrid(courseId); }
        catch (ex) { toast(ex.message, 'warning'); }
    });
}

// ---- Vue étudiant : s'inscrire / se désinscrire ----------------------
async function renderStudentBox(courseId) {
    const { data: mine } = await api.get('enrollments/mine');
    const enrollment = mine.find(e => Number(e.course_id) === courseId);
    const section = document.getElementById('course-section');
    const full = Number(course.enrolled) >= Number(course.capacity);

    if (enrollment) {
        section.innerHTML = `<div class="alert alert-success d-flex justify-content-between align-items-center">
            <span><i class="bi bi-check-circle me-2"></i>Vous êtes inscrit à ce cours${enrollment.average!==null?` — moyenne actuelle : <strong>${enrollment.average}/20</strong>`:''}.</span>
            <button class="btn btn-outline-danger btn-sm" id="unenroll">Se désinscrire</button></div>`;
        document.getElementById('unenroll').addEventListener('click', async () => {
            if (!await confirmDialog('Confirmer la désinscription de ce cours ?', 'Désinscription')) return;
            try { await api.del(`enrollments/${enrollment.enrollment_id}`); toast('Désinscription effectuée.'); render(document.getElementById('page-content'), { id: courseId }); }
            catch (ex) { toast(ex.message, 'warning'); }
        });
    } else {
        section.innerHTML = `<div class="alert alert-light border d-flex justify-content-between align-items-center">
            <span>Vous n'êtes pas inscrit à ce cours.</span>
            <button class="btn btn-success btn-sm" id="enroll" ${full?'disabled':''}>${full?'Complet':'S\'inscrire'}</button></div>`;
        document.getElementById('enroll')?.addEventListener('click', async () => {
            try { await api.post('enrollments', { course_id: courseId }); toast('Inscription enregistrée !'); render(document.getElementById('page-content'), { id: courseId }); }
            catch (ex) { toast(ex.message, 'warning'); }
        });
    }
}
