/**
 * Page "Mes cours" (étudiant) — liste des inscriptions avec moyenne par cours
 * et possibilité de se désinscrire (si autorisé par les règles métier).
 */
import { api } from '../api.js';
import { esc, pageHeader, confirmDialog, toast, spinner } from '../ui.js';

export async function render(container) {
    container.innerHTML = pageHeader('Mes cours',
        `<a class="btn btn-primary" href="#/courses"><i class="bi bi-plus-lg me-1"></i>S'inscrire à un cours</a>`)
        + `<div id="my-courses">${spinner()}</div>`;
    load(container);
}

async function load(container) {
    const { data } = await api.get('enrollments/mine');
    const target = document.getElementById('my-courses');
    if (!data.length) {
        target.innerHTML = `<div class="alert alert-info">Vous n'êtes inscrit à aucun cours.
            <a href="#/courses">Parcourir le catalogue</a>.</div>`;
        return;
    }
    target.innerHTML = `<div class="card shadow-sm"><div class="table-responsive"><table class="table table-hover align-middle mb-0">
        <thead class="table-light"><tr><th>Code</th><th>Intitulé</th><th>Enseignant</th><th>Semestre</th>
            <th class="text-center">ECTS</th><th class="text-center">Moyenne</th><th class="text-end">Actions</th></tr></thead>
        <tbody>${data.map(c => `<tr>
            <td><strong>${esc(c.code)}</strong></td>
            <td><a href="#/courses/${c.course_id}">${esc(c.name)}</a></td>
            <td class="small">${esc(c.teacher_name || '—')}</td>
            <td>${esc(c.semester)}</td>
            <td class="text-center">${esc(c.credits)}</td>
            <td class="text-center">${c.average!==null?`<span class="badge text-bg-primary">${c.average}/20</span>`:'<span class="text-muted">—</span>'}</td>
            <td class="text-end"><button class="btn btn-sm btn-outline-danger" data-drop="${c.enrollment_id}" data-name="${esc(c.name)}">
                <i class="bi bi-box-arrow-left me-1"></i>Se désinscrire</button></td></tr>`).join('')}
        </tbody></table></div></div>`;

    target.querySelectorAll('[data-drop]').forEach(b => b.addEventListener('click', async () => {
        if (!await confirmDialog(`Se désinscrire de « ${b.dataset.name} » ?`, 'Désinscription')) return;
        try { await api.del(`enrollments/${b.dataset.drop}`); toast('Désinscription effectuée.'); load(container); }
        catch (ex) { toast(ex.message, 'warning'); }
    }));
}
