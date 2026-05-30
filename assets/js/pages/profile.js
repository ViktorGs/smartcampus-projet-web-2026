/**
 * Page "Mon profil" — affichage des informations de l'utilisateur connecté
 * (commun aux trois rôles), avec les données de profil spécifiques.
 */
import { api } from '../api.js';
import { esc, pageHeader } from '../ui.js';

const ROLE_LABEL = { admin: 'Administrateur', teacher: 'Enseignant', student: 'Étudiant' };

export async function render(container) {
    const { user } = await api.get('auth/me');
    const p = user.profile || {};

    const row = (label, value) => value ? `<div class="col-sm-6 mb-3">
        <div class="text-muted small">${esc(label)}</div><div class="fw-semibold">${esc(value)}</div></div>` : '';

    let specific = '';
    if (user.role === 'student') {
        specific = row('N° étudiant', p.student_number) + row('Filière', p.filiere) + row('Niveau', p.niveau)
            + row('Groupe TD', p.group_td) + row('Date de naissance', (p.date_naissance||'').slice(0,10))
            + row('Adresse', p.address) + row('Date d\'inscription', (p.date_inscription||'').slice(0,10));
    } else if (user.role === 'teacher') {
        specific = row('N° employé', p.employee_number) + row('Département', p.department)
            + row('Grade', p.grade) + row('Bureau', p.office);
    }

    container.innerHTML = pageHeader('Mon profil') + `
        <div class="card shadow-sm"><div class="card-body">
            <div class="d-flex align-items-center mb-4">
                <i class="bi bi-person-circle text-primary" style="font-size:3.5rem"></i>
                <div class="ms-3"><h4 class="mb-0">${esc(user.first_name)} ${esc(user.last_name)}</h4>
                    <span class="badge text-bg-primary">${ROLE_LABEL[user.role]||user.role}</span></div>
            </div>
            <div class="row">
                ${row('Email', user.email)}${row('Téléphone', user.phone)}${row('Sexe', user.gender)}
                ${specific}
            </div>
        </div></div>`;
}
