(() => {
  const app = document.querySelector('[data-config-app]');
  if (!app) return;

  const apiUrl = app.dataset.apiUrl;
  const csrf = document.querySelector('meta[name="csrf-token"]')?.content || '';
  const state = {
    catalogs: { roles: [], turnos: [], unidades: [] },
    users: [],
    selectedUnitId: null,
    selectedUser: null,
    activeModal: null
  };

  const $ = (selector, parent = document) => parent.querySelector(selector);
  const $$ = (selector, parent = document) => [...parent.querySelectorAll(selector)];
  const escapeHtml = str => String(str ?? '').replace(/[&<>'"]/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', "'": '&#039;', '"': '&quot;' }[c]));

  async function get(params) {
    const url = new URL(apiUrl, window.location.href);
    Object.entries(params).forEach(([k, v]) => url.searchParams.set(k, v ?? ''));
    const res = await fetch(url, { headers: { Accept: 'application/json' }, credentials: 'same-origin' });
    const data = await res.json().catch(() => ({ ok: false, message: 'Respuesta inválida del servidor.' }));
    if (!res.ok || !data.ok) throw new Error(data.message || 'Error en consulta.');
    return data;
  }

  async function post(action, formOrData) {
    let body;
    if (formOrData instanceof FormData) {
      body = formOrData;
    } else if (formOrData instanceof HTMLFormElement) {
      body = new FormData(formOrData);
    } else {
      body = new FormData();
      Object.entries(formOrData || {}).forEach(([k, v]) => body.set(k, v ?? ''));
    }
    body.set('action', action);
    body.set('_csrf', csrf);

    const res = await fetch(apiUrl, { method: 'POST', body, headers: { Accept: 'application/json' }, credentials: 'same-origin' });
    const data = await res.json().catch(() => ({ ok: false, message: 'Respuesta inválida del servidor.' }));
    if (!res.ok || !data.ok) throw new Error(data.message || 'Error al guardar.');
    return data;
  }

  function toast(msg) {
    const region = $('[data-toast-region]');
    if (!region) return;
    const item = document.createElement('div');
    item.className = 'toast';
    item.innerHTML = `<span>${escapeHtml(msg)}</span>`;
    region.appendChild(item);
    setTimeout(() => { item.style.opacity = '0'; setTimeout(() => item.remove(), 200); }, 4000);
  }

  function modalElement(name) { return $(`[data-modal="${name}"]`); }

  function openModal(name) {
    const layer = modalElement(name);
    if (!layer) return;
    state.activeModal = name;
    layer.hidden = false;
    document.body.style.overflow = 'hidden';
    const card = $('.modal-card', layer);
    if (card) card.focus();
  }

  function closeModal(layer = modalElement(state.activeModal)) {
    if (!layer || layer.hidden) return;
    layer.hidden = true;
    state.activeModal = null;
    document.body.style.overflow = '';
  }

  // ==========================================================================
  // CARGA DE CATÁLOGOS BASE
  // ==========================================================================
  async function loadCatalogs() {
    try {
      const data = await get({ action: 'catalogs' });
      state.catalogs = data;
      populateSelects();
    } catch (e) {
      console.error('Error cargando catálogos:', e);
    }
  }

  function updateConsultoriosSelect(unitId, selectElem, selectedConsultorioId = 0) {
    if (!selectElem) return;
    const uid = parseInt(unitId, 10) || 0;
    const matching = (state.catalogs.consultorios || []).filter(c => parseInt(c.unidad_id, 10) === uid);
    let options = '<option value="0">SIN ASIGNACIÓN</option>';
    if (matching.length > 0) {
      options += matching.map(c => `<option value="${c.consultorio_id}">${escapeHtml(c.nombre_descriptivo || c.clave)}</option>`).join('');
    }
    selectElem.innerHTML = options;
    selectElem.value = String(selectedConsultorioId || 0);
  }

  function populateSelects() {
    const unitOptions = '<option value="0">SIN ASIGNACIÓN</option>' +
      state.catalogs.unidades.map(u => `<option value="${u.unidad_id}">${escapeHtml(u.clave || u.nombre)}</option>`).join('');

    const roleOptions = state.catalogs.roles.map(r => `<option value="${r.rol_id}">${escapeHtml(r.nombre)}</option>`).join('');

    const regUnit = $('[data-user-unit-select]');
    if (regUnit) {
      regUnit.innerHTML = unitOptions;
      regUnit.onchange = () => updateConsultoriosSelect(regUnit.value, $('[data-user-consultorio-select]'), 0);
      updateConsultoriosSelect(regUnit.value, $('[data-user-consultorio-select]'), 0);
    }

    const editUnit = $('[data-edit-user-unit-select]');
    if (editUnit) {
      editUnit.innerHTML = unitOptions;
      editUnit.onchange = () => updateConsultoriosSelect(editUnit.value, $('[data-edit-user-consultorio-select]'), 0);
    }

    const regRole = $('[data-user-role-select]');
    if (regRole) regRole.innerHTML = roleOptions;

    const editRole = $('[data-edit-user-role-select]');
    if (editRole) editRole.innerHTML = roleOptions;

    const consUnit = $('[data-consult-unit-select]');
    if (consUnit) {
      consUnit.innerHTML = state.catalogs.unidades.map(u => `<option value="${u.unidad_id}">${escapeHtml(u.clave || u.nombre)}</option>`).join('');
    }

    const consShift = $('[data-consult-shift-select]');
    if (consShift) {
      consShift.innerHTML = state.catalogs.turnos.map(t => `<option value="${t.turno_id}">${escapeHtml(t.nombre)} (${escapeHtml(t.clave)})</option>`).join('');
    }
  }

  // ==========================================================================
  // SECCIÓN 1: USUARIOS
  // ==========================================================================
  async function loadUsers(query = '') {
    const tbody = $('[data-users-rows]');
    try {
      const data = await get({ action: 'users_list', q: query });
      state.users = data.users;

      if (!data.users.length) {
        tbody.innerHTML = '<tr><td colspan="5" style="text-align: center; padding: 24px; color: var(--srae-grey);">No se encontraron usuarios registrados.</td></tr>';
        return;
      }

      tbody.innerHTML = data.users.map(u => {
        const statusClass = u.activo ? 'active' : 'inactive';
        const statusLabel = u.activo ? 'ACTIVO' : 'INACTIVO';
        const unitSiglas = u.unidad_siglas || (u.unidad_nombre ? escapeHtml(u.unidad_nombre) : '—');
        const consultLabel = u.consultorio_nombre || u.consultorio_clave || '—';

        const prefixDisplay = u.prefijo ? `${u.prefijo} ` : '';

        return `<tr data-user-id="${u.usuario_id}" tabindex="0">
          <td><strong>${escapeHtml(u.matricula)}</strong></td>
          <td>${escapeHtml(prefixDisplay + u.nombre)}</td>
          <td>${escapeHtml(unitSiglas)}</td>
          <td>${escapeHtml(consultLabel)}</td>
          <td style="text-align: center;">
            <button type="button" class="user-status-switch ${statusClass}" data-toggle-user="${u.usuario_id}">
              ${statusLabel}
            </button>
          </td>
        </tr>`;
      }).join('');

      // Clic en fila abre tarjeta de usuario
      $$('tr[data-user-id]', tbody).forEach(row => {
        row.addEventListener('click', () => {
          const user = state.users.find(u => String(u.usuario_id) === row.dataset.userId);
          if (user) openUserCard(user);
        });
      });

      // Clic en switch de estado
      $$('[data-toggle-user]', tbody).forEach(btn => {
        btn.addEventListener('click', async event => {
          event.stopPropagation();
          const userId = btn.dataset.toggleUser;
          btn.disabled = true;
          try {
            const res = await post('user_toggle', { usuario_id: userId });
            toast(res.message);
            await loadUsers($('[data-user-search-input]').value.trim());
          } catch (err) {
            toast(err.message);
          } finally {
            btn.disabled = false;
          }
        });
      });

    } catch (e) {
      tbody.innerHTML = `<tr><td colspan="5" style="text-align: center; padding: 24px; color: #c62828;">${escapeHtml(e.message)}</td></tr>`;
    }
  }

  // Abrir Modal de Registro de Usuario
  function openRegisterUser() {
    const form = $('#reg-user-form');
    form.reset();
    const prefixSelect = $('[data-user-prefix-select]', form);
    if (prefixSelect) prefixSelect.value = 'L.E.';
    const err = $('[data-user-error]', form);
    if (err) err.hidden = true;
    const unitSelect = $('[data-user-unit-select]', form);
    updateConsultoriosSelect(unitSelect?.value || 0, $('[data-user-consultorio-select]', form), 0);
    openModal('register-user');
  }

  async function saveRegisterUser(e) {
    e.preventDefault();
    const form = e.currentTarget;
    const btn = $('button[type="submit"]', form);
    btn.disabled = true;
    try {
      const res = await post('user_save', form);
      closeModal(modalElement('register-user'));
      await loadUsers();
      toast(res.message);
    } catch (err) {
      const errNode = $('[data-user-error]', form);
      if (errNode) { errNode.textContent = err.message; errNode.hidden = false; }
    } finally {
      btn.disabled = false;
    }
  }

  // Tarjeta de Usuario & Edición In Situ (Página 3 PDF)
  function openUserCard(user) {
    state.selectedUser = user;
    setUserCardMode(false);
    const prefixNode = $('[data-card-user-prefix]');
    if (prefixNode) prefixNode.textContent = user.prefijo || 'L.E.';
    $('[data-card-user-name]').textContent = user.nombre;
    $('[data-card-user-mat]').textContent = user.matricula;
    $('[data-card-user-unit]').textContent = user.unidad_siglas || user.unidad_nombre || 'Sin asignación';
    $('[data-card-user-clinic]').textContent = user.consultorio_nombre || user.consultorio_clave || 'Sin asignación';
    openModal('card-user');
  }

  function setUserCardMode(isEditing) {
    const view = $('[data-user-card-view]');
    const edit = $('[data-user-card-edit]');
    const title = $('[data-card-user-title]');

    if (isEditing) {
      title.textContent = 'EDITAR DATOS DE USUARIO';
      view.hidden = true;
      edit.hidden = false;
      const u = state.selectedUser;
      const f = $('#edit-user-form');
      f.elements.namedItem('usuario_id').value = u.usuario_id;
      if (f.elements.namedItem('prefijo')) f.elements.namedItem('prefijo').value = u.prefijo || 'L.E.';
      f.elements.namedItem('nombre').value = u.nombre;
      f.elements.namedItem('matricula').value = u.matricula;
      const userUnitId = u.unidad_id || '0';
      if (f.elements.namedItem('unidad_id')) f.elements.namedItem('unidad_id').value = userUnitId;
      const editConsult = $('[data-edit-user-consultorio-select]', f);
      updateConsultoriosSelect(userUnitId, editConsult, u.consultorio_id || 0);
      if (f.elements.namedItem('rol_id')) f.elements.namedItem('rol_id').value = u.rol_id || '1';
      f.elements.namedItem('password').value = '';
      f.elements.namedItem('password_confirm').value = '';
    } else {
      title.textContent = 'TARJETA DE USUARIO';
      view.hidden = false;
      edit.hidden = true;
    }
  }

  async function saveEditUser(e) {
    e.preventDefault();
    const form = e.currentTarget;
    const btn = $('button[type="submit"]', form);
    btn.disabled = true;
    try {
      const res = await post('user_save', form);
      toast(res.message);
      await loadUsers($('[data-user-search-input]').value.trim());
      const updated = state.users.find(u => String(u.usuario_id) === String(state.selectedUser.usuario_id));
      if (updated) openUserCard(updated);
      else closeModal(modalElement('card-user'));
    } catch (err) {
      const errNode = $('[data-edit-user-error]', form);
      if (errNode) { errNode.textContent = err.message; errNode.hidden = false; }
    } finally {
      btn.disabled = false;
    }
  }

  // ==========================================================================
  // SECCIÓN 2: UNIDADES (Ordenadas por Número)
  // ==========================================================================
  async function loadUnits() {
    const container = $('[data-units-container]');
    try {
      const data = await get({ action: 'units_list' });
      state.catalogs.unidades = data.units;

      if (!data.units.length) {
        container.innerHTML = '<div style="opacity: 0.8;">No hay unidades registradas.</div>';
        return;
      }

      container.innerHTML = data.units.map(u => {
        const isSelected = String(u.unidad_id) === String(state.selectedUnitId);
        const iconSvg = '<svg width="14" height="14" viewBox="0 0 24 24" fill="currentColor"><path d="M19 2H5a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V4a2 2 0 0 0-2-2zm-8 4h2v2h-2V6zm0 4h2v2h-2v-2zm0 4h2v2h-2v-2zm-4-8h2v2H7V6zm0 4h2v2H7v-2zm0 4h2v2H7v-2zm10 4h-2v-2h2v2zm0-4h-2v-2h2v2zm0-4h-2V6h2v2z"/></svg>';
        return `<button type="button" class="unit-tile-badge ${isSelected ? 'selected' : ''}" data-unit-tile="${u.unidad_id}">
          ${iconSvg} <span>${escapeHtml(u.clave || u.nombre)}</span>
        </button>`;
      }).join('');

      // Auto-seleccionar la primera unidad si no hay ninguna seleccionada
      if (!state.selectedUnitId && data.units.length > 0) {
        selectUnit(data.units[0].unidad_id);
      }

      $$('[data-unit-tile]', container).forEach(btn => {
        btn.addEventListener('click', () => selectUnit(btn.dataset.unitTile));
      });
    } catch (e) {
      container.innerHTML = `<div style="color: #ffcdd2;">${escapeHtml(e.message)}</div>`;
    }
  }

  function selectUnit(unitId) {
    state.selectedUnitId = unitId;
    $$('[data-unit-tile]').forEach(btn => {
      btn.classList.toggle('selected', btn.dataset.unitTile === String(unitId));
    });
    loadConsultorios(unitId);
  }

  // Modales de Unidad (Registro y Edición)
  function openAddUnit() {
    const form = $('#unit-form');
    form.reset();
    form.elements.namedItem('unidad_id').value = '';
    $('[data-unit-modal-title]').textContent = 'REGISTRO DE UNIDAD';
    const err = $('[data-unit-form-error]', form);
    if (err) err.hidden = true;
    openModal('unit');
  }

  function openEditSelectedUnit() {
    if (!state.selectedUnitId) {
      toast('Selecciona una unidad para editar.');
      return;
    }
    const unit = state.catalogs.unidades.find(u => String(u.unidad_id) === String(state.selectedUnitId));
    if (!unit) return;

    const form = $('#unit-form');
    form.reset();
    form.elements.namedItem('unidad_id').value = unit.unidad_id;
    form.elements.namedItem('nombre').value = unit.nombre;
    form.elements.namedItem('siglas').value = unit.clave || '';
    $('[data-unit-modal-title]').textContent = 'DATOS DE UNIDAD';
    const err = $('[data-unit-form-error]', form);
    if (err) err.hidden = true;
    openModal('unit');
  }

  async function saveUnit(e) {
    e.preventDefault();
    const form = e.currentTarget;
    const btn = $('button[type="submit"]', form);
    btn.disabled = true;
    try {
      const res = await post('unit_save', form);
      closeModal(modalElement('unit'));
      await loadUnits();
      toast(res.message);
    } catch (err) {
      const errNode = $('[data-unit-form-error]', form);
      if (errNode) { errNode.textContent = err.message; errNode.hidden = false; }
    } finally {
      btn.disabled = false;
    }
  }

  // ==========================================================================
  // SECCIÓN 3: CONSULTORIOS (Switches Interactivos)
  // ==========================================================================
  async function loadConsultorios(unitId) {
    const container = $('[data-consultorios-container]');
    if (!unitId) {
      container.innerHTML = '<div style="opacity: 0.8;">Selecciona una unidad para ver sus consultorios.</div>';
      return;
    }

    try {
      const data = await get({ action: 'consultorios_by_unit', unidad_id: unitId });

      if (!data.consultorios.length) {
        container.innerHTML = '<div style="opacity: 0.8;">No hay consultorios configurados en esta unidad. Presiona AGREGAR para registrar uno.</div>';
        return;
      }

      // Agrupar consultorios por turno para formar filas (1-M a 7-M, 1-V a 7-V)
      const byShift = {};
      data.consultorios.forEach(c => {
        const shiftName = c.turno_nombre || 'General';
        if (!byShift[shiftName]) byShift[shiftName] = [];
        byShift[shiftName].push(c);
      });

      container.innerHTML = Object.entries(byShift).map(([shiftName, items]) => {
        const pillsHtml = items.map(c => {
          const statusClass = c.activo ? 'active' : 'inactive';
          return `<button type="button" class="consultorio-switch-pill ${statusClass}" 
                          data-consult-id="${c.consultorio_id}" 
                          data-shift-id="${c.turno_id}"
                          title="${escapeHtml(c.consultorio_nombre)} - ${escapeHtml(c.turno_nombre)} (${c.activo ? 'Activo' : 'Inactivo'})">
            ${escapeHtml(c.label)}
          </button>`;
        }).join('');

        return `<div class="consultorios-shift-row">${pillsHtml}</div>`;
      }).join('');

      // Clic en switch de consultorio
      $$('[data-consult-id]', container).forEach(btn => {
        btn.addEventListener('click', async () => {
          const consultId = btn.dataset.consultId;
          const shiftId = btn.dataset.shiftId;
          btn.style.opacity = '0.5';
          try {
            const res = await post('consultorio_toggle', {
              unidad_id: state.selectedUnitId,
              consultorio_id: consultId,
              turno_id: shiftId
            });
            btn.classList.toggle('active', res.activo);
            await loadCatalogs();
            btn.classList.toggle('inactive', !res.activo);
          } catch (err) {
            toast(err.message);
          } finally {
            btn.style.opacity = '1';
          }
        });
      });

    } catch (e) {
      container.innerHTML = `<div style="color: #ffcdd2;">${escapeHtml(e.message)}</div>`;
    }
  }

  // Modal Registro de Consultorio (Página 4 PDF)
  function openAddConsultorio() {
    const form = $('#consultorio-form');
    form.reset();
    if (state.selectedUnitId && form.elements.namedItem('unidad_id')) {
      form.elements.namedItem('unidad_id').value = state.selectedUnitId;
    }
    const err = $('[data-consult-form-error]', form);
    if (err) err.hidden = true;
    openModal('consultorio');
  }

  async function saveConsultorio(e) {
    e.preventDefault();
    const form = e.currentTarget;
    const btn = $('button[type="submit"]', form);
    btn.disabled = true;
    try {
      const res = await post('consultorio_save', form);
      closeModal(modalElement('consultorio'));
      await loadConsultorios(state.selectedUnitId);
      await loadCatalogs();
      toast(res.message);
    } catch (err) {
      const errNode = $('[data-consult-form-error]', form);
      if (errNode) { errNode.textContent = err.message; errNode.hidden = false; }
    } finally {
      btn.disabled = false;
    }
  }

  // ==========================================================================
  // INICIALIZACIÓN Y EVENT LISTENERS
  // ==========================================================================
  $('#user-search-form').addEventListener('submit', e => {
    e.preventDefault();
    loadUsers($('[data-user-search-input]').value.trim());
  });

  $('[data-open-register-user]').addEventListener('click', openRegisterUser);
  $('#reg-user-form').addEventListener('submit', saveRegisterUser);
  $('[data-btn-edit-user]').addEventListener('click', () => setUserCardMode(true));
  $('[data-cancel-edit-user]').addEventListener('click', () => setUserCardMode(false));
  $('#edit-user-form').addEventListener('submit', saveEditUser);

  $('[data-add-unit]').addEventListener('click', openAddUnit);
  $('[data-edit-selected-unit]').addEventListener('click', openEditSelectedUnit);
  $('#unit-form').addEventListener('submit', saveUnit);

  $('[data-add-consultorio]').addEventListener('click', openAddConsultorio);
  $('#consultorio-form').addEventListener('submit', saveConsultorio);

  // Cierre de modales
  $$('[data-modal]').forEach(layer => {
    layer.addEventListener('click', e => {
      if (e.target === layer) closeModal(layer);
    });
    $$('[data-close-modal]', layer).forEach(btn => {
      btn.addEventListener('click', () => closeModal(layer));
    });
  });

  document.addEventListener('keydown', e => {
    if (e.key === 'Escape' && state.activeModal) {
      closeModal(modalElement(state.activeModal));
    }
  });

  // Carga inicial secuencial
  loadCatalogs().then(() => {
    loadUsers();
    loadUnits();
  });
})();


