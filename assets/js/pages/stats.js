/**
 * Page Statistiques (admin, bonus) — graphiques Chart.js à partir des données
 * agrégées renvoyées par l'API (/stats).
 */
import { api } from '../api.js';
import { esc, pageHeader } from '../ui.js';

let charts = [];

export async function render(container) {
    // Détruit les graphiques précédents (évite les fuites en re-navigation)
    charts.forEach(c => c.destroy()); charts = [];

    const { data } = await api.get('stats');
    container.innerHTML = pageHeader('Statistiques académiques') + `
        <div class="row g-3 mb-3">
            <div class="col-md-4"><div class="card stat-card shadow-sm"><div class="card-body text-center">
                <div class="stat-value text-success">${data.pass_rate!==null?data.pass_rate+'%':'—'}</div>
                <div class="text-muted small">Taux de réussite (moyenne ≥ 10)</div></div></div></div>
            <div class="col-md-4"><div class="card stat-card shadow-sm"><div class="card-body text-center">
                <div class="stat-value text-primary">${data.evaluated_results}</div>
                <div class="text-muted small">Résultats évalués</div></div></div></div>
            <div class="col-md-4"><div class="card stat-card shadow-sm"><div class="card-body text-center">
                <div class="stat-value text-info">${data.students_by_filiere.reduce((a,b)=>a+Number(b.value),0)}</div>
                <div class="text-muted small">Étudiants au total</div></div></div></div>
        </div>
        <div class="row g-3">
            <div class="col-lg-6"><div class="card shadow-sm"><div class="card-body">
                <h6 class="card-title">Étudiants par filière</h6><canvas id="c-filiere"></canvas></div></div></div>
            <div class="col-lg-6"><div class="card shadow-sm"><div class="card-body">
                <h6 class="card-title">Étudiants par niveau</h6><canvas id="c-niveau"></canvas></div></div></div>
            <div class="col-lg-6"><div class="card shadow-sm"><div class="card-body">
                <h6 class="card-title">Inscriptions par cours</h6><canvas id="c-course"></canvas></div></div></div>
            <div class="col-lg-6"><div class="card shadow-sm"><div class="card-body">
                <h6 class="card-title">Cours par département</h6><canvas id="c-dept"></canvas></div></div></div>
        </div>`;

    const palette = ['#0d6efd','#198754','#6f42c1','#fd7e14','#d63384','#20c997','#dc3545','#0dcaf0','#ffc107'];
    const labels = (arr) => arr.map(x => x.label);
    const values = (arr) => arr.map(x => Number(x.value));

    charts.push(new Chart(document.getElementById('c-filiere'), {
        type: 'doughnut',
        data: { labels: labels(data.students_by_filiere), datasets: [{ data: values(data.students_by_filiere), backgroundColor: palette }] },
        options: { plugins: { legend: { position: 'bottom' } } },
    }));
    charts.push(new Chart(document.getElementById('c-niveau'), {
        type: 'bar',
        data: { labels: labels(data.students_by_niveau), datasets: [{ label: 'Étudiants', data: values(data.students_by_niveau), backgroundColor: '#0d6efd' }] },
        options: { plugins: { legend: { display: false } }, scales: { y: { beginAtZero: true, ticks: { precision: 0 } } } },
    }));
    charts.push(new Chart(document.getElementById('c-course'), {
        type: 'bar',
        data: { labels: labels(data.enrollments_by_course), datasets: [{ label: 'Inscriptions', data: values(data.enrollments_by_course), backgroundColor: '#198754' }] },
        options: { indexAxis: 'y', plugins: { legend: { display: false } }, scales: { x: { beginAtZero: true, ticks: { precision: 0 } } } },
    }));
    charts.push(new Chart(document.getElementById('c-dept'), {
        type: 'pie',
        data: { labels: labels(data.courses_by_department), datasets: [{ data: values(data.courses_by_department), backgroundColor: palette }] },
        options: { plugins: { legend: { position: 'bottom' } } },
    }));
}
