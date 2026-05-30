/**
 * Page Emploi du temps — vue hebdomadaire (Lundi → Vendredi).
 *  - étudiant : ses cours ; enseignant : ses cours ; admin : tout + ajout/suppression.
 * La détection des conflits (salle/enseignant) est faite côté serveur à l'ajout.
 */
import { api } from '../api.js';
import { store } from '../store.js';
import { esc, pageHeader, openModal, confirmDialog, toast, dayName, hhmm, spinner } from '../ui.js';

const DAYS = [1,2,3,4,5];
const COLORS = ['#0d6efd','#198754','#6f42c1','#fd7e14','#d63384','#20c997','#dc3545','#0dcaf0'];
const colorFor = (id) => COLORS[id % COLORS.length];

export async function render(container) {
    const isAdmin = store.role === 'admin';
    container.innerHTML = pageHeader('Emploi du temps',
        isAdmin ? `<button class="btn btn-primary" id="add-slot"><i class="bi bi-plus-lg me-1"></i>Ajouter un créneau</button>` : '')
        + `<div id="timetable">${spinner()}</div>`;
    if (isAdmin) document.getElementById('add-slot').addEventListener('click', () => openSlotForm(container));
    load(container);
}

async function load(container) {
    const { data } = await api.get('schedule');
    const target = document.getElementById('timetable');
    const isAdmin = store.role === 'admin';

    const byDay = {}; DAYS.forEach(d => byDay[d] = []);
    data.forEach(s => { if (byDay[s.day_of_week]) byDay[s.day_of_week].push(s); });
    DAYS.forEach(d => byDay[d].sort((a,b) => a.start_time.localeCompare(b.start_time)));

    target.innerHTML = `<div class="row g-2">${DAYS.map(d => `
        <div class="col"><div class="card shadow-sm h-100">
            <div class="card-header text-center fw-semibold bg-dark text-white py-2">${dayName(d)}</div>
            <div class="card-body p-2 d-flex flex-column gap-2">
            ${byDay[d].length ? byDay[d].map(s => slotBlock(s, isAdmin)).join('')
                : '<div class="text-muted small text-center py-3">—</div>'}
            </div></div></div>`).join('')}</div>`;

    if (isAdmin) target.querySelectorAll('[data-del-slot]').forEach(b => b.addEventListener('click', () => removeSlot(container, b.dataset.delSlot)));
}

function slotBlock(s, isAdmin) {
    return `<div class="tt-event position-relative" style="position:relative;background:${colorFor(s.course_id)}">
        <div class="fw-semibold">${esc(s.code)}</div>
        <div>${hhmm(s.start_time)}–${hhmm(s.end_time)}</div>
        <div><i class="bi bi-geo-alt"></i> ${esc(s.room)}</div>
        ${s.teacher_name ? `<div class="text-truncate"><i class="bi bi-person"></i> ${esc(s.teacher_name)}</div>` : ''}
        ${isAdmin ? `<button class="btn btn-sm btn-light position-absolute top-0 end-0 m-1 p-0 px-1" data-del-slot="${s.id}" title="Supprimer">
            <i class="bi bi-x text-danger"></i></button>` : ''}
    </div>`;
}

async function openSlotForm(container) {
    const { data: courses } = await api.get('courses');
    const body = `<form id="slot-form"><div class="row g-2">
        <div class="col-12"><label class="form-label">Cours *</label><select name="course_id" class="form-select">
            ${courses.map(c => `<option value="${c.id}">${esc(c.code)} — ${esc(c.name)} (${esc(c.teacher_name||'—')})</option>`).join('')}</select></div>
        <div class="col-6"><label class="form-label">Jour *</label><select name="day_of_week" class="form-select">
            ${DAYS.map(d => `<option value="${d}">${dayName(d)}</option>`).join('')}</select></div>
        <div class="col-6"><label class="form-label">Salle *</label><input name="room" class="form-control" placeholder="B201"></div>
        <div class="col-6"><label class="form-label">Début *</label><input name="start_time" type="time" class="form-control" value="08:00"></div>
        <div class="col-6"><label class="form-label">Fin *</label><input name="end_time" type="time" class="form-control" value="10:00"></div>
    </div><p class="form-text">Un conflit de salle ou d'enseignant sera refusé automatiquement.</p></form>`;
    const footer = `<button class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button><button class="btn btn-primary" id="save-slot">Ajouter</button>`;
    const { modal, modalEl } = openModal({ title: 'Ajouter un créneau', bodyHtml: body, footerHtml: footer });

    modalEl.querySelector('#save-slot').addEventListener('click', async () => {
        const p = Object.fromEntries(new FormData(modalEl.querySelector('#slot-form')).entries());
        try { await api.post('schedule', p); modal.hide(); toast('Créneau ajouté.'); load(container); }
        catch (ex) { toast(ex.message, 'warning'); }
    });
}

async function removeSlot(container, id) {
    if (!await confirmDialog('Supprimer ce créneau ?', 'Supprimer')) return;
    try { await api.del(`schedule/${id}`); toast('Créneau supprimé.'); load(container); }
    catch (ex) { toast(ex.message, 'danger'); }
}
