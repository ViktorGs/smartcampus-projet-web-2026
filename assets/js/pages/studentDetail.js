/**
 * Page profil étudiant : informations, parcours académique (cours suivis),
 * et accès au relevé de notes PDF.
 */
import { api } from '../api.js';
import { store } from '../store.js';
import { esc, pageHeader } from '../ui.js';

export async function render(container, params) {
    const id = params.id;
    const { data: s } = await api.get(`students/${id}`);

    const info = (label, value) => `<div class="col-sm-6 mb-2">
        <div class="text-muted small">${esc(label)}</div><div class="fw-semibold">${esc(value || '—')}</div></div>`;

    container.innerHTML = pageHeader(`${esc(s.first_name)} ${esc(s.last_name)}`,
        `<a class="btn btn-outline-danger" href="api/transcript/${id}" target="_blank"><i class="bi bi-file-earmark-pdf me-1"></i>Relevé PDF</a>
         <a class="btn btn-outline-secondary" href="#/students"><i class="bi bi-arrow-left me-1"></i>Retour</a>`)
        + `<div class="row g-3">
        <div class="col-lg-5"><div class="card shadow-sm"><div class="card-header bg-white fw-semibold">
            <i class="bi bi-person-vcard me-2"></i>Informations</div><div class="card-body"><div class="row">
            ${info('N° étudiant', s.student_number)}
            ${info('Email', s.email)}
            ${info('Filière', s.filiere)}
            ${info('Niveau', s.niveau)}
            ${info('Groupe TD', s.group_td)}
            ${info('Téléphone', s.phone)}
            ${info('Date de naissance', (s.date_naissance||'').slice(0,10))}
            ${info('Date d\'inscription', (s.date_inscription||'').slice(0,10))}
            ${info('Adresse', s.address)}
        </div></div></div></div>
        <div class="col-lg-7"><div class="card shadow-sm"><div class="card-header bg-white fw-semibold">
            <i class="bi bi-mortarboard me-2"></i>Parcours académique — cours suivis</div>
            <div class="table-responsive"><table class="table mb-0">
            <thead class="table-light"><tr><th>Code</th><th>Intitulé</th><th>Semestre</th><th class="text-center">ECTS</th></tr></thead>
            <tbody>${s.courses.length ? s.courses.map(c => `<tr>
                <td><strong>${esc(c.code)}</strong></td><td>${esc(c.name)}</td>
                <td>${esc(c.semester)}</td><td class="text-center">${esc(c.credits)}</td></tr>`).join('')
                : '<tr><td colspan="4" class="text-muted text-center py-3">Aucune inscription.</td></tr>'}
            </tbody></table></div></div></div>
    </div>`;
}
