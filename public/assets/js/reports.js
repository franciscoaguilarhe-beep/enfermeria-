document.addEventListener('DOMContentLoaded', () => {
    const app = document.querySelector('[data-reports-app]');
    if (!app) return;

    const apiUrl = app.dataset.apiUrl;
    const csrfToken = document.querySelector('meta[name="csrf-token"]').content;

    const form = document.getElementById('reports-filters');
    const fUnidad = document.getElementById('f-unidad');
    const fConsultorio = document.getElementById('f-consultorio');
    const fTurno = document.getElementById('f-turno');
    const fEnfermera = document.getElementById('f-enfermera');
    const btnExport = document.querySelector('[data-export-csv]');
    const tbody = document.querySelector('[data-report-rows]');

    const statPacientes = document.querySelector('[data-stat-pacientes]');
    const statAtenciones = document.querySelector('[data-stat-atenciones]');
    const statVitamina = document.querySelector('[data-stat-vitamina]');
    const statDesparasitacion = document.querySelector('[data-stat-desparasitacion]');
    const statVacunas = document.querySelector('[data-stat-vacunas]');

    let contextData = null;
    let chartTurnos, chartConsultorios, chartPreventivas, chartEnfermeras;

    function renderCharts(data) {
        if (!window.Chart) return;
        
        const sum = data.summary;
        
        const ctxTurnos = document.getElementById('chart-turnos');
        if (ctxTurnos) {
            if (chartTurnos) chartTurnos.destroy();
            chartTurnos = new Chart(ctxTurnos, {
                type: 'doughnut',
                data: {
                    labels: data.por_turno.map(t => t.nombre),
                    datasets: [{
                        data: data.por_turno.map(t => t.total),
                        backgroundColor: ['#0288d1', '#009688', '#fbc02d', '#d32f2f']
                    }]
                },
                options: { responsive: true, maintainAspectRatio: false }
            });
        }
        
        const ctxConsultorios = document.getElementById('chart-consultorios');
        if (ctxConsultorios) {
            if (chartConsultorios) chartConsultorios.destroy();
            chartConsultorios = new Chart(ctxConsultorios, {
                type: 'bar',
                data: {
                    labels: data.por_consultorio.map(c => c.nombre),
                    datasets: [{
                        label: 'Atenciones',
                        data: data.por_consultorio.map(c => c.total),
                        backgroundColor: '#009688'
                    }]
                },
                options: { responsive: true, maintainAspectRatio: false, scales: { y: { beginAtZero: true } } }
            });
        }

        const ctxPreventivas = document.getElementById('chart-preventivas');
        if (ctxPreventivas) {
            if (chartPreventivas) chartPreventivas.destroy();
            chartPreventivas = new Chart(ctxPreventivas, {
                type: 'bar',
                data: {
                    labels: ['Vitamina A', 'Desparasitacin', 'Cepillado', 'Sulfato', 'Envo Md.'],
                    datasets: [{
                        label: 'Aplicaciones',
                        data: [sum.vitamina_a || 0, sum.desparasitacion || 0, sum.cepillado_dental || 0, sum.sulfato_ferroso || 0, sum.envio_medico_familiar || 0],
                        backgroundColor: '#fbc02d'
                    }]
                },
                options: { responsive: true, maintainAspectRatio: false, scales: { y: { beginAtZero: true } } }
            });
        }

        const ctxEnfermeras = document.getElementById('chart-enfermeras');
        if (ctxEnfermeras && data.por_enfermera) {
            if (chartEnfermeras) chartEnfermeras.destroy();
            chartEnfermeras = new Chart(ctxEnfermeras, {
                type: 'bar',
                data: {
                    labels: data.por_enfermera.slice(0, 10).map(e => e.nombre),
                    datasets: [{
                        label: 'Atenciones',
                        data: data.por_enfermera.slice(0, 10).map(e => e.total),
                        backgroundColor: '#d32f2f'
                    }]
                },
                options: { indexAxis: 'y', responsive: true, maintainAspectRatio: false, scales: { x: { beginAtZero: true } } }
            });
        }
    }

    async function fetchContext() {
        try {
            const res = await fetch(`${apiUrl}?action=context`, {
                headers: { 'Accept': 'application/json' }
            });
            const data = await res.json();
            if (data.ok) {
                contextData = data;
                populateSelects();
                await loadData();
            }
        } catch (err) {
            console.error('Error fetching context:', err);
        }
    }

    function populateSelects() {
        if (!contextData) return;

        fUnidad.innerHTML = '<option value="">TODAS</option>';
        contextData.unidades.forEach(u => {
            const opt = document.createElement('option');
            opt.value = u.unidad_id;
            opt.textContent = u.clave || u.nombre;
            fUnidad.appendChild(opt);
        });

        if (contextData.rol !== 'ADMINISTRADOR' && contextData.rol !== 'SUPERVISOR' && contextData.mi_unidad) {
            fUnidad.value = contextData.mi_unidad;
            fUnidad.disabled = true;
        }

        fTurno.innerHTML = '<option value="">TODOS</option>';
        contextData.turnos.forEach(t => {
            const opt = document.createElement('option');
            opt.value = t.turno_id;
            opt.textContent = t.nombre;
            fTurno.appendChild(opt);
        });

        if (fEnfermera && contextData.enfermeras) {
            fEnfermera.innerHTML = '<option value="">TODOS</option>';
            contextData.enfermeras.forEach(e => {
                const opt = document.createElement('option');
                opt.value = e.usuario_id;
                opt.textContent = e.nombre;
                fEnfermera.appendChild(opt);
            });
        }
        
        updateConsultorios();
    }

    function updateConsultorios() {
        if (!contextData) return;
        const unidadId = parseInt(fUnidad.value, 10);
        fConsultorio.innerHTML = '<option value="">TODOS</option>';
        
        const filtered = isNaN(unidadId) || unidadId === 0 
            ? contextData.consultorios 
            : contextData.consultorios.filter(c => parseInt(c.unidad_id, 10) === unidadId);
            
        filtered.forEach(c => {
            const opt = document.createElement('option');
            opt.value = c.consultorio_id;
            opt.textContent = c.nombre_descriptivo;
            fConsultorio.appendChild(opt);
        });
    }

    fUnidad.addEventListener('change', () => {
        updateConsultorios();
    });

    async function loadData() {
        const formData = new FormData(form);
        const params = new URLSearchParams(formData);
        
        try {
            tbody.innerHTML = '<tr><td colspan="7" style="text-align:center; padding: 24px;">Cargando...</td></tr>';
            
            const [summaryRes, detailRes] = await Promise.all([
                fetch(`${apiUrl}?action=summary&${params.toString()}`, { headers: { 'Accept': 'application/json' } }),
                fetch(`${apiUrl}?action=detail&${params.toString()}`, { headers: { 'Accept': 'application/json' } })
            ]);

            const summaryData = await summaryRes.json();
            const detailData = await detailRes.json();

            if (summaryData.ok) {
                const sum = summaryData.summary;
                statPacientes.textContent = sum.pacientes_unicos || 0;
                statAtenciones.textContent = sum.atenciones || 0;
                statVitamina.textContent = sum.vitamina_a || 0;
                statDesparasitacion.textContent = sum.desparasitacion || 0;
                if (statVacunas) statVacunas.textContent = sum.vacunas_aplicadas || 0;
                renderCharts(summaryData);
            }

            if (detailData.ok) {
                if (detailData.rows.length === 0) {
                    tbody.innerHTML = '<tr><td colspan="7" style="text-align:center; padding: 24px;">No se encontraron registros.</td></tr>';
                } else {
                    tbody.innerHTML = '';
                    detailData.rows.forEach(row => {
                        const tr = document.createElement('tr');
                        const hFecha = document.createElement('td');
                        hFecha.innerHTML = `${row.fecha}<br><small style="color:#666">${row.hora}</small>`;
                        
                        const hNss = document.createElement('td');
                        hNss.textContent = row.identidad;
                        
                        const hPac = document.createElement('td');
                        hPac.textContent = row.paciente_nombre;
                        
                        const hUC = document.createElement('td');
                        hUC.innerHTML = `${row.unidad_nombre}<br><small style="color:#666">${row.consultorio_nombre}</small>`;
                        
                        const hTurno = document.createElement('td');
                        hTurno.textContent = row.turno_nombre;
                        
                        const hEnf = document.createElement('td');
                        hEnf.textContent = row.enfermera_nombre;
                        
                        const hAcc = document.createElement('td');
                        const a = document.createElement('a');
                        a.href = `index.php?r=patient&ref=${row.public_id}`; // Or similar, wait - there's no specific route instructed. Let's just output text or a placeholder.
                        a.textContent = 'Ver detalle';
                        a.className = 'button secondary';
                        a.style.padding = '4px 8px';
                        a.style.fontSize = '12px';
                        hAcc.appendChild(a);

                        tr.appendChild(hFecha);
                        tr.appendChild(hNss);
                        tr.appendChild(hPac);
                        tr.appendChild(hUC);
                        tr.appendChild(hTurno);
                        tr.appendChild(hEnf);
                        tr.appendChild(hAcc);
                        tbody.appendChild(tr);
                    });
                }
            }
        } catch (err) {
            console.error('Error loading data:', err);
            tbody.innerHTML = '<tr><td colspan="7" style="text-align:center; padding: 24px; color:red;">Error al cargar datos.</td></tr>';
        }
    }

    form.addEventListener('submit', (e) => {
        e.preventDefault();
        loadData();
    });

    if (btnExport) {
        btnExport.addEventListener('click', () => {
            const formData = new FormData(form);
            const params = new URLSearchParams(formData);
            window.location.href = `${apiUrl}?action=export_csv&${params.toString()}`;
        });
    }

    fetchContext();
});


