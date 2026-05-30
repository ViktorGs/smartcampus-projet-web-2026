/**
 * Page "Mes notes" (étudiant) — notes regroupées par cours, moyenne et résultat.
 * Bouton de téléchargement du relevé PDF.
 */
import { api } from '../api.js';
import { store } from '../store.js';
import { esc, pageHeader, spinner } from '../ui.js';

export async function render(container) {
    container.innerHTML = pageHeader('Mes notes',
        `<a class="btn btn-outline-danger" href="api/transcript/${store.id}" target="_blank">
            <i class="bi bi-file-earmark-pdf me-1"></i>Télécharger mon relevé PDF</a>`)
        + `<div id="grades">${spinner()}</div>`;

    const { data } = await api.get('grades/mine');
    const target = document.getElementById('grades');
    if (!data.length) { target.innerHTML = `<div class="alert alert-info">Aucune note publiée pour l'instant.</div>`; return; }

    target.innerHTML = data.map(c => `<div class="card shadow-sm mb-3">
        <div class="card-header bg-white d-flex justify-content-between align-items-center">
            <span class="fw-semibold">${esc(c.code)} — ${esc(c.name)}</span>
            <span>${c.average!==null?`Moyenne : <strong>${c.average}/20</strong>
                <span class="badge ${c.result==='Admis'?'badge-result-admis':'badge-result-ajourne'} ms-2">${esc(c.result)}</span>`:''}</span>
        </div>
        <div class="table-responsive"><table class="table mb-0">
            <thead class="table-light"><tr><th>Évaluation</th><th class="text-center">Note</th><th class="text-center">Coefficient</th><th>Statut</th></tr></thead>
            <tbody>${c.grades.map(g => `<tr>
                <td>${esc(g.eval_type)}</td>
                <td class="text-center fw-semibold">${esc(g.value)}/20</td>
                <td class="text-center">${esc(g.coefficient)}</td>
                <td>${Number(g.is_locked)===1?'<span class="badge text-bg-success"><i class="bi bi-lock-fill me-1"></i>Validée</span>':'<span class="badge text-bg-secondary">Provisoire</span>'}</td>
            </tr>`).join('')}</tbody>
        </table></div></div>`).join('');
}
