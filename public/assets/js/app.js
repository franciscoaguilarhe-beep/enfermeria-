document.addEventListener('DOMContentLoaded', () => {
  document.querySelectorAll('[data-uppercase]').forEach(field => {
    field.addEventListener('input', () => { field.value = field.value.toLocaleUpperCase('es-MX'); });
    field.value = field.value.toLocaleUpperCase('es-MX');
  });

  document.querySelectorAll('[data-staff-lookup]').forEach(input => {
    let timer;
    const result = input.parentElement.querySelector('[data-staff-result]');
    input.addEventListener('input', () => {
      clearTimeout(timer);
      result.textContent = 'Consultando matrícula…';
      if (!input.value.trim()) {
        result.textContent = 'Se validará contra personal activo antes de guardar.';
        return;
      }
      timer = setTimeout(async () => {
        try {
          const url = new URL(window.location.href);
          url.search = '';
          url.searchParams.set('r', 'staff_lookup');
          url.searchParams.set('matricula', input.value.trim());
          const response = await fetch(url);
          const data = await response.json();
          result.textContent = data.ok ? `Matrícula válida: ${data.staff.nombre}` : 'Matrícula no registrada o inactiva.';
          result.className = data.ok ? 'valid' : 'invalid';
        } catch (e) {
          result.textContent = 'No fue posible consultar la matrícula.';
        }
      }, 250);
    });
  });

  const nss = document.querySelector('input[name="nss"][data-dv-check]') || document.querySelector('input[name="nss"]');
  const dv = document.querySelector('[data-dv-check]');
  const dvResult = document.querySelector('[data-dv-result]');
  let dvTimer;
  if (nss && dv && dvResult) {
    const check = async () => {
      clearTimeout(dvTimer);
      if (!/^\d{10}$/.test(nss.value)) {
        dvResult.textContent = 'Captura 10 dígitos para calcular una ayuda.';
        return;
      }
      dvTimer = setTimeout(async () => {
        const url = new URL(window.location.href);
        url.search = '';
        url.searchParams.set('r', 'dv_lookup');
        url.searchParams.set('nss', nss.value);
        const data = await (await fetch(url)).json();
        if (!data.dv) {
          dvResult.textContent = 'No hay cálculo disponible.';
          return;
        }
        dvResult.textContent = dv.value && dv.value !== data.dv
          ? `DV calculado: ${data.dv}. Verifica la cartilla; se conservará el capturado.`
          : `DV calculado: ${data.dv} ✓`;
        dvResult.className = dv.value && dv.value !== data.dv ? 'invalid' : 'valid';
      }, 150);
    };
    nss.addEventListener('input', check);
    dv.addEventListener('input', check);
    check();
  }

  document.querySelectorAll('select[name="unidad_id"]').forEach(unit => {
    const consult = document.querySelector('select[name="consultorio_id"]');
    if (!consult) return;
    const filter = () => {
      [...consult.options].forEach(o => {
        if (!o.dataset.unit) { o.hidden = false; return; }
        o.hidden = unit.value !== '' && o.dataset.unit !== unit.value;
      });
    };
    unit.addEventListener('change', filter);
    filter();
  });

  // ==========================================================================
  // MODALES RÁPIDOS DE HEADER (PREFIJO/NOMBRE Y CONSULTORIO/TURNO)
  // ==========================================================================
  const navUserWrap = document.querySelector('.nav-user[data-profile-api-url]');
  const profileTrigger = document.querySelector('[data-header-user-name]');
  const contextTrigger = document.querySelector('[data-header-user-context]');
  if (!navUserWrap || (!profileTrigger && !contextTrigger)) return;

  const profileApiUrl = navUserWrap.dataset.profileApiUrl || 'api/patients.php';
  const csrfMeta = document.querySelector('meta[name="csrf-token"]');
  const csrfToken = csrfMeta ? csrfMeta.content : '';
  const standardPrefixes = ['L.E.', 'M.E.', 'E.G.', 'E.E.', 'AUX. ENF.', 'DR.', 'DRA.', 'C.'];

  function showQuickToast(message) {
    let region = document.querySelector('[data-toast-region]');
    if (!region) {
      region = document.createElement('div');
      region.className = 'toast-region';
      region.setAttribute('aria-live', 'polite');
      region.setAttribute('data-toast-region', '');
      document.body.appendChild(region);
    }
    const item = document.createElement('div');
    item.className = 'toast';
    item.textContent = message;
    region.appendChild(item);
    setTimeout(() => item.remove(), 3800);
  }

  function ensureHeaderModals() {
    let profileLayer = document.getElementById('srae-quick-profile-modal');
    if (!profileLayer) {
      profileLayer = document.createElement('div');
      profileLayer.id = 'srae-quick-profile-modal';
      profileLayer.className = 'modal-layer';
      profileLayer.hidden = true;
      profileLayer.innerHTML = `
        <div class="modal-card modal-header-quick" role="dialog" aria-modal="true" aria-labelledby="srae-qp-title" tabindex="-1">
          <div class="modal-header-line">
            <h2 id="srae-qp-title" class="modal-title">EDITAR MI PERFIL</h2>
            <button type="button" class="modal-close-btn" data-quick-close aria-label="Cerrar">×</button>
          </div>
          <form id="srae-quick-profile-form" autocomplete="off">
            <div data-quick-error style="color:#c62828; font-size:13px; font-weight:600; margin-bottom:10px;" hidden></div>
            <div class="field-item" style="margin-bottom:12px;">
              <label for="srae-qp-prefijo">PREFIJO *</label>
              <select id="srae-qp-prefijo" name="prefijo" required></select>
            </div>
            <div class="field-item" style="margin-bottom:16px;">
              <label for="srae-qp-nombre">NOMBRE COMPLETO *</label>
              <input type="text" id="srae-qp-nombre" name="nombre" maxlength="180" required placeholder="NOMBRE COMPLETO">
            </div>
            <div class="modal-actions">
              <button type="submit" class="modal-btn prostate-save-btn">GUARDAR</button>
              <button type="button" class="modal-btn secondary" data-quick-close>CANCELAR</button>
            </div>
          </form>
        </div>
      `;
      document.body.appendChild(profileLayer);
    }

    let contextLayer = document.getElementById('srae-quick-context-modal');
    if (!contextLayer) {
      contextLayer = document.createElement('div');
      contextLayer.id = 'srae-quick-context-modal';
      contextLayer.className = 'modal-layer';
      contextLayer.hidden = true;
      contextLayer.innerHTML = `
        <div class="modal-card modal-header-quick" role="dialog" aria-modal="true" aria-labelledby="srae-qc-title" tabindex="-1">
          <div class="modal-header-line">
            <h2 id="srae-qc-title" class="modal-title">CAMBIAR CONSULTORIO Y TURNO</h2>
            <button type="button" class="modal-close-btn" data-quick-close aria-label="Cerrar">×</button>
          </div>
          <form id="srae-quick-context-form" autocomplete="off">
            <div data-quick-error style="color:#c62828; font-size:13px; font-weight:600; margin-bottom:10px;" hidden></div>
            <div class="field-item" style="margin-bottom:12px;">
              <label for="srae-qc-consultorio">CONSULTORIO *</label>
              <select id="srae-qc-consultorio" name="consultorio_id" required></select>
            </div>
            <div class="field-item" style="margin-bottom:16px;">
              <label for="srae-qc-turno">TURNO *</label>
              <select id="srae-qc-turno" name="turno_id" required></select>
            </div>
            <div class="modal-actions">
              <button type="submit" class="modal-btn prostate-save-btn">ACTUALIZAR</button>
              <button type="button" class="modal-btn secondary" data-quick-close>CANCELAR</button>
            </div>
          </form>
        </div>
      `;
      document.body.appendChild(contextLayer);
    }

    return { profileLayer, contextLayer };
  }

  const { profileLayer, contextLayer } = ensureHeaderModals();

  function closeQuickLayer(layer) {
    if (!layer) return;
    layer.hidden = true;
    document.body.style.overflow = '';
  }

  [profileLayer, contextLayer].forEach(layer => {
    layer.addEventListener('click', e => {
      if (e.target === layer) closeQuickLayer(layer);
    });
    layer.querySelectorAll('[data-quick-close]').forEach(btn => {
      btn.addEventListener('click', () => closeQuickLayer(layer));
    });
  });

  document.addEventListener('keydown', e => {
    if (e.key === 'Escape') {
      if (!profileLayer.hidden) closeQuickLayer(profileLayer);
      if (!contextLayer.hidden) closeQuickLayer(contextLayer);
    }
  });

  async function fetchProfileContext() {
    const url = new URL(profileApiUrl, window.location.href);
    url.searchParams.set('action', 'get_user_context_options');
    const resp = await fetch(url.toString(), {
      credentials: 'same-origin',
      headers: { 'X-CSRF-Token': csrfToken }
    });
    const data = await resp.json();
    if (!resp.ok || !data.ok) {
      throw new Error(data.message || 'No fue posible cargar los datos del usuario.');
    }
    return data;
  }

  const nombreInput = profileLayer.querySelector('#srae-qp-nombre');
  if (nombreInput) {
    nombreInput.addEventListener('input', () => {
      nombreInput.value = nombreInput.value.toLocaleUpperCase('es-MX');
    });
  }

  if (profileTrigger) {
    profileTrigger.addEventListener('click', async () => {
      const errBox = profileLayer.querySelector('[data-quick-error]');
      if (errBox) { errBox.hidden = true; errBox.textContent = ''; }
      try {
        const data = await fetchProfileContext();
        const u = data.user || {};
        const sel = profileLayer.querySelector('#srae-qp-prefijo');
        const currentPref = (u.prefijo || 'L.E.').trim();
        const prefixes = standardPrefixes.includes(currentPref)
          ? standardPrefixes
          : [currentPref, ...standardPrefixes];
        sel.innerHTML = '';
        prefixes.forEach(p => {
          const opt = document.createElement('option');
          opt.value = p;
          opt.textContent = p;
          if (p === currentPref) opt.selected = true;
          sel.appendChild(opt);
        });
        nombreInput.value = (u.nombre || '').toLocaleUpperCase('es-MX');
        profileLayer.hidden = false;
        document.body.style.overflow = 'hidden';
        nombreInput.focus();
      } catch (err) {
        showQuickToast(err.message);
      }
    });
  }

  const profileForm = profileLayer.querySelector('#srae-quick-profile-form');
  if (profileForm) {
    profileForm.addEventListener('submit', async e => {
      e.preventDefault();
      const errBox = profileLayer.querySelector('[data-quick-error]');
      if (errBox) { errBox.hidden = true; errBox.textContent = ''; }
      const submitBtn = profileForm.querySelector('button[type="submit"]');
      if (submitBtn) submitBtn.disabled = true;

      try {
        const fd = new FormData(profileForm);
        fd.set('action', 'update_own_profile');
        fd.set('_csrf', csrfToken);
        const resp = await fetch(profileApiUrl, {
          method: 'POST',
          credentials: 'same-origin',
          headers: { 'X-CSRF-Token': csrfToken },
          body: fd
        });
        const data = await resp.json();
        if (!resp.ok || !data.ok) {
          throw new Error(data.message || 'No fue posible actualizar el perfil.');
        }
        const u = data.user || {};
        const display = `${(u.prefijo || '').trim()} ${(u.nombre || '').trim()}`.trim();
        if (profileTrigger && display) {
          profileTrigger.textContent = display;
        }
        closeQuickLayer(profileLayer);
        showQuickToast(data.message || 'PERFIL ACTUALIZADO CORRECTAMENTE.');
      } catch (err) {
        if (errBox) {
          errBox.textContent = err.message;
          errBox.hidden = false;
        }
      } finally {
        if (submitBtn) submitBtn.disabled = false;
      }
    });
  }

  if (contextTrigger) {
    contextTrigger.addEventListener('click', async () => {
      const errBox = contextLayer.querySelector('[data-quick-error]');
      if (errBox) { errBox.hidden = true; errBox.textContent = ''; }
      try {
        const data = await fetchProfileContext();
        const u = data.user || {};
        const selCons = contextLayer.querySelector('#srae-qc-consultorio');
        const selTurno = contextLayer.querySelector('#srae-qc-turno');

        selCons.innerHTML = '';
        (data.consultorios || []).forEach(c => {
          const opt = document.createElement('option');
          opt.value = String(c.consultorio_id);
          opt.textContent = c.nombre_descriptivo || c.clave || `Consultorio ${c.consultorio_id}`;
          if (Number(c.consultorio_id) === Number(u.consultorio_id)) opt.selected = true;
          selCons.appendChild(opt);
        });

        selTurno.innerHTML = '';
        (data.turnos || []).forEach(t => {
          const opt = document.createElement('option');
          opt.value = String(t.turno_id);
          opt.textContent = t.nombre || t.clave;
          if (Number(t.turno_id) === Number(u.turno_id)) opt.selected = true;
          selTurno.appendChild(opt);
        });

        contextLayer.hidden = false;
        document.body.style.overflow = 'hidden';
        selCons.focus();
      } catch (err) {
        showQuickToast(err.message);
      }
    });
  }

  const contextForm = contextLayer.querySelector('#srae-quick-context-form');
  if (contextForm) {
    contextForm.addEventListener('submit', async e => {
      e.preventDefault();
      const errBox = contextLayer.querySelector('[data-quick-error]');
      if (errBox) { errBox.hidden = true; errBox.textContent = ''; }
      const submitBtn = contextForm.querySelector('button[type="submit"]');
      if (submitBtn) submitBtn.disabled = true;

      try {
        const fd = new FormData(contextForm);
        fd.set('action', 'update_own_context');
        fd.set('_csrf', csrfToken);
        const resp = await fetch(profileApiUrl, {
          method: 'POST',
          credentials: 'same-origin',
          headers: { 'X-CSRF-Token': csrfToken },
          body: fd
        });
        const data = await resp.json();
        if (!resp.ok || !data.ok) {
          throw new Error(data.message || 'No fue posible actualizar el consultorio y turno.');
        }
        const u = data.user || {};
        const parts = [
          (u.unidad_nombre || '').trim(),
          (u.consultorio_nombre || u.consultorio_clave || '').trim(),
          (u.turno_nombre || '').trim()
        ].filter(Boolean);
        if (contextTrigger) {
          contextTrigger.textContent = parts.length ? parts.join(' · ') : 'ÁMBITO GENERAL';
        }
        window.dispatchEvent(new CustomEvent('srae:context-updated', { detail: u }));
        closeQuickLayer(contextLayer);
        showQuickToast(data.message || 'CONSULTORIO Y TURNO ACTUALIZADOS CORRECTAMENTE.');
      } catch (err) {
        if (errBox) {
          errBox.textContent = err.message;
          errBox.hidden = false;
        }
      } finally {
        if (submitBtn) submitBtn.disabled = false;
      }
    });
  }
});

// Registro de Service Worker (PWA) con manejo silencioso para intranet HTTP
if ('serviceWorker' in navigator) {
  window.addEventListener('load', () => {
    navigator.serviceWorker.register('sw.js').catch(() => {});
  });
}
