/**
 * admin-edit.js -- panel de edición inline reutilizable para el admin.
 *
 * En vez de bajar hasta un formulario al final de la página, "Editar"
 * abre una barra editable justo debajo de lo que se está editando
 * (una fila de tabla, o un bloque cualquiera como el header de un grupo).
 *
 * Uso típico desde cada página:
 *
 *   const panel = AdminEdit.open(botón.closest('tr'), htmlDelFormulario);
 *   if (!panel) return; // el usuario canceló (tenía cambios sin guardar)
 *   panel.querySelector('[name="nombre"]').value = dato.nombre;
 *   AdminEdit.snapshot(); // marca este estado como "sin cambios" (una vez cargados los datos)
 *
 * Solo puede haber un panel abierto a la vez:
 *   - Abrir uno nuevo cierra el anterior (con confirmación si tenía cambios sin guardar).
 *   - Volver a tocar "Editar" sobre lo mismo que ya está abierto lo cierra (toggle),
 *     también con confirmación si hay cambios sin guardar.
 *   - El botón "Descartar" de cada formulario debe llamar a AdminEdit.close(true)
 *     para cerrar sin confirmar (ya es, en sí mismo, la confirmación explícita).
 */
const AdminEdit = (() => {
  let activo = null; // { anclaEl, panelWrapEl, snapshotInicial }

  function panelDe(wrapEl) {
    return wrapEl.classList.contains('admin-edit-panel') ? wrapEl : wrapEl.querySelector('.admin-edit-panel');
  }

  function serializarFormulario(panelEl) {
    const form = panelEl?.querySelector('form');
    if (!form) return '';
    return new URLSearchParams(new FormData(form)).toString();
  }

  function estaSucio() {
    if (!activo) return false;
    return serializarFormulario(panelDe(activo.panelWrapEl)) !== activo.snapshotInicial;
  }

  function cerrarInterno() {
    if (!activo) return;
    activo.panelWrapEl.remove();
    activo.anclaEl.classList.remove('is-editing');
    activo = null;
  }

  /**
   * Cierra el panel activo. Si tiene cambios sin guardar, pide confirmación
   * antes (salvo que se pase forzar=true). Devuelve true si quedó cerrado
   * (o no había nada abierto), false si el usuario canceló el cierre.
   */
  function close(forzar = false) {
    if (!activo) return true;
    if (!forzar && estaSucio() && !confirm('Tenés cambios sin guardar en este formulario. ¿Salir sin guardar?')) {
      return false;
    }
    cerrarInterno();
    return true;
  }

  /**
   * Marca el estado actual del formulario abierto como "sin cambios".
   * Llamar una vez, después de cargar los datos iniciales en el panel
   * (y de terminar cualquier wireo adicional que cambie valores, como
   * mostrar/ocultar campos según otra selección).
   */
  function snapshot() {
    if (!activo) return;
    activo.snapshotInicial = serializarFormulario(panelDe(activo.panelWrapEl));
  }

  /**
   * Abre el panel de edición inmediatamente después de `anclaEl`.
   * Si `anclaEl` es una fila de tabla (<tr>), el panel se inserta como
   * una nueva fila con colspan completo. Si es cualquier otro elemento
   * (por ejemplo un <div> de tarjeta), se inserta como bloque normal.
   *
   * Si se vuelve a llamar sobre la MISMA ancla que ya está abierta,
   * actúa como toggle y la cierra. Si hay otro panel abierto con
   * cambios sin guardar, pide confirmación antes de reemplazarlo.
   *
   * Devuelve el elemento .admin-edit-panel para que la página llene sus
   * campos, o null si no se abrió nada (toggle-cierre, o el usuario
   * canceló por tener cambios sin guardar en el panel anterior).
   */
  function open(anclaEl, htmlContenido) {
    if (activo && activo.anclaEl === anclaEl) {
      close();
      return null;
    }
    if (!close()) return null;

    const esFila = anclaEl.tagName === 'TR';
    let panelWrapEl;

    if (esFila) {
      const nuevaFila = document.createElement('tr');
      nuevaFila.className = 'admin-edit-row';
      const celda = document.createElement('td');
      celda.colSpan = anclaEl.children.length;
      celda.innerHTML = '<div class="admin-edit-panel">' + htmlContenido + '</div>';
      nuevaFila.appendChild(celda);
      anclaEl.insertAdjacentElement('afterend', nuevaFila);
      panelWrapEl = nuevaFila;

      // Si la tabla tiene scroll horizontal (celular/tablet) y el botón
      // "Editar" estaba a la derecha, sin esto el formulario se abre
      // fuera de la vista. Volvemos el scroll al inicio.
      const tablaScrolleable = anclaEl.closest('table');
      if (tablaScrolleable) tablaScrolleable.scrollLeft = 0;
    } else {
      const contenedor = document.createElement('div');
      contenedor.className = 'admin-edit-panel';
      contenedor.innerHTML = htmlContenido;
      anclaEl.insertAdjacentElement('afterend', contenedor);
      panelWrapEl = contenedor;
    }

    anclaEl.classList.add('is-editing');
    activo = { anclaEl, panelWrapEl, snapshotInicial: '' };

    const panelEl = panelDe(panelWrapEl);
    const primerCampo = panelEl.querySelector('input, select, textarea');
    if (primerCampo) primerCampo.focus();
    panelWrapEl.scrollIntoView({ behavior: 'smooth', block: 'center' });

    // Snapshot provisorio (formulario recién insertado, sin datos todavía);
    // la página lo vuelve a fijar con AdminEdit.snapshot() tras cargar los valores.
    snapshot();

    return panelEl;
  }

  let enviandoFormulario = false;
  document.addEventListener('submit', () => { enviandoFormulario = true; });

  window.addEventListener('beforeunload', (e) => {
    if (!enviandoFormulario && estaSucio()) { e.preventDefault(); e.returnValue = ''; }
  });

  document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape') close();
  });

  return { open, close, snapshot };
})();

/** Escapa texto para insertarlo de forma segura dentro de un template HTML. */
function adminEscapeHtml(valor) {
  const div = document.createElement('div');
  div.textContent = valor ?? '';
  return div.innerHTML;
}

/**
 * Confirma y envía un formulario de borrado oculto que ya existe en la página
 * (con un input hidden para el id). Patrón común a todas las páginas del admin.
 */
function adminConfirmarYBorrar(mensaje, formId, inputIdName, valorId) {
  if (!confirm(mensaje)) return;
  document.getElementById(inputIdName).value = valorId;
  document.getElementById(formId).submit();
}
