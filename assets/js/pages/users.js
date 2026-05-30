/**
 * Page Utilisateurs (admin) — vue transversale + modération (activer/désactiver,
 * supprimer un compte abusif).
 */
import { api } from '../api.js';
import { store } from '../store.js';
import { esc, pageHeader, confirmDialog, toast, spinner } from '../ui.js';

let filter = { role: '', q: '' };

export async function render(container) {
    container.innerHTML = pageHeader('Gestion des utilisateurs')
        + `<div class="card shadow-sm mb-3"><div class="card-body"><div class="row g-2">
            <div class="col-md-8"><input class="form-control" id="u-q" placeholder="Rechercher (nom, email)…"></div>
            <div class="col-md-4"><select class="form-select" id="u-role">
                <option value="">Tous les rôles</option><option value="admin">Administrateurs</option>
                <option value="teacher">Enseignants</option><option value="student">Étudiants</option></select></div>
        </div></div></div><div id="users-table">${spinner()}</div>`;

    let timer;
    container.querySelector('#u-q').addEventListener('input', e => { clearTimeout(timer); timer = setTimeout(() => { filter.q = e.target.value; load(); }, 300); });
    container.querySelector('#u-role').addEventListener('change', e => { filter.role = e.target.value; load(); });
    load();
}

async function load() {
    const params = new URLSearchParams(filter).toString();
    const { data } = await api.get(`users?${params}`);
    const target = document.getElementById('users-table');
    const badge = { admin: 'text-bg-danger', teacher: 'text-bg-info', student: 'text-bg-secondary' };

    target.innerHTML = `<div class="card shadow-sm"><div class="table-responsive"><table class="table table-hover align-middle mb-0">
        <thead class="table-light"><tr><th>Nom</th><th>Email</th><th>Rôle</th><th>Statut</th><th class="text-end">Actions</th></tr></thead>
        <tbody>${data.map(u => `<tr>
            <td class="fw-semibold">${esc(u.last_name)} ${esc(u.first_name)}</td>
            <td class="small text-muted">${esc(u.email)}</td>
            <td><span class="badge ${badge[u.role]||'text-bg-secondary'}">${esc(u.role)}</span></td>
            <td>${Number(u.is_active)===1?'<span class="badge text-bg-success">Actif</span>':'<span class="badge text-bg-warning">Désactivé</span>'}</td>
            <td class="text-end table-actions">
                ${u.id===store.id ? '<span class="text-muted small">(vous)</span>' : `
                <button class="btn btn-sm ${Number(u.is_active)===1?'btn-outline-warning':'btn-outline-success'}" data-toggle="${u.id}" data-active="${Number(u.is_active)===1?0:1}">
                    <i class="bi bi-${Number(u.is_active)===1?'pause':'play'}-circle"></i> ${Number(u.is_active)===1?'Désactiver':'Activer'}</button>
                <button class="btn btn-sm btn-outline-danger" data-del="${u.id}" data-name="${esc(u.last_name)} ${esc(u.first_name)}"><i class="bi bi-trash"></i></button>`}
            </td></tr>`).join('')}
        </tbody></table></div></div>`;

    target.querySelectorAll('[data-toggle]').forEach(b => b.addEventListener('click', async () => {
        try { await api.patch(`users/${b.dataset.toggle}/status`, { is_active: Number(b.dataset.active) }); toast('Statut mis à jour.'); load(); }
        catch (ex) { toast(ex.message, 'danger'); }
    }));
    target.querySelectorAll('[data-del]').forEach(b => b.addEventListener('click', async () => {
        if (!await confirmDialog(`Supprimer le compte de ${b.dataset.name} ?`, 'Supprimer')) return;
        try { await api.del(`users/${b.dataset.del}`); toast('Compte supprimé.'); load(); }
        catch (ex) { toast(ex.message, 'danger'); }
    }));
}
