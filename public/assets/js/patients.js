(() => {
  const app = document.querySelector('[data-patients-app]');
  if (!app) return;
  const apiUrl = app.dataset.apiUrl;
  const csrf = document.querySelector('meta[name="csrf-token"]')?.content || '';
  const state = {
    context: null,
    patient: null,
    cardPatient: null,
    printRef: null,
    printPatientRef: null,
    printHasAttention: false,
    printHasProstate: false,
    lastFocus: null,
    activeModal: null,
    vaccines: [],
    vaccineEditMode: false,
    prostatePatient: null,
    prostateQuestions: [],
    prostateRecord: null,
    prostateAnswers: {},
    prostateOrigAnswers: {},
    prostateOrigFecha: '',
    prostateOrigGrupo: '45_74',
    prostateEditMode: false,
    prostateIsNewMode: false,
    colonPatient: null,
    mhPatient: null,
    mhQuestions: [],
    mhRecord: null,
    mhAnswers: {},
    mhOrigAnswers: {},
    mhOrigFecha: '',
    mhEditMode: false,
    mhIsNewMode: false,
    printHasMentalHealth: false
  };
  let hasUnsavedChanges = false;
  let hasProstateUnsavedChanges = false;
  let hasMhUnsavedChanges = false;

  const $ = (selector, parent = document) => parent.querySelector(selector);
  const $$ = (selector, parent = document) => [...parent.querySelectorAll(selector)];
  const escapeHtml = value => String(value ?? '').replace(/[&<>'"]/g, char => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', "'": '&#039;', '"': '&quot;' }[char]));
  const today = () => { const d = new Date(); return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`; };
  const cleanIsoDate = value => {
    const str = String(value ?? '').trim().slice(0, 10);
    if (!str || str === '0000-00-00' || str.startsWith('0000-')) return '';
    return /^\d{4}-\d{2}-\d{2}$/.test(str) ? str : '';
  };
  const formatDate = value => {
    const clean = cleanIsoDate(value);
    if (!clean) return '—';
    const [y, m, d] = clean.split('-');
    return `${d}/${m}/${y}`;
  };
  const formatDateTime = (date, time) => date ? `${date} ${String(time || '').slice(0, 5)}` : '—';
  const display = value => value === null || value === undefined || String(value).trim() === '' ? 'No especificado' : String(value);
  const patientLabel = p => display(p?.nombre_completo || [p?.apellido_paterno, p?.apellido_materno, p?.nombres].filter(Boolean).join(' '));

  // SVGs institucionales para acciones
  const icon = type => {
    if (type === 'add') {
      return '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#5c6bc0" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="5" x2="12" y2="19"></line><line x1="5" y1="12" x2="19" y2="12"></line></svg>';
    }
    if (type === 'edit') {
      return '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#d97706" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path></svg>';
    }
    if (type === 'vaccine') {
      return '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#2e7d32" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="5"></circle><line x1="12" y1="1" x2="12" y2="4"></line><line x1="12" y1="20" x2="12" y2="23"></line><line x1="4.22" y1="4.22" x2="6.34" y2="6.34"></line><line x1="17.66" y1="17.66" x2="19.78" y2="19.78"></line><line x1="1" y1="12" x2="4" y2="12"></line><line x1="20" y1="12" x2="23" y2="12"></line><line x1="4.22" y1="19.78" x2="6.34" y2="17.66"></line><line x1="17.66" y1="6.34" x2="19.78" y2="4.22"></line></svg>';
    }
    if (type === 'prostate') {
      return '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 5H7a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V7a2 2 0 0 0-2-2h-2"></path><rect x="9" y="3" width="6" height="4" rx="1"></rect><path d="M9 14l2 2 4-4"></path></svg>';
    }
    if (type === 'colon') {
      return '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path><polyline points="14 2 14 8 20 8"></polyline><circle cx="11" cy="14" r="3"></circle><line x1="13.2" y1="16.2" x2="16" y2="19"></line></svg>';
    }
    if (type === 'mental_health') {
      return '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.1" stroke-linecap="round" stroke-linejoin="round"><path d="M9.5 2A2.5 2.5 0 0 1 12 4.5v15a2.5 2.5 0 0 1-4.96.44 2.5 2.5 0 0 1-2.96-3.08 3 3 0 0 1-.34-5.58 2.5 2.5 0 0 1 1.32-4.24 2.5 2.5 0 0 1 4.44-2.04Z"></path><path d="M14.5 2A2.5 2.5 0 0 0 12 4.5v15a2.5 2.5 0 0 0 4.96.44 2.5 2.5 0 0 0 2.96-3.08 3 3 0 0 0 .34-5.58 2.5 2.5 0 0 0-1.32-4.24 2.5 2.5 0 0 0-4.44-2.04Z"></path></svg>';
    }
    return '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#455a64" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path><polyline points="14 2 14 8 20 8"></polyline><line x1="16" y1="13" x2="8" y2="13"></line><line x1="16" y1="17" x2="8" y2="17"></line><polyline points="10 9 9 9 8 9"></polyline></svg>';
  };

  async function get(params, signal) {
    const url = new URL(apiUrl, window.location.href);
    Object.entries(params).forEach(([key, value]) => {
      const cleanVal = String(value ?? '').trim();
      if (cleanVal !== '') {
        url.searchParams.set(key, cleanVal);
      } else {
        url.searchParams.delete(key);
      }
    });
    const response = await fetch(url, { headers: { Accept: 'application/json' }, credentials: 'same-origin', signal });
    const data = await response.json().catch(() => ({ ok: false, message: 'Respuesta inválida del servidor.' }));
    if (!response.ok || !data.ok) throw new Error(data.message || 'No fue posible consultar la información.');
    return data;
  }

  async function post(action, form) {
    const body = form instanceof FormData ? form : new FormData(form);
    body.set('action', action);
    body.set('_csrf', csrf);
    const response = await fetch(apiUrl, { method: 'POST', body, headers: { Accept: 'application/json' }, credentials: 'same-origin' });
    const data = await response.json().catch(() => ({ ok: false, message: 'Respuesta inválida del servidor.' }));
    if (!response.ok || !data.ok) {
      const error = new Error(data.message || 'No fue posible guardar.');
      error.data = data;
      throw error;
    }
    return data;
  }

  function toast(message) {
    const region = $('[data-toast-region]');
    if (!region) return;
    const item = document.createElement('div');
    item.className = 'toast';
    item.innerHTML = `<span>${escapeHtml(message)}</span>`;
    region.appendChild(item);
    setTimeout(() => { item.style.opacity = '0'; setTimeout(() => item.remove(), 200); }, 4500);
  }

  function setError(form, message = '') {
    const node = $('[data-form-error]', form);
    if (node) { node.textContent = message; node.hidden = !message; }
  }

  function modalElement(name) { return $(`[data-modal="${name}"]`); }

  function openModal(name, trigger) {
    const layer = modalElement(name);
    if (!layer) return;
    state.lastFocus = trigger || document.activeElement;
    state.activeModal = name;
    layer.hidden = false;
    document.body.style.overflow = 'hidden';
    const dialog = $('.modal-card', layer);
    if (dialog) dialog.focus();
  }

  function closeModal(layer = modalElement(state.activeModal), force = false) {
    if (!layer || layer.hidden) return;
    if (!force && layer === modalElement('vaccines') && hasUnsavedChanges) {
      showVaccineConfirmDialog(true);
      return;
    }
    if (!force && layer === modalElement('prostate') && hasProstateUnsavedChanges) {
      showProstateConfirmDialog(true);
      return;
    }
    if (!force && layer === modalElement('mental-health') && hasMhUnsavedChanges) {
      showMhConfirmDialog(true);
      return;
    }
    if (layer === modalElement('vaccines')) {
      hasUnsavedChanges = false;
      state.vaccineEditMode = false;
      showVaccineConfirmDialog(false);
      showVaccineFeedback('', '');
      updateVaccineEditButtonUI();
    }
    if (layer === modalElement('prostate')) {
      hasProstateUnsavedChanges = false;
      state.prostateEditMode = false;
      state.prostateIsNewMode = false;
      showProstateConfirmDialog(false);
      showProstateFeedback('', '');
    }
    if (layer === modalElement('mental-health')) {
      hasMhUnsavedChanges = false;
      state.mhEditMode = false;
      state.mhIsNewMode = false;
      showMhConfirmDialog(false);
      showMhFeedback('', '');
    }
    layer.hidden = true;
    const form = $('[data-modal-form]', layer);
    if (form) { form.reset(); setError(form); }
    state.activeModal = null;
    document.body.style.overflow = '';
    if (state.lastFocus?.focus) state.lastFocus.focus();
  }

  function fill(form, values) {
    Object.entries(values || {}).forEach(([key, value]) => {
      const field = form.elements.namedItem(key);
      if (!field) return;
      if (field.type === 'checkbox') field.checked = String(value) === '1';
      else field.value = value ?? '';
    });
  }

  function populatePatientAssignmentSelects(ctx) {
    if (!ctx) return;
    const consultorios = ctx.all_consultorios || ctx.consultorios || [];
    const turnos = ctx.all_turnos || ctx.turnos || [];

    $$('[data-patient-consultorio-select]').forEach(sel => {
      const currentVal = sel.value;
      sel.innerHTML = '<option value="">SIN ASIGNAR</option>';
      consultorios.forEach(c => {
        const opt = document.createElement('option');
        opt.value = String(c.consultorio_id);
        opt.textContent = c.nombre_descriptivo || c.clave || `Consultorio ${c.consultorio_id}`;
        sel.appendChild(opt);
      });
      if (currentVal) sel.value = currentVal;
    });

    $$('[data-patient-turno-select]').forEach(sel => {
      const currentVal = sel.value;
      sel.innerHTML = '<option value="">SIN ASIGNAR</option>';
      turnos.forEach(t => {
        const opt = document.createElement('option');
        opt.value = String(t.turno_id);
        opt.textContent = t.nombre || t.clave || `Turno ${t.turno_id}`;
        sel.appendChild(opt);
      });
      if (currentVal) sel.value = currentVal;
    });
  }

  async function loadContext() {
    if (state.context) return state.context;
    state.context = (await get({ action: 'context' })).context;
    populatePatientAssignmentSelects(state.context);
    return state.context;
  }

  // Acciones en cada fila de la tabla (6 iconos alineados: Atención, Vacunas, Próstata, Colon, Salud Mental, Imprimir)
  function rowActions(row) {
    const hasAttention = Boolean(row.atencion_public_id);
    const hasProstate = Boolean(row.has_prostata || row.prostata_id);
    const hasMentalHealth = Boolean(row.has_salud_mental || row.salud_mental_id);
    const attentionClass = hasAttention ? ' has-record' : '';
    const prostateClass = hasProstate ? ' has-record' : '';
    const mhClass = hasMentalHealth ? ' has-record' : '';
    const prostateTitle = hasProstate
      ? `Detección de Cáncer de Próstata (${row.prostata_valoracion || 'Registrado'} · ${row.prostata_puntaje ?? 0} pts)`
      : 'Detección de Cáncer de Próstata';
    const mhTitle = hasMentalHealth
      ? `Detección de Salud Mental (PHQ-4 + PHQ-TP) (${row.salud_mental_valoracion || 'Registrado'} · PHQ-4: ${row.salud_mental_puntaje ?? 0} pts)`
      : 'Detección de Salud Mental (PHQ-4 + PHQ-TP)';
    return `<div class="action-buttons">
      <button type="button" class="btn-action-icon plus${attentionClass}" title="Atención Integral de Enfermería" aria-label="Atención Integral de Enfermería" data-action="attention" data-ref="${row.paciente_public_id}">
        ${icon('add')}
      </button>
      <button type="button" class="btn-action-icon virus" title="Ingresar datos de vacunación" aria-label="Ingresar datos de vacunación" data-action="vaccine" data-ref="${row.paciente_public_id}">
        ${icon('vaccine')}
      </button>
      <button type="button" class="btn-action-icon prostate${prostateClass}" title="${escapeHtml(prostateTitle)}" aria-label="Detección de Cáncer de Próstata" data-action="prostate" data-ref="${row.paciente_public_id}">
        ${icon('prostate')}
      </button>
      <button type="button" class="btn-action-icon colon" title="Detección de Cáncer de Colon y Recto" aria-label="Detección de Cáncer de Colon y Recto" data-action="colon" data-ref="${row.paciente_public_id}">
        ${icon('colon')}
      </button>
      <button type="button" class="btn-action-icon mental-health${mhClass}" title="${escapeHtml(mhTitle)}" aria-label="Detección de Salud Mental (PHQ-4 + PHQ-TP)" data-action="mental-health" data-ref="${row.paciente_public_id}">
        ${icon('mental_health')}
      </button>
      <button type="button" class="btn-action-icon doc" title="Imprimir formatos de enfermería" aria-label="Imprimir formatos de enfermería" data-action="print" data-ref="${row.atencion_public_id || ''}" data-patient-ref="${row.paciente_public_id}" data-has-prostate="${hasProstate ? '1' : '0'}" data-has-mh="${hasMentalHealth ? '1' : '0'}">
        ${icon('doc')}
      </button>
    </div>`;
  }

  function renderRows(data) {
    const tbody = $('[data-attention-rows]');
    const rows = [...data.rows];
    const rowRefs = new Set(rows.map(row => row.paciente_public_id));

    (data.patient_matches || []).filter(patient => !rowRefs.has(patient.public_id)).forEach(patient => {
      rows.push({
        patient_only: true,
        paciente_public_id: patient.public_id,
        nombre_completo: patient.nombre_completo,
        identidad: patient.identidad,
        prostata_id: patient.prostata_id ?? null,
        prostata_puntaje: patient.prostata_puntaje ?? null,
        prostata_valoracion: patient.prostata_valoracion ?? null,
        has_prostata: Boolean(patient.has_prostata || patient.prostata_id),
        salud_mental_id: patient.salud_mental_id ?? null,
        salud_mental_puntaje: patient.salud_mental_puntaje ?? null,
        salud_mental_valoracion: patient.salud_mental_valoracion ?? null,
        has_salud_mental: Boolean(patient.has_salud_mental || patient.salud_mental_id)
      });
    });

    if (!rows.length) {
      tbody.innerHTML = '<tr><td colspan="6" style="text-align:center; padding: 32px; color: var(--srae-text-muted);">No se encontraron pacientes para los filtros seleccionados.</td></tr>';
      return 0;
    }

    tbody.innerHTML = rows.map(row => {
      const hasActivity = !row.patient_only && Boolean(row.fecha);
      const dateTime = !hasActivity ? '—' : escapeHtml(formatDateTime(row.fecha, row.hora));
      const consTurnoParts = [row.consultorio_nombre || row.consultorio_clave || '', row.turno_clave || ''].filter(Boolean);
      const consultShift = consTurnoParts.length ? escapeHtml(consTurnoParts.join(' - ')) : '—';
      const staffName = !hasActivity ? '—' : escapeHtml(row.enfermera_nombre || '—');
      return `<tr data-patient-ref="${row.paciente_public_id}" tabindex="0">
        <td class="col-date">${dateTime}</td>
        <td class="col-patient">${escapeHtml(row.nombre_completo)}</td>
        <td class="col-nss">${escapeHtml(row.identidad || '—')}</td>
        <td class="col-clinic">${consultShift}</td>
        <td class="col-staff">${staffName}</td>
        <td class="col-actions">${rowActions(row)}</td>
      </tr>`;
    }).join('');

    // Clic en fila para abrir Tarjeta de Paciente
    $$('tr[data-patient-ref]', tbody).forEach(row => {
      row.addEventListener('click', () => openCard(row.dataset.patientRef, row));
      row.addEventListener('keydown', event => {
        if (event.key === 'Enter' || event.key === ' ') {
          event.preventDefault();
          openCard(row.dataset.patientRef, row);
        }
      });
    });

    // Clic en botones de acción específicos (evita que se abra la tarjeta)
    $$('[data-action]', tbody).forEach(button => {
      button.addEventListener('click', event => {
        event.stopPropagation();
        const action = button.dataset.action;
        if (action === 'attention') openAttention(button.dataset.ref, button);
        if (action === 'vaccine') openVaccines(button.dataset.ref, button);
        if (action === 'prostate') openProstate(button.dataset.ref, button);
        if (action === 'colon') openColon(button.dataset.ref, button);
        if (action === 'mental-health') openMentalHealth(button.dataset.ref, button);
        if (action === 'print') openPrint(button.dataset.ref, button, button.dataset.patientRef, button.dataset.hasProstate === '1', button.dataset.hasMh === '1');
      });
    });

    return rows.length;
  }

  let filterDebounceTimer = null;
  let listAbortController = null;

  async function loadRows() {
    const dateInput = $('[data-filter-date]');
    const dateEndInput = $('[data-filter-date-end]');
    const queryInput = $('[data-filter-query]');

    const date = cleanIsoDate(dateInput?.value || '');
    const dateEnd = cleanIsoDate(dateEndInput?.value || '');
    const q = (queryInput?.value || '').trim();

    // Limpiar o actualizar parámetros en la URL actual sin recargar
    try {
      const currentUrl = new URL(window.location.href);
      if (date) currentUrl.searchParams.set('fecha', date);
      else currentUrl.searchParams.delete('fecha');
      if (dateEnd) currentUrl.searchParams.set('fecha_fin', dateEnd);
      else currentUrl.searchParams.delete('fecha_fin');
      if (q) currentUrl.searchParams.set('q', q);
      else currentUrl.searchParams.delete('q');
      window.history.replaceState({}, '', currentUrl.toString());
    } catch (_) { /* ignore history error */ }

    const status = $('[data-table-status]');
    if (status) status.textContent = 'Consultando...';

    if (listAbortController) {
      listAbortController.abort();
    }
    listAbortController = new AbortController();

    try {
      const params = { action: 'list' };
      if (date) params.fecha = date;
      if (dateEnd) params.fecha_fin = dateEnd;
      if (q) params.q = q;

      const data = await get(params, listAbortController.signal);
      const total = renderRows(data);
      if (status) status.textContent = `${total} registro(s) encontrado(s)`;
    } catch (error) {
      if (error?.name === 'AbortError') return;
      $('[data-attention-rows]').innerHTML = `<tr><td colspan="6" style="text-align:center; padding: 24px; color: #c62828;">${escapeHtml(error.message)}</td></tr>`;
      if (status) status.textContent = 'Error en consulta';
    }
  }

  // ==========================================================================
  // REGISTRO DE PACIENTE (Modal)
  // ==========================================================================
  function openRegister(trigger) {
    const form = $('#patient-form');
    form.reset();
    setError(form);
    form.elements.namedItem('public_id').value = '';
    form.elements.namedItem('pais_residencia').value = 'MÉXICO';
    openModal('patient', trigger);
  }

  async function savePatient(event) {
    event.preventDefault();
    const form = event.currentTarget;
    setError(form);
    const btn = $('button[type="submit"]', form);
    if (btn) btn.disabled = true;
    try {
      const data = await post('save_patient', form);
      closeModal(modalElement('patient'));
      await loadRows();
      toast(data.message || 'PACIENTE REGISTRADO CORRECTAMENTE.');
    } catch (error) {
      setError(form, error.message);
    } finally {
      if (btn) btn.disabled = false;
    }
  }

  // ==========================================================================
  // TARJETA DE PACIENTE & EDICIÓN IN-SITU (Modales 3 y 4 PDF)
  // ==========================================================================
  function cardHtml(p) {
    const tri = val => val === null || val === '' ? 'No especificado' : String(val) === '1' ? 'Sí' : 'No';
    const gen = p.sexo === 'F' ? 'Femenino' : p.sexo === 'M' ? 'Masculino' : 'No especificado';
    return `<div class="patient-card-data">
      <div class="modal-section-title" style="margin-top: 0;">DATO GENERALES:</div>
      <div class="card-data-row">
        <div class="card-data-item">
          <span class="card-data-label">NOMBRE:</span>
          <span class="card-data-value">${escapeHtml(patientLabel(p))}</span>
        </div>
        <div class="card-data-item">
          <span class="card-data-label">NSS:</span>
          <span class="card-data-value">${escapeHtml(p.identidad)}</span>
        </div>
        <div class="card-data-item">
          <span class="card-data-label">CURP:</span>
          <span class="card-data-value">${escapeHtml(display(p.curp))}</span>
        </div>
      </div>
      <div class="card-data-row">
        <div class="card-data-item">
          <span class="card-data-label">ESCOLARIDAD:</span>
          <span class="card-data-value">${escapeHtml(display(p.escolaridad))}</span>
        </div>
        <div class="card-data-item">
          <span class="card-data-label">OCUPACIÓN:</span>
          <span class="card-data-value">${escapeHtml(display(p.ocupacion))}</span>
        </div>
        <div class="card-data-item">
          <span class="card-data-label">ESTADO CIVIL:</span>
          <span class="card-data-value">${escapeHtml(display(p.estado_civil))}</span>
        </div>
      </div>
      <div class="card-data-row">
        <div class="card-data-item">
          <span class="card-data-label">FECHA NAC:</span>
          <span class="card-data-value">${escapeHtml(formatDate(p.fecha_nacimiento))}</span>
        </div>
        <div class="card-data-item">
          <span class="card-data-label">LUGAR DE NACIMIENTO</span>
          <span class="card-data-value">${escapeHtml(display(p.lugar_nacimiento))}</span>
        </div>
        <div class="card-data-item">
          <span class="card-data-label">GENERO</span>
          <span class="card-data-value">${escapeHtml(gen)}</span>
        </div>
      </div>
      <div class="card-data-row">
        <div class="card-data-item">
          <span class="card-data-label">INDIGENA:</span>
          <span class="card-data-value">${escapeHtml(tri(p.indigena))}</span>
        </div>
        <div class="card-data-item">
          <span class="card-data-label">AFROAMERICANO</span>
          <span class="card-data-value">${escapeHtml(tri(p.afromexicano))}</span>
        </div>
        <div class="card-data-item">
          <span class="card-data-label">DISCAPACIDAD</span>
          <span class="card-data-value">${escapeHtml(tri(p.discapacidad))}</span>
        </div>
      </div>
      <div class="card-data-row two-col">
        <div class="card-data-item">
          <span class="card-data-label">CONSULTORIO:</span>
          <span class="card-data-value">${escapeHtml(display(p.consultorio_nombre || p.consultorio_clave))}</span>
        </div>
        <div class="card-data-item">
          <span class="card-data-label">TURNO:</span>
          <span class="card-data-value">${escapeHtml(display(p.turno_nombre || p.turno_clave))}</span>
        </div>
      </div>

      <div class="modal-section-title">LUGAR DE RESIDENCIA</div>
      <div class="card-data-row">
        <div class="card-data-item">
          <span class="card-data-label">PAIS:</span>
          <span class="card-data-value">${escapeHtml(display(p.pais_residencia))}</span>
        </div>
        <div class="card-data-item">
          <span class="card-data-label">ENTIDAD</span>
          <span class="card-data-value">${escapeHtml(display(p.entidad_residencia))}</span>
        </div>
        <div class="card-data-item">
          <span class="card-data-label">MUNICIPIO</span>
          <span class="card-data-value">${escapeHtml(display(p.municipio))}</span>
        </div>
      </div>
      <div class="card-data-row">
        <div class="card-data-item">
          <span class="card-data-label">COLONIA:</span>
          <span class="card-data-value">${escapeHtml(display(p.colonia))}</span>
        </div>
        <div class="card-data-item">
          <span class="card-data-label">CALLE Y NUMERO</span>
          <span class="card-data-value">${escapeHtml(display(p.calle))}</span>
        </div>
        <div class="card-data-item">
          <span class="card-data-label">C.P.:</span>
          <span class="card-data-value">${escapeHtml(display(p.codigo_postal))}</span>
        </div>
      </div>

      <div class="modal-section-title">CONTACTO</div>
      <div class="card-data-row two-col">
        <div class="card-data-item">
          <span class="card-data-label">TELEFONO/CEL</span>
          <span class="card-data-value">${escapeHtml(display(p.telefono))}</span>
        </div>
        <div class="card-data-item">
          <span class="card-data-label">OTRO CONTACTO</span>
          <span class="card-data-value">${escapeHtml(display(p.otro_contacto))}</span>
        </div>
      </div>
    </div>`;
  }

  function setCardMode(isEditing) {
    const title = $('[data-card-title]');
    const viewContainer = $('[data-card-view-mode]');
    const editForm = $('[data-card-edit-mode]');
    if (isEditing) {
      if (title) title.textContent = 'EDITAR DATOS DEL PACIENTE';
      if (viewContainer) viewContainer.hidden = true;
      if (editForm) {
        populatePatientAssignmentSelects(state.context);
        editForm.hidden = false;
        fill(editForm, state.cardPatient);
        editForm.elements.namedItem('public_id').value = state.cardPatient.public_id;
      }
    } else {
      if (title) title.textContent = 'TARJETA DE PACIENTE';
      if (viewContainer) {
        viewContainer.hidden = false;
        $('[data-card-content]').innerHTML = cardHtml(state.cardPatient);
      }
      if (editForm) editForm.hidden = true;
    }
  }

  async function openCard(ref, trigger) {
    try {
      const data = await get({ action: 'patient', ref });
      state.cardPatient = data.patient;
      setCardMode(false);
      openModal('card', trigger);
    } catch (error) {
      toast(error.message);
    }
  }

  async function saveCardEdit(event) {
    event.preventDefault();
    const form = event.currentTarget;
    const btn = $('button[type="submit"]', form);
    if (btn) btn.disabled = true;
    try {
      const data = await post('save_patient', form);
      state.cardPatient = data.patient;
      setCardMode(false);
      await loadRows();
      toast('DATOS ACTUALIZADOS.');
    } catch (error) {
      const errNode = $('[data-card-form-error]');
      if (errNode) { errNode.textContent = error.message; errNode.hidden = false; }
    } finally {
      if (btn) btn.disabled = false;
    }
  }

  // ==========================================================================
  // SELECTOR COMPACTO DE HISTORIAL POR FECHAS (Arquitectura Reutilizable)
  // ==========================================================================
  function openHistorySelector({ title, patient, history, newButtonLabel, onSelectRecord, onCreateNew, trigger }) {
    const titleNode = $('[data-history-module-title]');
    const patientNode = $('[data-history-patient-name]');
    const listNode = $('[data-history-dates-list]');
    const newBtn = $('[data-history-new-btn]');

    if (titleNode) titleNode.textContent = title || 'HISTORIAL DE REGISTROS';
    if (patientNode) patientNode.textContent = patientLabel(patient);

    if (listNode) {
      listNode.innerHTML = (history || []).map((item, idx) => {
        const cleanDate = cleanIsoDate(item.fecha) || String(item.fecha || '').slice(0, 10);
        return `<button type="button" class="history-date-item" data-history-idx="${idx}">
          ${escapeHtml(cleanDate)}
        </button>`;
      }).join('');

      $$('[data-history-idx]', listNode).forEach(btn => {
        btn.addEventListener('click', () => {
          const idx = Number(btn.dataset.historyIdx);
          const selectedItem = history[idx];
          closeModal(modalElement('history-selector'), true);
          if (selectedItem && typeof onSelectRecord === 'function') {
            onSelectRecord(selectedItem, trigger);
          }
        });
      });
    }

    if (newBtn) {
      newBtn.textContent = newButtonLabel || '+ Nuevo Registro';
      newBtn.onclick = () => {
        closeModal(modalElement('history-selector'), true);
        if (typeof onCreateNew === 'function') {
          onCreateNew(trigger);
        }
      };
    }

    openModal('history-selector', trigger);
  }

  // ==========================================================================
  // ATENCIÓN / CONSULTA (Modal ➕ - Con Selector de Historial y Modo Lectura/Edición)
  // ==========================================================================
  function setAttentionReadOnly(isReadOnly) {
    const form = $('#attention-form');
    if (!form) return;

    const editBtn = $('[data-attention-edit-btn]', form);
    const saveBtn = $('[data-attention-save-btn]', form);

    $$('input:not([type="hidden"]), select, textarea', form).forEach(ctrl => {
      ctrl.disabled = isReadOnly;
    });

    if (!isReadOnly) {
      toggleCompanion();
    } else {
      const fields = $('[data-companion-fields]', form);
      if (fields) fields.disabled = true;
    }

    if (editBtn) editBtn.hidden = !isReadOnly;
    if (saveBtn) {
      saveBtn.hidden = isReadOnly;
      saveBtn.disabled = isReadOnly;
    }
  }

  function enableAttentionEdit() {
    const titleNode = $('[data-attention-modal-title]');
    if (titleNode) titleNode.textContent = 'EDITAR CONSULTA / ATENCIÓN';
    setAttentionReadOnly(false);
  }

  function openNewAttentionModal(p, trigger) {
    const form = $('#attention-form');
    form.reset();
    setError(form);
    if (form.elements.namedItem('id_atencion')) {
      form.elements.namedItem('id_atencion').value = '';
    }
    if (form.elements.namedItem('attention_ref')) {
      form.elements.namedItem('attention_ref').value = '';
    }
    form.elements.namedItem('patient_ref').value = p.public_id;

    const titleNode = $('[data-attention-modal-title]');
    if (titleNode) titleNode.textContent = 'REGISTRAR ATENCIÓN';

    $('[data-attention-patient-name]').textContent = patientLabel(p);
    $('[data-attention-patient-nss]').textContent = p.identidad;

    setAttentionReadOnly(false);
    openModal('attention', trigger);
  }

  async function loadAttentionByIdOrRef(idAtencion, attentionRef, trigger) {
    try {
      await loadContext();
      const params = { action: 'attention' };
      if (idAtencion) params.id_atencion = idAtencion;
      if (attentionRef) params.ref = attentionRef;

      const data = await get(params);
      const att = data.attention;
      const comp = data.companion || {};
      const p = data.patient;
      const form = $('#attention-form');
      form.reset();
      setError(form);

      if (form.elements.namedItem('id_atencion')) {
        form.elements.namedItem('id_atencion').value = att.atencion_id || '';
      }
      if (form.elements.namedItem('attention_ref')) {
        form.elements.namedItem('attention_ref').value = att.public_id || '';
      }
      form.elements.namedItem('patient_ref').value = p.public_id;

      const titleNode = $('[data-attention-modal-title]');
      if (titleNode) titleNode.textContent = `CONSULTA DE ATENCIÓN (${cleanIsoDate(att.fecha) || att.fecha || ''})`;

      $('[data-attention-patient-name]').textContent = patientLabel(p);
      $('[data-attention-patient-nss]').textContent = p.identidad;

      // Acompañante
      const noCompCheck = $('[name="sin_acompanante"]', form);
      if (noCompCheck) {
        noCompCheck.checked = Boolean(Number(att.sin_acompanante));
      }
      fill(form, {
        acompanante_apellido_paterno: comp.apellido_paterno,
        acompanante_apellido_materno: comp.apellido_materno,
        acompanante_nombres: comp.nombres,
        acompanante_fecha_nacimiento: comp.fecha_nacimiento,
        acompanante_lugar_nacimiento: comp.lugar_nacimiento,
        acompanante_sexo: comp.sexo,
        acompanante_parentesco: comp.parentesco,
        acompanante_observaciones: comp.observaciones
      });

      // Signos vitales y somatometría
      fill(form, {
        temperatura: att.temperatura,
        frecuencia_cardiaca: att.frecuencia_cardiaca,
        frecuencia_respiratoria: att.frecuencia_respiratoria,
        peso: att.peso,
        talla: att.talla,
        perimetro_cefalico: att.perimetro_cefalico,
        perimetro_toracico: att.perimetro_toracico,
        perimetro_abdominal: att.perimetro_abdominal,
        vitamina_a_fecha: att.vitamina_a_fecha,
        desparasitacion_fecha: att.desparasitacion_fecha,
        cepillado_dental_fecha: att.cepillado_dental_fecha,
        agudeza_visual_od: att.agudeza_visual_od,
        agudeza_visual_oi: att.agudeza_visual_oi,
        exploracion_fisica: att.exploracion_fisica,
        nota_enfermeria: att.nota_enfermeria,
        educacion_salud_texto: att.educacion_salud_texto,
        observaciones_atencion: att.observaciones
      });

      // Temas de educación
      const topicIds = new Set(data.topics || []);
      $$('[name="educacion_tema[]"]', form).forEach(cb => {
        cb.checked = topicIds.has(Number(cb.value));
      });

      // Abrir en modo lectura (con botones EDITAR y CANCELAR)
      setAttentionReadOnly(true);
      openModal('attention', trigger);
    } catch (error) {
      toast(error.message);
    }
  }

  async function openAttention(ref, trigger) {
    try {
      await loadContext();
      const data = await get({ action: 'attention_history', ref });
      const p = data.patient;
      const history = data.history || [];

      // ESCENARIO A: Sin registros previos -> Abrir formulario en blanco directamente
      if (history.length === 0) {
        openNewAttentionModal(p, trigger);
        return;
      }

      // ESCENARIO B: Con 1 o más registros previos -> Abrir pequeño popup selector de fechas
      openHistorySelector({
        title: 'ATENCIÓN DE ENFERMERÍA',
        patient: p,
        history,
        newButtonLabel: '+ Nueva Atención',
        onSelectRecord: item => loadAttentionByIdOrRef(item.id, item.public_id, trigger),
        onCreateNew: () => openNewAttentionModal(p, trigger),
        trigger
      });
    } catch (error) {
      toast(error.message);
    }
  }

  function toggleCompanion() {
    const checked = $('[name="sin_acompanante"]', $('#attention-form')).checked;
    const fields = $('[data-companion-fields]');
    if (fields) {
      fields.disabled = checked;
      if (checked) {
        $$('input,select,textarea', fields).forEach(f => { f.value = ''; });
      }
    }
  }

  async function saveAttention(event) {
    event.preventDefault();
    const form = event.currentTarget;
    setError(form);
    const btn = $('[data-attention-save-btn]', form) || $('button[type="submit"]', form);
    if (btn) btn.disabled = true;
    try {
      const isEdit = Boolean(form.elements.namedItem('id_atencion')?.value || form.elements.namedItem('attention_ref')?.value);
      const data = await post('save_attention', form);
      closeModal(modalElement('attention'));
      await loadRows();
      toast(data.message || (isEdit ? 'ATENCIÓN ACTUALIZADA.' : 'ATENCIÓN REGISTRADA.'));
    } catch (error) {
      setError(form, error.message);
      toast(error.message);
    } finally {
      if (btn) btn.disabled = false;
    }
  }

  // ==========================================================================
  // ESQUEMA DE VACUNACIÓN (Modal 🦠 - Página 6 PDF + Modo Edición)
  // ==========================================================================
  const doseColumns = ['1ER', '2DA', '3ER', '4TA', 'REF', 'ADIC', 'ADIC2'];

  function showVaccineFeedback(message = '', type = 'success') {
    const node = $('[data-vaccine-feedback]');
    if (!node) return;
    if (!message) {
      node.hidden = true;
      node.textContent = '';
      node.className = 'vaccine-feedback-alert';
      return;
    }
    node.className = `vaccine-feedback-alert ${type === 'error' ? 'error' : 'success'}`;
    node.textContent = message;
    node.hidden = false;
  }

  function showVaccineConfirmDialog(visible) {
    const dialog = $('[data-vaccine-confirm-dialog]');
    if (!dialog) return;
    dialog.hidden = !visible;
    if (visible) {
      const keepBtn = $('[data-vaccine-confirm-stay]', dialog);
      if (keepBtn) keepBtn.focus();
    }
  }

  function updateVaccineEditButtonUI() {
    const btn = $('[data-toggle-vaccine-edit]');
    const label = $('[data-vaccine-edit-label]');
    const hint = $('[data-vaccine-mode-hint]');
    if (btn) {
      btn.classList.toggle('active', state.vaccineEditMode);
      btn.setAttribute('aria-pressed', state.vaccineEditMode ? 'true' : 'false');
    }
    if (label) {
      label.textContent = state.vaccineEditMode ? 'Modo Edición Activo' : 'Editar Registro';
    }
    if (hint) {
      hint.textContent = state.vaccineEditMode
        ? 'Modo Edición activo: corrige cualquier fecha aplicada, lote o caducidad y presiona GUARDAR.'
        : 'Haz clic o doble clic sobre una fecha en verde o activa "Editar Registro" para corregir dosis aplicadas.';
    }
  }

  function syncVaccineDirtyState() {
    const modal = modalElement('vaccines');
    if (!modal) return;
    let dirty = false;

    $$('input[data-vaccine-key]', modal).forEach(input => {
      const orig = input.dataset.originalValue ?? '';
      const curr = input.value.trim();
      const modified = curr !== orig;
      input.classList.toggle('is-modified', modified);
      if (modified) dirty = true;
    });

    $$('input[data-lote], input[data-cad]', modal).forEach(input => {
      const orig = (input.dataset.originalValue ?? '').trim().toUpperCase();
      const curr = input.value.trim().toUpperCase();
      const modified = curr !== orig;
      input.classList.toggle('is-modified', modified);
      if (modified) dirty = true;
    });

    hasUnsavedChanges = dirty;
    const badge = $('[data-vaccine-unsaved-badge]');
    if (badge) badge.hidden = !dirty;
  }

  function updateRowSequentialInputs(row) {
    if (!row) return;
    const pendingInputs = $$('input[data-vaccine-key][data-is-applied="0"]', row);
    let canEnableNext = true;
    pendingInputs.forEach(input => {
      const td = input.closest('td');
      if (canEnableNext) {
        input.disabled = false;
        if (td) {
          td.classList.remove('vaccine-cell-pending');
          td.classList.add('vaccine-cell-active');
        }
        if (!cleanIsoDate(input.value)) {
          canEnableNext = false;
        }
      } else {
        if (!input.value) {
          input.disabled = true;
          if (td) {
            td.classList.remove('vaccine-cell-active');
            td.classList.add('vaccine-cell-pending');
          }
        }
      }
    });
  }

  function convertAppliedCellToInput(cell, focusInput = false) {
    if (!cell || $('input[data-vaccine-key]', cell)) return;
    const vKey = cell.dataset.vaccineKey || '';
    const dKey = cell.dataset.doseKey || '';
    const origDate = cleanIsoDate(cell.dataset.originalDate || '');
    const currentDate = cleanIsoDate(cell.dataset.currentDate || origDate);
    cell.classList.add('is-editing-cell');
    cell.innerHTML = `<input type="date" class="vaccine-input-date vaccine-input-editing"
      value="${escapeHtml(currentDate)}"
      max="${today()}"
      data-vaccine-key="${escapeHtml(vKey)}"
      data-dose-key="${escapeHtml(dKey)}"
      data-original-value="${escapeHtml(origDate)}"
      data-is-applied="1"
      aria-label="Editar ${escapeHtml(vKey)} dosis ${escapeHtml(dKey)}">`;
    const input = $('input[data-vaccine-key]', cell);
    if (input) {
      input.addEventListener('input', () => {
        cell.dataset.currentDate = input.value;
        syncVaccineDirtyState();
      });
      input.addEventListener('change', () => {
        cell.dataset.currentDate = input.value;
        syncVaccineDirtyState();
      });
      syncVaccineDirtyState();
      if (focusInput) {
        input.focus();
        if (typeof input.showPicker === 'function') {
          try { input.showPicker(); } catch (_) { /* ignore */ }
        }
      }
    }
  }

  function revertUnmodifiedAppliedCellToBadge(cell) {
    if (!cell) return;
    const input = $('input[data-vaccine-key]', cell);
    if (!input) return;
    const origDate = cleanIsoDate(cell.dataset.originalDate || '');
    const currDate = cleanIsoDate(input.value);
    // Si el usuario modificó la fecha, mantener el input visible para no perder su edición
    if (currDate !== origDate) return;
    cell.classList.remove('is-editing-cell');
    cell.innerHTML = `<span class="vaccine-badge-date">${escapeHtml(formatDate(origDate))}</span>`;
  }

  function toggleVaccineEditMode(forceState) {
    state.vaccineEditMode = typeof forceState === 'boolean' ? forceState : !state.vaccineEditMode;
    updateVaccineEditButtonUI();
    const modal = modalElement('vaccines');
    if (!modal) return;

    $$('td[data-applied-cell]', modal).forEach(cell => {
      if (state.vaccineEditMode) {
        convertAppliedCellToInput(cell, false);
      } else {
        revertUnmodifiedAppliedCellToBadge(cell);
      }
    });

    $$('input[data-lote], input[data-cad]', modal).forEach(input => {
      input.classList.toggle('vaccine-input-editing', state.vaccineEditMode);
    });
  }

  function vaccineRowsHtml(vaccines, isEditMode = false, recentlySavedKeys = new Set()) {
    return vaccines.map(vaccine => {
      const byCode = Object.fromEntries(vaccine.slots.map(s => [s.codigo, s]));
      let rowLote = vaccine.lote || '';
      let rowCad = cleanIsoDate(vaccine.caducidad || '');
      let lastAppliedDose = '';

      vaccine.slots.forEach(s => {
        if (s.aplicacion_public_id && cleanIsoDate(s.fecha_aplicacion)) {
          lastAppliedDose = s.codigo;
          if (s.lote) rowLote = s.lote;
          if (cleanIsoDate(s.caducidad)) rowCad = cleanIsoDate(s.caducidad);
        }
      });

      const cells = doseColumns.map(code => {
        const slot = byCode[code];
        const cellKey = `${vaccine.clave}:${code}`;
        const savedPulseClass = recentlySavedKeys.has(cellKey) ? ' vaccine-cell-just-saved' : '';

        // 1. Si no existe en la matriz (campo en gris en Excel)
        if (!slot) {
          return '<td class="vaccine-cell-gray"><span class="vaccine-slot-locked">—</span></td>';
        }
        // 2. Si ya fue aplicada la dosis, mostrar fecha en badge verde (o input si Modo Edición está activo)
        const appliedDate = cleanIsoDate(slot.fecha_aplicacion);
        if (slot.aplicacion_public_id && appliedDate) {
          if (isEditMode) {
            return `<td class="vaccine-cell-applied is-editing-cell${savedPulseClass}"
              data-applied-cell
              data-vaccine-key="${escapeHtml(vaccine.clave)}"
              data-dose-key="${escapeHtml(slot.codigo)}"
              data-original-date="${escapeHtml(appliedDate)}"
              data-current-date="${escapeHtml(appliedDate)}">
              <input type="date" class="vaccine-input-date vaccine-input-editing"
                value="${escapeHtml(appliedDate)}"
                max="${today()}"
                data-vaccine-key="${escapeHtml(vaccine.clave)}"
                data-dose-key="${escapeHtml(slot.codigo)}"
                data-original-value="${escapeHtml(appliedDate)}"
                data-is-applied="1">
            </td>`;
          }
          return `<td class="vaccine-cell-applied${savedPulseClass}"
            data-applied-cell
            data-vaccine-key="${escapeHtml(vaccine.clave)}"
            data-dose-key="${escapeHtml(slot.codigo)}"
            data-original-date="${escapeHtml(appliedDate)}"
            data-current-date="${escapeHtml(appliedDate)}"
            title="Clic o doble clic para corregir esta fecha">
            <span class="vaccine-badge-date">${escapeHtml(formatDate(appliedDate))}</span>
          </td>`;
        }
        // 3. Si está habilitada secuencialmente (siguiente dosis pendiente)
        if (slot.habilitado) {
          return `<td class="vaccine-cell-active">
            <input type="date" class="vaccine-input-date"
              value=""
              max="${today()}"
              data-vaccine-key="${escapeHtml(vaccine.clave)}"
              data-dose-key="${escapeHtml(slot.codigo)}"
              data-original-value=""
              data-is-applied="0">
          </td>`;
        }
        // 4. Campo en blanco pero deshabilitado en espera de registrar la dosis anterior
        return `<td class="vaccine-cell-pending">
          <input type="date" class="vaccine-input-date"
            value=""
            max="${today()}"
            data-vaccine-key="${escapeHtml(vaccine.clave)}"
            data-dose-key="${escapeHtml(slot.codigo)}"
            data-original-value=""
            data-is-applied="0"
            disabled
            placeholder="00/00/0000">
        </td>`;
      });

      const editClass = isEditMode ? ' vaccine-input-editing' : '';
      const lotInput = `<input type="text" class="vaccine-input-text${editClass}" value="${escapeHtml(rowLote)}" data-vaccine-meta="${escapeHtml(vaccine.clave)}" data-lote data-original-value="${escapeHtml(rowLote)}" maxlength="80" placeholder="LOTE">`;
      const cadInput = `<input type="date" class="vaccine-input-date${editClass}" value="${escapeHtml(rowCad)}" data-vaccine-meta="${escapeHtml(vaccine.clave)}" data-cad data-original-value="${escapeHtml(rowCad)}">`;

      return `<tr data-vaccine-row="${escapeHtml(vaccine.clave)}" data-last-applied-dose="${escapeHtml(lastAppliedDose)}">
        <th scope="row">${escapeHtml(vaccine.nombre)}</th>
        ${cells.join('')}
        <td>${lotInput}</td>
        <td>${cadInput}</td>
      </tr>`;
    }).join('');
  }

  function bindVaccineGridEvents() {
    const grid = $('[data-vaccine-grid]');
    if (!grid) return;

    // Clic o doble clic en cualquier celda verde aplicada activa edición in-situ de esa fecha
    $$('td[data-applied-cell]', grid).forEach(cell => {
      cell.addEventListener('click', event => {
        if (event.target.tagName === 'INPUT') return;
        convertAppliedCellToInput(cell, true);
      });
      cell.addEventListener('dblclick', event => {
        if (event.target.tagName === 'INPUT') return;
        convertAppliedCellToInput(cell, true);
      });
    });

    // Eventos de cambio en inputs de fechas de dosis
    $$('input[data-vaccine-key]', grid).forEach(input => {
      const onChange = () => {
        const row = input.closest('tr');
        if (input.dataset.isApplied === '1') {
          const cell = input.closest('td[data-applied-cell]');
          if (cell) cell.dataset.currentDate = input.value;
        } else {
          updateRowSequentialInputs(row);
        }
        syncVaccineDirtyState();
      };
      input.addEventListener('input', onChange);
      input.addEventListener('change', onChange);
    });

    // Eventos de cambio en LOTE y CAD
    $$('input[data-lote], input[data-cad]', grid).forEach(input => {
      const onMetaChange = () => {
        if (input.hasAttribute('data-lote')) {
          input.value = input.value.toUpperCase();
        }
        syncVaccineDirtyState();
      };
      input.addEventListener('input', onMetaChange);
      input.addEventListener('change', onMetaChange);
    });
  }

  function renderVaccineGrid(vaccines, isEditMode = false, recentlySavedKeys = new Set()) {
    state.vaccines = vaccines || [];
    $('[data-vaccine-grid]').innerHTML = vaccineRowsHtml(state.vaccines, isEditMode, recentlySavedKeys);
    bindVaccineGridEvents();
    syncVaccineDirtyState();
  }

  async function openVaccines(ref, trigger) {
    try {
      const data = await get({ action: 'vaccines', ref });
      state.patient = data.patient;
      state.vaccineEditMode = false;
      hasUnsavedChanges = false;
      showVaccineConfirmDialog(false);
      showVaccineFeedback('', '');
      updateVaccineEditButtonUI();
      $('[data-vaccine-patient-name]').textContent = patientLabel(data.patient);
      $('[data-vaccine-patient-nss]').textContent = data.patient.identidad;
      renderVaccineGrid(data.vaccines, false);
      openModal('vaccines', trigger);
    } catch (error) {
      toast(error.message);
    }
  }

  function collectVaccinePayload() {
    const grid = $('[data-vaccine-grid]');
    if (!grid) return [];
    const payload = [];

    for (const row of $$('tr[data-vaccine-row]', grid)) {
      const vaccineKey = row.dataset.vaccineRow;
      const vaccineName = $('th', row)?.textContent?.trim() || vaccineKey;
      const loteInput = $('[data-lote]', row);
      const cadInput = $('[data-cad]', row);

      const loteVal = (loteInput?.value || '').trim().toUpperCase();
      const origLote = (loteInput?.dataset.originalValue || '').trim().toUpperCase();

      const rawCad = (cadInput?.value || '').trim();
      const cadVal = cleanIsoDate(rawCad);
      if (rawCad !== '' && !cadVal) {
        throw new Error(`La fecha de caducidad en ${vaccineName} no es válida.`);
      }
      const origCad = cleanIsoDate(cadInput?.dataset.originalValue || '');
      const metaChanged = loteVal !== origLote || cadVal !== origCad;

      let rowAddedDose = false;
      const doseInputs = $$('input[data-vaccine-key]', row);

      for (const input of doseInputs) {
        const doseKey = input.dataset.doseKey;
        const isApplied = input.dataset.isApplied === '1';
        const origVal = cleanIsoDate(input.dataset.originalValue || '');
        const rawVal = input.value.trim();
        const cleanVal = cleanIsoDate(rawVal);

        if (isApplied) {
          if (rawVal === '' || !cleanVal) {
            throw new Error(`La fecha registrada de ${vaccineName} (${doseKey}) no puede quedar vacía ni con formato inválido.`);
          }
          if (cleanVal !== origVal || metaChanged) {
            payload.push({
              vacuna_clave: vaccineKey,
              dosis_clave: doseKey,
              fecha_aplicacion: cleanVal,
              lote: loteVal,
              caducidad: cadVal,
              is_edit: true
            });
            rowAddedDose = true;
          }
        } else if (rawVal !== '') {
          if (!cleanVal) {
            throw new Error(`La fecha capturada en ${vaccineName} (${doseKey}) no tiene un formato válido.`);
          }
          payload.push({
            vacuna_clave: vaccineKey,
            dosis_clave: doseKey,
            fecha_aplicacion: cleanVal,
            lote: loteVal,
            caducidad: cadVal,
            is_edit: false
          });
          rowAddedDose = true;
        }
      }

      // Si el usuario editó LOTE o CAD en una vacuna con dosis ya aplicadas sin abrir el input de fecha
      if (metaChanged && !rowAddedDose) {
        const appliedCells = $$('td[data-applied-cell]', row);
        if (appliedCells.length > 0) {
          const lastCell = appliedCells[appliedCells.length - 1];
          const existingDate = cleanIsoDate(lastCell.dataset.currentDate || lastCell.dataset.originalDate || '');
          if (existingDate) {
            payload.push({
              vacuna_clave: vaccineKey,
              dosis_clave: lastCell.dataset.doseKey,
              fecha_aplicacion: existingDate,
              lote: loteVal,
              caducidad: cadVal,
              is_edit: true
            });
          }
        }
      }
    }

    return payload;
  }

  async function saveVaccines() {
    const btn = $('[data-save-vaccines]');
    showVaccineFeedback('', '');

    let payload;
    try {
      payload = collectVaccinePayload();
    } catch (validationError) {
      showVaccineFeedback(validationError.message, 'error');
      toast(validationError.message);
      return;
    }

    if (!payload.length) {
      const msg = 'Captura una nueva fecha o modifica algún registro existente antes de guardar.';
      showVaccineFeedback(msg, 'error');
      toast(msg);
      return;
    }

    btn.disabled = true;
    try {
      const form = new FormData();
      form.set('patient_ref', state.patient.public_id);
      form.set('vaccines', JSON.stringify(payload));
      const data = await post('save_vaccines', form);

      const savedKeys = new Set(payload.map(item => `${item.vacuna_clave}:${item.dosis_clave}`));
      hasUnsavedChanges = false;
      state.vaccineEditMode = false;
      updateVaccineEditButtonUI();

      const updatedVaccines = data.vaccines || (await get({ action: 'vaccines', ref: state.patient.public_id })).vaccines;
      renderVaccineGrid(updatedVaccines, false, savedKeys);

      const successMsg = data.message || 'ESQUEMA DE VACUNACIÓN ACTUALIZADO CORRECTAMENTE.';
      showVaccineFeedback(`✓ ${successMsg}`, 'success');
      toast(successMsg);
      await loadRows();
    } catch (error) {
      showVaccineFeedback(error.message, 'error');
      toast(error.message);
    } finally {
      btn.disabled = false;
    }
  }

  // ==========================================================================
  // CUESTIONARIO DE CÁNCER DE PRÓSTATA (Modal 📋 - PREVENIMSS / CHKT)
  // ==========================================================================
  const prostateScoreOptions = [0, 1, 2, 3, 4, 5];

  function calculateProstateValoracion(score) {
    if (score <= 0) return 'Normal';
    if (score <= 7) return 'Sintomatología LEVE';
    if (score <= 19) return 'Sintomatología MODERADA';
    return 'Sintomatología SEVERA';
  }

  function valoracionBadgeClass(valoracion) {
    if (valoracion === 'Normal') return 'level-normal';
    if (valoracion === 'Sintomatología LEVE') return 'level-leve';
    if (valoracion === 'Sintomatología MODERADA') return 'level-moderada';
    return 'level-severa';
  }

  function showProstateFeedback(message = '', type = 'success') {
    const node = $('[data-prostate-feedback]');
    if (!node) return;
    if (!message) {
      node.hidden = true;
      node.textContent = '';
      node.className = 'vaccine-feedback-alert';
      return;
    }
    node.className = `vaccine-feedback-alert ${type === 'error' ? 'error' : 'success'}`;
    node.textContent = message;
    node.hidden = false;
  }

  function showProstateConfirmDialog(visible) {
    const dialog = $('[data-prostate-confirm-dialog]');
    if (!dialog) return;
    dialog.hidden = !visible;
    if (visible) {
      const keepBtn = $('[data-prostate-confirm-stay]', dialog);
      if (keepBtn) keepBtn.focus();
    }
  }

  function updateProstateEditUI() {
    const hasExisting = Boolean(state.prostateRecord && state.prostateRecord.id);
    const isNew = Boolean(state.prostateIsNewMode);
    const isEditing = Boolean(state.prostateEditMode);
    const isEditable = !hasExisting || isNew || isEditing;

    const btnNuevo = $('[data-prostate-new]');
    const btnEditar = $('[data-prostate-edit]');
    const btnPrint = $('[data-prostate-print]');
    const saveBtn = $('[data-save-prostate]');
    const dateInput = $('[data-prostate-date]');
    const groupSelect = $('[data-prostate-risk-group]');

    if (btnNuevo) btnNuevo.hidden = true;

    if (!hasExisting || isNew || isEditing) {
      // Modo captura inicial, nuevo registro o edición activa: mostrar únicamente GUARDAR y CANCELAR
      if (btnEditar) btnEditar.hidden = true;
      if (btnPrint) btnPrint.hidden = true;
      if (saveBtn) {
        saveBtn.hidden = false;
        saveBtn.disabled = false;
      }
    } else {
      // Modo lectura de un registro histórico: mostrar EDITAR, IMPRIMIR FORMATO y CANCELAR
      if (btnEditar) btnEditar.hidden = false;
      if (btnPrint) btnPrint.hidden = false;
      if (saveBtn) {
        saveBtn.hidden = true;
        saveBtn.disabled = true;
      }
    }

    if (dateInput) {
      dateInput.disabled = !isEditable;
    }
    if (groupSelect) {
      groupSelect.disabled = !isEditable;
    }

    const grid = $('[data-prostate-grid]');
    if (grid) {
      $$('input[type="radio"][data-prostate-question]', grid).forEach(radio => {
        radio.disabled = !isEditable;
      });
      $$('.prostate-choice-label', grid).forEach(label => {
        label.classList.toggle('is-readonly', !isEditable);
      });
    }
  }

  function recalculateProstateLive() {
    let total = 0;
    Object.values(state.prostateAnswers).forEach(val => {
      if (val !== null && val !== undefined && val !== '') {
        total += Number(val) || 0;
      }
    });

    const valoracion = calculateProstateValoracion(total);
    const totalNode = $('[data-prostate-total-score]');
    const badgeNode = $('[data-prostate-valoracion-badge]');
    const scaleCards = $('[data-prostate-scale-cards]');

    if (totalNode) totalNode.textContent = String(total);
    if (badgeNode) {
      badgeNode.textContent = valoracion;
      badgeNode.className = `prostate-valoracion-badge ${valoracionBadgeClass(valoracion)}`;
    }
    if (scaleCards) {
      $$('[data-scale-level]', scaleCards).forEach(card => {
        card.classList.toggle('active', card.dataset.scaleLevel === valoracion);
      });
    }
  }

  function syncProstateDirtyState() {
    const dateInput = $('[data-prostate-date]');
    const groupSelect = $('[data-prostate-risk-group]');
    const currFecha = cleanIsoDate(dateInput?.value || '');
    const currGrupo = groupSelect?.value || '45_74';

    let dirty = false;
    if (currFecha !== state.prostateOrigFecha || currGrupo !== state.prostateOrigGrupo) {
      dirty = true;
    }

    if (!dirty) {
      for (const q of state.prostateQuestions) {
        const qId = String(q.id);
        const origVal = state.prostateOrigAnswers[qId] ?? null;
        const currVal = state.prostateAnswers[qId] ?? null;
        if (origVal !== currVal) {
          dirty = true;
          break;
        }
      }
    }

    hasProstateUnsavedChanges = dirty;
    const badge = $('[data-prostate-unsaved-badge]');
    if (badge) badge.hidden = !dirty;
  }

  function renderProstateGrid() {
    const grid = $('[data-prostate-grid]');
    if (!grid) return;
    const hasExisting = Boolean(state.prostateRecord && state.prostateRecord.id);
    const isNew = Boolean(state.prostateIsNewMode);
    const isEditing = Boolean(state.prostateEditMode);
    const isEditable = !hasExisting || isNew || isEditing;

    grid.innerHTML = state.prostateQuestions.map(q => {
      const qId = Number(q.id);
      const selectedScore = state.prostateAnswers[qId];
      const cells = prostateScoreOptions.map(score => {
        const isChecked = selectedScore !== undefined && selectedScore !== null && Number(selectedScore) === score;
        return `<td class="prostate-choice-cell${isChecked ? ' is-selected' : ''}">
          <label class="prostate-choice-label${!isEditable ? ' is-readonly' : ''}">
            <input type="radio"
              class="prostate-choice-radio"
              name="prostate_q_${qId}"
              value="${score}"
              data-prostate-question="${qId}"
              ${isChecked ? 'checked' : ''}
              ${!isEditable ? 'disabled' : ''}>
          </label>
        </td>`;
      }).join('');

      return `<tr data-prostate-question-row="${qId}">
        <td class="prostate-question-cell">
          <span class="prostate-q-number">${escapeHtml(q.orden)}.</span>
          <span class="prostate-q-text">${escapeHtml(q.pregunta)}</span>
        </td>
        ${cells}
      </tr>`;
    }).join('');

    $$('input[type="radio"][data-prostate-question]', grid).forEach(radio => {
      radio.addEventListener('change', () => {
        const qId = Number(radio.dataset.prostateQuestion);
        const val = Number(radio.value);
        state.prostateAnswers[qId] = val;

        const row = radio.closest('tr');
        if (row) {
          $$('.prostate-choice-cell', row).forEach(td => td.classList.remove('is-selected'));
          const cell = radio.closest('.prostate-choice-cell');
          if (cell) cell.classList.add('is-selected');
        }

        recalculateProstateLive();
        syncProstateDirtyState();
      });
    });
  }

  function applyProstateStateToModal(data) {
    state.prostatePatient = data.patient;
    state.prostateQuestions = data.questions || [];
    state.prostateRecord = data.record || null;
    state.prostateAnswers = {};
    state.prostateOrigAnswers = {};

    const rawAnswers = data.answers || {};
    state.prostateQuestions.forEach(q => {
      const qId = Number(q.id);
      if (Object.prototype.hasOwnProperty.call(rawAnswers, qId) || Object.prototype.hasOwnProperty.call(rawAnswers, String(qId))) {
        const score = Number(rawAnswers[qId] ?? rawAnswers[String(qId)]);
        state.prostateAnswers[qId] = score;
        state.prostateOrigAnswers[qId] = score;
      } else {
        state.prostateAnswers[qId] = null;
        state.prostateOrigAnswers[qId] = null;
      }
    });

    const hasExisting = Boolean(state.prostateRecord && state.prostateRecord.id);
    state.prostateEditMode = false;
    state.prostateIsNewMode = false;
    hasProstateUnsavedChanges = false;

    const fechaVal = cleanIsoDate(state.prostateRecord?.fecha) || today();
    const grupoVal = state.prostateRecord?.grupo_riesgo || '45_74';
    state.prostateOrigFecha = fechaVal;
    state.prostateOrigGrupo = grupoVal;

    $('[data-prostate-patient-name]').textContent = patientLabel(data.patient);
    $('[data-prostate-patient-nss]').textContent = data.patient.identidad || '';

    const dateInput = $('[data-prostate-date]');
    const groupSelect = $('[data-prostate-risk-group]');
    if (dateInput) dateInput.value = fechaVal;
    if (groupSelect) groupSelect.value = grupoVal;

    renderProstateGrid();
    updateProstateEditUI();
    recalculateProstateLive();
    syncProstateDirtyState();
  }

  function openNewProstateModal(baseData, trigger) {
    showProstateConfirmDialog(false);
    showProstateFeedback('', '');
    applyProstateStateToModal({
      patient: baseData.patient,
      questions: baseData.questions || [],
      record: null,
      answers: {}
    });
    state.prostateIsNewMode = true;
    state.prostateEditMode = false;
    updateProstateEditUI();
    renderProstateGrid();
    openModal('prostate', trigger);
  }

  async function loadProstateRecord(ref, dcId, trigger) {
    try {
      const data = await get({ action: 'get_prostate_questionnaire', ref, id_dc_prostata: dcId });
      showProstateConfirmDialog(false);
      showProstateFeedback('', '');
      applyProstateStateToModal(data);
      openModal('prostate', trigger);
    } catch (error) {
      toast(error.message);
    }
  }

  async function openProstate(ref, trigger) {
    try {
      const data = await get({ action: 'get_prostate_questionnaire', ref });
      const history = data.history || [];

      // ESCENARIO A: El paciente NO tiene ningún registro previo -> Abrir formulario limpio directamente
      if (history.length === 0) {
        openNewProstateModal(data, trigger);
        return;
      }

      // ESCENARIO B: El paciente SÍ tiene 1 o más registros previos -> Abrir pequeño popup selector de fechas
      openHistorySelector({
        title: 'DETECCIÓN DE CÁNCER DE PRÓSTATA',
        patient: data.patient,
        history,
        newButtonLabel: '+ Nuevo Registro',
        onSelectRecord: item => loadProstateRecord(ref, item.id, trigger),
        onCreateNew: () => openNewProstateModal(data, trigger),
        trigger
      });
    } catch (error) {
      toast(error.message);
    }
  }

  function enableProstateEdit() {
    state.prostateEditMode = true;
    state.prostateIsNewMode = false;
    showProstateFeedback('', '');
    updateProstateEditUI();
    renderProstateGrid();
  }

  function startProstateNew() {
    state.prostateRecord = null;
    state.prostateIsNewMode = true;
    state.prostateEditMode = false;
    state.prostateAnswers = {};
    state.prostateOrigAnswers = {};
    showProstateFeedback('', '');

    const fechaHoy = today();
    state.prostateOrigFecha = fechaHoy;
    state.prostateOrigGrupo = '45_74';

    const dateInput = $('[data-prostate-date]');
    const groupSelect = $('[data-prostate-risk-group]');
    if (dateInput) dateInput.value = fechaHoy;
    if (groupSelect) groupSelect.value = '45_74';

    renderProstateGrid();
    updateProstateEditUI();
    recalculateProstateLive();
    syncProstateDirtyState();
  }

  async function saveProstate() {
    const btn = $('[data-save-prostate]');
    showProstateFeedback('', '');

    const dateInput = $('[data-prostate-date]');
    const groupSelect = $('[data-prostate-risk-group]');
    const fecha = cleanIsoDate(dateInput?.value || '');
    const grupoRiesgo = groupSelect?.value || '45_74';

    if (!fecha) {
      const msg = 'Selecciona una fecha de evaluación válida.';
      showProstateFeedback(msg, 'error');
      toast(msg);
      return;
    }

    const answersPayload = {};
    for (const q of state.prostateQuestions) {
      const qId = Number(q.id);
      const val = state.prostateAnswers[qId];
      if (val === null || val === undefined || val === '') {
        const msg = `Por favor responde la pregunta ${q.orden} antes de guardar.`;
        showProstateFeedback(msg, 'error');
        toast(msg);
        return;
      }
      answersPayload[qId] = Number(val);
    }

    if (btn) btn.disabled = true;
    try {
      const form = new FormData();
      form.set('patient_ref', state.prostatePatient.public_id);
      form.set('fecha', fecha);
      form.set('grupo_riesgo', grupoRiesgo);
      form.set('respuestas', JSON.stringify(answersPayload));

      if (!state.prostateIsNewMode && state.prostateRecord && state.prostateRecord.id) {
        form.set('id_dc_prostata', String(state.prostateRecord.id));
      }

      const data = await post('save_prostate_questionnaire', form);
      applyProstateStateToModal(data);

      const successMsg = data.message || 'CUESTIONARIO DE PRÓSTATA GUARDADO CORRECTAMENTE.';
      showProstateFeedback(`✓ ${successMsg}`, 'success');
      toast(successMsg);
      await loadRows();
    } catch (error) {
      showProstateFeedback(error.message, 'error');
      toast(error.message);
      if (btn) btn.disabled = false;
    }
  }

  function printProstateDirect() {
    const ref = state.prostatePatient?.public_id;
    if (!ref) return;
    const url = new URL('index.php', window.location.href);
    url.searchParams.set('r', 'pdf_prostata');
    url.searchParams.set('ref', ref);
    if (state.prostateRecord && state.prostateRecord.id) {
      url.searchParams.set('id_dc_prostata', String(state.prostateRecord.id));
    }
    window.open(url.toString(), '_blank', 'noopener');
  }

  // ==========================================================================
  // DETECCIÓN DE CÁNCER DE COLON Y RECTO (Modal Captura Rápida)
  // ==========================================================================
  async function openColon(ref, trigger) {
    if (!ref) {
      toast('No se especificó la referencia del paciente.');
      return;
    }
    try {
      const data = await get({ action: 'patient', ref });
      const p = data.patient;
      state.colonPatient = p;

      const nameEl = $('[data-colon-patient-name]');
      const nssEl = $('[data-colon-patient-nss]');
      if (nameEl) nameEl.textContent = patientLabel(p);
      if (nssEl) nssEl.textContent = display(p.identidad);

      const refHidden = $('[data-colon-patient-ref]');
      if (refHidden) refHidden.value = p.public_id;

      const form = $('#colon-form');
      if (form) {
        form.reset();
        const upInput = $('#colon-up');
        if (upInput) {
          upInput.value = '';
        }
        for (let i = 1; i <= 5; i++) {
          const noRadio = form.querySelector(`input[name="colon_q${i}"][value="NO"]`);
          if (noRadio) noRadio.checked = true;
        }
      }

      openModal('colon', trigger);
    } catch (error) {
      toast(error.message || 'Error al abrir el cuestionario de colon.');
    }
  }

  function printColonSubmit(event) {
    event.preventDefault();
    const form = $('#colon-form');
    if (!form) return;

    const ref = state.colonPatient?.public_id || $('[data-colon-patient-ref]')?.value;
    if (!ref) {
      toast('No se encontró la referencia del paciente.');
      return;
    }

    const up = ($('#colon-up')?.value || '').trim();
    const citaLab = ($('#colon-cita-lab')?.value || '').trim();
    const citaEemf = ($('#colon-cita-eemf')?.value || '').trim();

    const q1 = form.querySelector('input[name="colon_q1"]:checked')?.value || 'NO';
    const q2 = form.querySelector('input[name="colon_q2"]:checked')?.value || 'NO';
    const q3 = form.querySelector('input[name="colon_q3"]:checked')?.value || 'NO';
    const q4 = form.querySelector('input[name="colon_q4"]:checked')?.value || 'NO';
    const q5 = form.querySelector('input[name="colon_q5"]:checked')?.value || 'NO';

    const url = new URL('index.php', window.location.href);
    url.searchParams.set('r', 'pdf_colon');
    url.searchParams.set('ref', ref);
    if (up) url.searchParams.set('up', up);
    if (citaLab) url.searchParams.set('cita_laboratorio', citaLab);
    if (citaEemf) url.searchParams.set('cita_eemf', citaEemf);
    url.searchParams.set('q1', q1);
    url.searchParams.set('q2', q2);
    url.searchParams.set('q3', q3);
    url.searchParams.set('q4', q4);
    url.searchParams.set('q5', q5);

    window.open(url.toString(), '_blank', 'noopener');
    closeModal(modalElement('colon'));
    setTimeout(() => { loadRows(); }, 650);
  }

  // ==========================================================================
  // CUESTIONARIO DE SALUD MENTAL (PHQ-4 + PHQ-TP)
  // ==========================================================================
  const mhPhq4Options = [0, 1, 2, 3];

  function formatUpCodes(claves) {
    if (!claves.length) return '—';
    if (claves.length === 1) return claves[0];
    if (claves.length === 2) return `${claves[0]}, ${claves[1]}`;
    return `${claves.slice(0, -1).join(', ')} y ${claves[claves.length - 1]}`;
  }

  function evaluateMhBlocks(sumAnsiedad, sumDepresion, panicoVal) {
    const isPosAnsiedad = sumAnsiedad > 3;
    const isPosDepresion = sumDepresion >= 3;
    const isPosPanico = panicoVal === 1;

    const interpretaciones = [];
    const clavesUp = [];

    if (isPosAnsiedad) {
      interpretaciones.push('PB TRASTORNO DE ANSIEDAD');
      clavesUp.push('UP307');
    }
    if (isPosDepresion) {
      interpretaciones.push('PB TRASTORNO DEPRESIVO');
      clavesUp.push('UP306');
    }
    if (isPosPanico) {
      interpretaciones.push('PB TRASTORNO DE PÁNICO, MIEDO');
      clavesUp.push('UP308');
    }

    const hasPositive = interpretaciones.length > 0;
    return {
      isPosAnsiedad,
      isPosDepresion,
      isPosPanico,
      hasPositive,
      valoracion: hasPositive ? interpretaciones.join(', ') : 'SIN RIESGO DETECTADO / NORMAL',
      claveUp: formatUpCodes(clavesUp),
      sitioEnvio: hasPositive ? 'ENVIO MF' : '—',
    };
  }

  function showMhFeedback(message = '', type = 'success') {
    const alertBox = $('[data-mh-feedback]');
    if (!alertBox) return;
    if (!message) {
      alertBox.hidden = true;
      alertBox.textContent = '';
      alertBox.className = 'vaccine-feedback-alert';
      return;
    }
    alertBox.textContent = message;
    alertBox.className = `vaccine-feedback-alert ${type}`;
    alertBox.hidden = false;
  }

  function showMhConfirmDialog(visible) {
    const dialog = $('[data-mh-confirm-dialog]');
    if (!dialog) return;
    dialog.hidden = !visible;
    if (visible) {
      const keepBtn = $('[data-mh-confirm-stay]', dialog);
      if (keepBtn) keepBtn.focus();
    }
  }

  function updateMhEditUI() {
    const hasExisting = Boolean(state.mhRecord && state.mhRecord.id);
    const isNew = Boolean(state.mhIsNewMode);
    const isEditing = Boolean(state.mhEditMode);
    const isEditable = !hasExisting || isNew || isEditing;

    const btnEditar = $('[data-mh-edit]');
    const btnPrint = $('[data-mh-print]');
    const saveBtn = $('[data-save-mh]');
    const dateInput = $('[data-mh-date]');

    if (!hasExisting || isNew || isEditing) {
      if (btnEditar) btnEditar.hidden = true;
      if (btnPrint) btnPrint.hidden = true;
      if (saveBtn) {
        saveBtn.hidden = false;
        saveBtn.disabled = false;
      }
    } else {
      if (btnEditar) btnEditar.hidden = false;
      if (btnPrint) btnPrint.hidden = false;
      if (saveBtn) {
        saveBtn.hidden = true;
        saveBtn.disabled = true;
      }
    }

    if (dateInput) {
      dateInput.disabled = !isEditable;
    }

    const grid = $('[data-mh-phq4-grid]');
    if (grid) {
      $$('input[type="radio"][data-mh-question]', grid).forEach(radio => {
        radio.disabled = !isEditable;
      });
      $$('.prostate-choice-label', grid).forEach(label => {
        label.classList.toggle('is-readonly', !isEditable);
      });
    }

    const q5Box = $('[data-mh-phqtp-box]');
    if (q5Box) {
      $$('input[type="radio"][data-mh-q5-radio]', q5Box).forEach(radio => {
        radio.disabled = !isEditable;
      });
      $$('.mh-phqtp-choice', q5Box).forEach(label => {
        label.classList.toggle('is-readonly', !isEditable);
      });
    }
  }

  function recalculateMhLive() {
    let sumAnsiedad = 0;
    let sumDepresion = 0;
    let panicoVal = null;

    state.mhQuestions.forEach(q => {
      const qId = Number(q.id);
      const ord = Number(q.orden);
      const val = state.mhAnswers[qId];
      const hasVal = val !== null && val !== undefined && val !== '';

      if (q.seccion === 'PHQTP' || ord === 5) {
        if (hasVal) {
          panicoVal = Number(val);
        }
      } else if (ord === 1 || ord === 2) {
        if (hasVal) {
          sumAnsiedad += Number(val) || 0;
        }
      } else if (ord === 3 || ord === 4) {
        if (hasVal) {
          sumDepresion += Number(val) || 0;
        }
      }
    });

    const evalRes = evaluateMhBlocks(sumAnsiedad, sumDepresion, panicoVal);

    const badgeNode = $('[data-mh-valoracion-badge]');
    const upBadgeNode = $('[data-mh-up-badge]');
    const envioBadgeNode = $('[data-mh-envio-badge]');

    if (badgeNode) {
      badgeNode.textContent = evalRes.valoracion;
      badgeNode.className = `prostate-valoracion-badge ${evalRes.hasPositive ? 'level-severa' : 'level-normal'}`;
    }
    if (upBadgeNode) {
      upBadgeNode.textContent = evalRes.claveUp;
      upBadgeNode.classList.toggle('is-active', evalRes.hasPositive);
    }
    if (envioBadgeNode) {
      envioBadgeNode.textContent = evalRes.sitioEnvio;
      envioBadgeNode.classList.toggle('is-active', evalRes.hasPositive);
    }

    const scoreP12Node = $('[data-mh-score-p12]');
    const scoreP34Node = $('[data-mh-score-p34]');
    const scoreP5Node = $('[data-mh-score-p5]');

    if (scoreP12Node) scoreP12Node.textContent = `(Actual: ${sumAnsiedad} pts)`;
    if (scoreP34Node) scoreP34Node.textContent = `(Actual: ${sumDepresion} pts)`;
    if (scoreP5Node) {
      const p5Label = panicoVal === 1 ? 'Sí' : (panicoVal === 0 ? 'No' : '—');
      scoreP5Node.textContent = `(Actual: ${p5Label})`;
    }

    const rowAnsiedad = $('[data-mh-result-row="ansiedad"]');
    const rowDepresion = $('[data-mh-result-row="depresion"]');
    const rowPanico = $('[data-mh-result-row="panico"]');

    if (rowAnsiedad) rowAnsiedad.classList.toggle('is-positive', evalRes.isPosAnsiedad);
    if (rowDepresion) rowDepresion.classList.toggle('is-positive', evalRes.isPosDepresion);
    if (rowPanico) rowPanico.classList.toggle('is-positive', evalRes.isPosPanico);
  }

  function syncMhDirtyState() {
    const dateInput = $('[data-mh-date]');
    const currFecha = cleanIsoDate(dateInput?.value || '');

    let dirty = false;
    if (currFecha !== state.mhOrigFecha) {
      dirty = true;
    }

    if (!dirty) {
      for (const q of state.mhQuestions) {
        const qId = String(q.id);
        const origVal = state.mhOrigAnswers[qId] ?? null;
        const currVal = state.mhAnswers[qId] ?? null;
        if (origVal !== currVal) {
          dirty = true;
          break;
        }
      }
    }

    hasMhUnsavedChanges = dirty;
  }

  function renderMentalHealthGrid() {
    const grid = $('[data-mh-phq4-grid]');
    if (!grid) return;

    const hasExisting = Boolean(state.mhRecord && state.mhRecord.id);
    const isNew = Boolean(state.mhIsNewMode);
    const isEditing = Boolean(state.mhEditMode);
    const isEditable = !hasExisting || isNew || isEditing;

    const phq4Questions = state.mhQuestions.filter(q => q.seccion === 'PHQ4' && Number(q.orden) <= 4);
    const q5Question = state.mhQuestions.find(q => q.seccion === 'PHQTP' || Number(q.orden) === 5);

    grid.innerHTML = phq4Questions.map(q => {
      const qId = Number(q.id);
      const selectedScore = state.mhAnswers[qId];
      const cleanQuestionText = String(q.pregunta || '').replace(/^\s*\d+\.\s*/, '');

      const cells = mhPhq4Options.map(score => {
        const isChecked = selectedScore !== undefined && selectedScore !== null && Number(selectedScore) === score;
        return `<td class="prostate-choice-cell${isChecked ? ' is-selected' : ''}">
          <label class="prostate-choice-label${!isEditable ? ' is-readonly' : ''}">
            <input type="radio"
              class="prostate-choice-radio"
              name="mh_q_${qId}"
              value="${score}"
              data-mh-question="${qId}"
              ${isChecked ? 'checked' : ''}
              ${!isEditable ? 'disabled' : ''}>
          </label>
        </td>`;
      }).join('');

      return `<tr data-mh-question-row="${qId}">
        <td class="prostate-question-cell">
          <span class="prostate-q-number">${escapeHtml(q.orden)}.</span>
          <span class="prostate-q-text">${escapeHtml(cleanQuestionText)}</span>
        </td>
        ${cells}
      </tr>`;
    }).join('');

    $$('input[type="radio"][data-mh-question]', grid).forEach(radio => {
      radio.addEventListener('change', () => {
        const qId = Number(radio.dataset.mhQuestion);
        const val = Number(radio.value);
        state.mhAnswers[qId] = val;

        const row = radio.closest('tr');
        if (row) {
          $$('.prostate-choice-cell', row).forEach(td => td.classList.remove('is-selected'));
          const cell = radio.closest('.prostate-choice-cell');
          if (cell) cell.classList.add('is-selected');
        }

        recalculateMhLive();
        syncMhDirtyState();
      });
    });

    // Configurar Pregunta 5 (PHQ-TP)
    const q5TextNode = $('[data-mh-q5-text]');
    const q5Box = $('[data-mh-phqtp-box]');
    if (q5Question && q5TextNode) {
      q5TextNode.textContent = q5Question.pregunta;
    }
    if (q5Box) {
      const q5Id = q5Question ? Number(q5Question.id) : 5;
      const q5Val = state.mhAnswers[q5Id];
      $$('input[type="radio"][data-mh-q5-radio]', q5Box).forEach(radio => {
        const rVal = Number(radio.value);
        const isChecked = q5Val !== null && q5Val !== undefined && Number(q5Val) === rVal;
        radio.checked = isChecked;
        radio.disabled = !isEditable;
        const label = radio.closest('.mh-phqtp-choice');
        if (label) {
          label.classList.toggle('is-selected', isChecked);
          label.classList.toggle('is-readonly', !isEditable);
        }
        radio.onchange = () => {
          state.mhAnswers[q5Id] = Number(radio.value);
          $$('.mh-phqtp-choice', q5Box).forEach(lbl => lbl.classList.remove('is-selected'));
          const parentLbl = radio.closest('.mh-phqtp-choice');
          if (parentLbl) parentLbl.classList.add('is-selected');
          recalculateMhLive();
          syncMhDirtyState();
        };
      });
    }
  }

  function applyMhStateToModal(data) {
    state.mhPatient = data.patient;
    state.mhQuestions = data.questions || [];
    state.mhRecord = data.record || null;
    state.mhAnswers = {};
    state.mhOrigAnswers = {};

    const rawAnswers = data.answers || {};
    state.mhQuestions.forEach(q => {
      const qId = Number(q.id);
      if (Object.prototype.hasOwnProperty.call(rawAnswers, qId) || Object.prototype.hasOwnProperty.call(rawAnswers, String(qId))) {
        const val = Number(rawAnswers[qId] ?? rawAnswers[String(qId)]);
        state.mhAnswers[qId] = val;
        state.mhOrigAnswers[qId] = val;
      } else {
        state.mhAnswers[qId] = null;
        state.mhOrigAnswers[qId] = null;
      }
    });

    state.mhEditMode = false;
    state.mhIsNewMode = false;
    hasMhUnsavedChanges = false;

    const fechaVal = cleanIsoDate(state.mhRecord?.fecha) || today();
    state.mhOrigFecha = fechaVal;

    const patNameNode = $('[data-mh-patient-name]');
    const patNssNode = $('[data-mh-patient-nss]');
    if (patNameNode) patNameNode.textContent = patientLabel(data.patient);
    if (patNssNode) patNssNode.textContent = data.patient.identidad || '';

    const dateInput = $('[data-mh-date]');
    if (dateInput) dateInput.value = fechaVal;

    renderMentalHealthGrid();
    updateMhEditUI();
    recalculateMhLive();
    syncMhDirtyState();
  }

  function openNewMentalHealthModal(baseData, trigger) {
    showMhConfirmDialog(false);
    showMhFeedback('', '');
    applyMhStateToModal({
      patient: baseData.patient,
      questions: baseData.questions || [],
      record: null,
      answers: {}
    });
    state.mhIsNewMode = true;
    state.mhEditMode = false;
    updateMhEditUI();
    renderMentalHealthGrid();
    openModal('mental-health', trigger);
  }

  async function loadMentalHealthRecord(ref, dcId, trigger) {
    try {
      const data = await get({ action: 'get_mental_health_questionnaire', ref, id_dc_salud_mental: dcId });
      showMhConfirmDialog(false);
      showMhFeedback('', '');
      applyMhStateToModal(data);
      openModal('mental-health', trigger);
    } catch (error) {
      toast(error.message);
    }
  }

  async function openMentalHealth(ref, trigger) {
    try {
      const data = await get({ action: 'get_mental_health_questionnaire', ref });
      const history = data.history || [];

      if (history.length === 0) {
        openNewMentalHealthModal(data, trigger);
        return;
      }

      openHistorySelector({
        title: 'CUESTIONARIO DE SALUD MENTAL',
        patient: data.patient,
        history,
        newButtonLabel: '+ Nueva Evaluación',
        onSelectRecord: item => loadMentalHealthRecord(ref, item.id, trigger),
        onCreateNew: () => openNewMentalHealthModal(data, trigger),
        trigger
      });
    } catch (error) {
      toast(error.message);
    }
  }

  function enableMhEdit() {
    state.mhEditMode = true;
    state.mhIsNewMode = false;
    showMhFeedback('', '');
    updateMhEditUI();
    renderMentalHealthGrid();
  }

  async function saveMentalHealth() {
    const btn = $('[data-save-mh]');
    showMhFeedback('', '');

    const dateInput = $('[data-mh-date]');
    const fecha = cleanIsoDate(dateInput?.value || '');

    if (!fecha) {
      const msg = 'Selecciona una fecha de evaluación válida.';
      showMhFeedback(msg, 'error');
      toast(msg);
      return;
    }

    const answersPayload = {};
    for (const q of state.mhQuestions) {
      const qId = Number(q.id);
      const val = state.mhAnswers[qId];
      if (val === null || val === undefined || val === '') {
        const msg = `Por favor responde la pregunta ${q.orden} antes de guardar.`;
        showMhFeedback(msg, 'error');
        toast(msg);
        return;
      }
      answersPayload[qId] = Number(val);
    }

    if (btn) btn.disabled = true;
    try {
      const form = new FormData();
      form.set('patient_ref', state.mhPatient.public_id);
      form.set('fecha', fecha);
      form.set('respuestas', JSON.stringify(answersPayload));

      if (!state.mhIsNewMode && state.mhRecord && state.mhRecord.id) {
        form.set('id_dc_salud_mental', String(state.mhRecord.id));
      }

      const data = await post('save_mental_health_questionnaire', form);
      applyMhStateToModal(data);

      const successMsg = data.message || 'CUESTIONARIO DE SALUD MENTAL GUARDADO CORRECTAMENTE.';
      showMhFeedback(`✓ ${successMsg}`, 'success');
      toast(successMsg);
      await loadRows();
    } catch (error) {
      showMhFeedback(error.message, 'error');
      toast(error.message);
      if (btn) btn.disabled = false;
    }
  }

  function printMentalHealthDirect() {
    const ref = state.mhPatient?.public_id;
    if (!ref) return;
    const url = new URL('index.php', window.location.href);
    url.searchParams.set('r', 'pdf_salud_mental');
    url.searchParams.set('ref', ref);
    if (state.mhRecord && state.mhRecord.id) {
      url.searchParams.set('id_dc_salud_mental', String(state.mhRecord.id));
    }
    window.open(url.toString(), '_blank', 'noopener');
  }

  // ==========================================================================
  // IMPRESIÓN DE FORMATOS (Modal 📄)
  // ==========================================================================
  function openPrint(ref, trigger, patientRef = '', hasProstate = false, hasMentalHealth = false) {
    if (!ref && !hasProstate && !hasMentalHealth && !patientRef) {
      toast('Esta fila no tiene formatos registrados para imprimir.');
      return;
    }
    state.printRef = ref || '';
    state.printPatientRef = patientRef || '';
    state.printHasAttention = Boolean(ref);
    state.printHasProstate = Boolean(hasProstate);
    state.printHasMentalHealth = Boolean(hasMentalHealth);

    const modal = modalElement('print');
    if (modal) {
      $$('[data-print]', modal).forEach(btn => {
        const type = btn.dataset.print;
        if (type === 'prostata' || type === 'colon' || type === 'salud_mental') {
          btn.disabled = false;
        } else {
          btn.disabled = !state.printHasAttention;
        }
      });
    }
    openModal('print', trigger);
  }

  function printDocument(type) {
    if (type === 'colon') {
      const ref = state.printPatientRef || state.printRef;
      closeModal(modalElement('print'));
      if (ref) {
        openColon(ref, null);
      }
      return;
    }
    if (type === 'prostata') {
      const ref = state.printPatientRef || state.printRef;
      if (!ref) return;
      const url = new URL('index.php', window.location.href);
      url.searchParams.set('r', 'pdf_prostata');
      url.searchParams.set('ref', ref);
      window.open(url.toString(), '_blank', 'noopener');
      closeModal(modalElement('print'));
      return;
    }
    if (type === 'salud_mental') {
      const ref = state.printPatientRef || state.printRef;
      if (!ref) return;
      const url = new URL('index.php', window.location.href);
      url.searchParams.set('r', 'pdf_salud_mental');
      url.searchParams.set('ref', ref);
      window.open(url.toString(), '_blank', 'noopener');
      closeModal(modalElement('print'));
      return;
    }
    if (!state.printRef) {
      toast('Este paciente aún no tiene una atención registrada para este formato.');
      return;
    }
    const route = type === 'censo' ? 'pdf_censo' : type === 'integral' ? 'pdf_integral' : 'pdf_both';
    const url = new URL('index.php', window.location.href);
    url.searchParams.set('r', route);
    url.searchParams.set('ref', state.printRef);
    window.open(url.toString(), '_blank', 'noopener');
    closeModal(modalElement('print'));
  }

  // ==========================================================================
  // EVENT LISTENERS & INICIALIZACIÓN
  // ==========================================================================
  const filterDateInput = $('[data-filter-date]');
  const filterDateEndInput = $('[data-filter-date-end]');
  const filterQueryInput = $('[data-filter-query]');

  // No forzar la fecha actual por defecto para mostrar todos los pacientes al iniciar
  if (filterDateInput) filterDateInput.value = '';
  if (filterDateEndInput) filterDateEndInput.value = '';

  const scheduleLoadRows = (delay = 260) => {
    clearTimeout(filterDebounceTimer);
    if (delay <= 0) {
      loadRows();
      return;
    }
    filterDebounceTimer = setTimeout(() => {
      loadRows();
    }, delay);
  };

  [filterDateInput, filterDateEndInput].filter(Boolean).forEach(dateCtrl => {
    dateCtrl.addEventListener('change', () => scheduleLoadRows(0));
    dateCtrl.addEventListener('search', () => scheduleLoadRows(0));
    dateCtrl.addEventListener('input', () => {
      const raw = dateCtrl.value.trim();
      if (raw === '' || cleanIsoDate(raw) !== '') {
        scheduleLoadRows(120);
      }
    });
  });

  if (filterQueryInput) {
    filterQueryInput.addEventListener('input', () => {
      // Si se vació el campo de búsqueda, recargar de inmediato el universo completo
      if (filterQueryInput.value.trim() === '') {
        scheduleLoadRows(0);
      } else {
        scheduleLoadRows(260);
      }
    });
    // Evento 'search' nativo al presionar la 'x' de limpiar o Enter en input[type="search"]
    filterQueryInput.addEventListener('search', () => scheduleLoadRows(0));
    filterQueryInput.addEventListener('change', () => scheduleLoadRows(0));
  }

  $('#patient-filters').addEventListener('submit', event => {
    event.preventDefault();
    scheduleLoadRows(0);
  });

  $('#patient-filters').addEventListener('reset', () => {
    setTimeout(() => scheduleLoadRows(0), 0);
  });

  $('[data-open-register]').addEventListener('click', event => openRegister(event.currentTarget));
  $('#patient-form').addEventListener('submit', savePatient);
  $('#attention-form').addEventListener('submit', saveAttention);
  $('[data-attention-edit-btn]')?.addEventListener('click', enableAttentionEdit);
  $('#card-edit-form').addEventListener('submit', saveCardEdit);
  $('[data-card-edit-btn]').addEventListener('click', () => setCardMode(true));
  $('[data-card-cancel-edit-btn]').addEventListener('click', () => setCardMode(false));
  $('[data-save-vaccines]').addEventListener('click', saveVaccines);
  $('[data-toggle-vaccine-edit]')?.addEventListener('click', () => toggleVaccineEditMode());
  $('[data-vaccine-confirm-stay]')?.addEventListener('click', () => showVaccineConfirmDialog(false));
  $('[data-vaccine-confirm-discard]')?.addEventListener('click', () => closeModal(modalElement('vaccines'), true));
  $('[data-save-prostate]')?.addEventListener('click', saveProstate);
  $('[data-prostate-edit]')?.addEventListener('click', enableProstateEdit);
  $('[data-prostate-print]')?.addEventListener('click', printProstateDirect);
  $('[data-prostate-new]')?.addEventListener('click', startProstateNew);
  $('[data-prostate-confirm-stay]')?.addEventListener('click', () => showProstateConfirmDialog(false));
  $('[data-prostate-confirm-discard]')?.addEventListener('click', () => closeModal(modalElement('prostate'), true));
  $('[data-prostate-date]')?.addEventListener('change', syncProstateDirtyState);
  $('[data-prostate-date]')?.addEventListener('input', syncProstateDirtyState);
  $('[data-prostate-risk-group]')?.addEventListener('change', syncProstateDirtyState);
  $('[data-save-mh]')?.addEventListener('click', saveMentalHealth);
  $('[data-mh-edit]')?.addEventListener('click', enableMhEdit);
  $('[data-mh-print]')?.addEventListener('click', printMentalHealthDirect);
  $('[data-mh-confirm-stay]')?.addEventListener('click', () => showMhConfirmDialog(false));
  $('[data-mh-confirm-discard]')?.addEventListener('click', () => closeModal(modalElement('mental-health'), true));
  $('[data-mh-date]')?.addEventListener('change', syncMhDirtyState);
  $('[data-mh-date]')?.addEventListener('input', syncMhDirtyState);
  $('[data-no-companion]').addEventListener('change', toggleCompanion);
  $('#colon-form')?.addEventListener('submit', printColonSubmit);

  // Cierre de modales con botón × o cancelar
  $$('[data-modal]').forEach(layer => {
    layer.addEventListener('click', event => {
      if (event.target === layer) closeModal(layer);
    });
    $$('[data-close-modal]', layer).forEach(button => {
      button.addEventListener('click', () => closeModal(layer));
    });
  });

  // Atajo de teclado ESC para cerrar modales flotantes (Punto 7 requerimiento)
  document.addEventListener('keydown', event => {
    if (event.key === 'Escape' && state.activeModal) {
      const confirmDialog = $('[data-vaccine-confirm-dialog]');
      if (confirmDialog && !confirmDialog.hidden) {
        showVaccineConfirmDialog(false);
        return;
      }
      const prostateConfirmDialog = $('[data-prostate-confirm-dialog]');
      if (prostateConfirmDialog && !prostateConfirmDialog.hidden) {
        showProstateConfirmDialog(false);
        return;
      }
      const mhConfirmDialog = $('[data-mh-confirm-dialog]');
      if (mhConfirmDialog && !mhConfirmDialog.hidden) {
        showMhConfirmDialog(false);
        return;
      }
      closeModal(modalElement(state.activeModal));
    }
  });

  $$('[data-print]').forEach(btn => btn.addEventListener('click', () => printDocument(btn.dataset.print)));

  window.addEventListener('srae:context-updated', () => {
    state.context = null;
    loadContext().catch(() => {});
  });

  // Carga inicial
  loadContext().then(loadRows).catch(error => {
    $('[data-attention-rows]').innerHTML = `<tr><td colspan="6" style="text-align:center; padding: 24px; color: #c62828;">${escapeHtml(error.message)}</td></tr>`;
  });
})();
