/**
 * Page Tableau de bord — contenu différent selon le rôle (étudiant/enseignant/admin).
 */
import { api } from '../api.js';
import { store } from '../store.js';
import { esc, pageHeader, dayName, hhmm } from '../ui.js';

function statCard(color, icon, value, label) {
    return `<div class="col-sm-6 col-xl-3">
        <div class="card stat-card shadow-sm h-100"><div class="card-body d-flex align-items-center gap-3">
            <div class="stat-icon text-bg-${color}"><i class="bi bi-${icon}"></i></div>
            <div><div class="stat-value">${esc(value)}</div><div class="text-muted small">${esc(label)}</div></div>
        </div></div></div>`;
}

export async function render(container) {
    const { data } = await api.get('dashboard');
    const hello = `Bonjour ${esc(store.user.first_name)} 👋`;

    if (data.role === 'student')      return renderStudent(container, data, hello);
    if (data.role === 'teacher')      return renderTeacher(container, data, hello);
    return renderAdmin(container, data, hello);
}

function renderStudent(container, d, hello) {
    const avg = d.general_average !== null ? `${d.general_average}/20` : '—';
    container.innerHTML = pageHeader(hello) + `
        <div class="row g-3 mb-4">
            ${statCard('primary','collection', d.course_count, 'Cours suivis')}
            ${statCard('success','trophy', avg, 'Moyenne générale')}
            ${statCard('info','award', d.total_credits, 'Crédits ECTS suivis')}
            ${statCard('warning','bell', d.unread_notifs, 'Notifications non lues')}
        </div>
        <div class="row g-3">
            <div class="col-lg-6"><div class="card shadow-sm h-100"><div class="card-header bg-white fw-semibold">
                <i class="bi bi-calendar-event me-2"></i>Prochaines séances</div>
                <ul class="list-group list-group-flush">
                ${d.upcoming.length ? d.upcoming.map(s => `<li class="list-group-item d-flex justify-content-between">
                    <span><strong>${esc(s.code)}</strong> ${esc(s.name)}</span>
                    <span class="text-muted small">${dayName(s.day_of_week)} ${hhmm(s.start_time)} · ${esc(s.room)}</span></li>`).join('')
                    : '<li class="list-group-item text-muted">Aucune séance planifiée.</li>'}
                </ul></div></div>
            <div class="col-lg-6"><div class="card shadow-sm h-100"><div class="card-header bg-white fw-semibold">
                <i class="bi bi-card-checklist me-2"></i>Notes récentes</div>
                <ul class="list-group list-group-flush">
                ${d.recent_grades.length ? d.recent_grades.map(g => `<li class="list-group-item d-flex justify-content-between">
                    <span><strong>${esc(g.code)}</strong> · ${esc(g.eval_type)}</span>
                    <span class="badge text-bg-primary">${esc(g.value)}/20</span></li>`).join('')
                    : '<li class="list-group-item text-muted">Aucune note pour le moment.</li>'}
                </ul></div></div>
        </div>`;
}

function renderTeacher(container, d, hello) {
    container.innerHTML = pageHeader(hello) + `
        <div class="row g-3 mb-4">
            ${statCard('primary','journal-bookmark', d.course_count, 'Cours enseignés')}
            ${statCard('success','people', d.total_students, 'Étudiants inscrits')}
        </div>
        <div class="row g-3">
            <div class="col-lg-7"><div class="card shadow-sm h-100"><div class="card-header bg-white fw-semibold">
                <i class="bi bi-journal-bookmark me-2"></i>Mes cours</div>
                <div class="table-responsive"><table class="table mb-0">
                <thead><tr><th>Code</th><th>Intitulé</th><th class="text-center">Étudiants</th><th class="text-center">Notes</th><th></th></tr></thead>
                <tbody>${d.courses.map(c => `<tr>
                    <td><strong>${esc(c.code)}</strong></td><td>${esc(c.name)}</td>
                    <td class="text-center">${c.students}</td><td class="text-center">${c.grades_count}</td>
                    <td><a class="btn btn-sm btn-outline-primary" href="#/courses/${c.id}">Gérer</a></td></tr>`).join('')}
                </tbody></table></div></div></div>
            <div class="col-lg-5"><div class="card shadow-sm h-100"><div class="card-header bg-white fw-semibold">
                <i class="bi bi-calendar-event me-2"></i>Prochaines séances</div>
                <ul class="list-group list-group-flush">
                ${d.upcoming.length ? d.upcoming.map(s => `<li class="list-group-item d-flex justify-content-between">
                    <span><strong>${esc(s.code)}</strong></span>
                    <span class="text-muted small">${dayName(s.day_of_week)} ${hhmm(s.start_time)} · ${esc(s.room)}</span></li>`).join('')
                    : '<li class="list-group-item text-muted">Aucune séance.</li>'}
                </ul></div></div>
        </div>`;
}

function renderAdmin(container, d, hello) {
    container.innerHTML = pageHeader(hello) + `
        <div class="row g-3 mb-4">
            ${statCard('primary','mortarboard', d.students, 'Étudiants')}
            ${statCard('info','person-video3', d.teachers, 'Enseignants')}
            ${statCard('success','journal-bookmark', d.courses, 'Cours actifs')}
            ${statCard('warning','pencil-square', d.enrollments, 'Inscriptions actives')}
        </div>
        <div class="row g-3">
            <div class="col-lg-6"><div class="card shadow-sm"><div class="card-header bg-white fw-semibold">
                <i class="bi bi-clock-history me-2"></i>Derniers comptes créés</div>
                <ul class="list-group list-group-flush">
                ${d.recent_users.map(u => `<li class="list-group-item d-flex justify-content-between">
                    <span>${esc(u.name)}</span><span class="badge text-bg-secondary">${esc(u.role)}</span></li>`).join('')}
                </ul></div></div>
            <div class="col-lg-6"><div class="card shadow-sm"><div class="card-body">
                <h5 class="card-title"><i class="bi bi-bar-chart-line me-2"></i>Accès rapides</h5>
                <div class="d-grid gap-2">
                    <a class="btn btn-outline-primary" href="#/students"><i class="bi bi-mortarboard me-2"></i>Gérer les étudiants</a>
                    <a class="btn btn-outline-primary" href="#/courses"><i class="bi bi-journal-bookmark me-2"></i>Gérer les cours</a>
                    <a class="btn btn-outline-primary" href="#/stats"><i class="bi bi-graph-up me-2"></i>Voir les statistiques</a>
                </div></div></div></div>
        </div>`;
}
