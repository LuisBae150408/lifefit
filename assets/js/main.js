(() => {
  'use strict';

  /* Sombra del nav al hacer scroll */
  const nav = document.querySelector('.l2-nav');
  if (nav) {
    const onScroll = () => nav.classList.toggle('is-scrolled', window.scrollY > 8);
    onScroll();
    window.addEventListener('scroll', onScroll, { passive: true });
  }

  /* Menú móvil (burger) */
  const burger = document.getElementById('l2-nav-burger');
  const navLinks = document.getElementById('l2-nav-links');
  burger?.addEventListener('click', () => {
    const open = navLinks.classList.toggle('is-open');
    burger.setAttribute('aria-expanded', String(open));
  });
  navLinks?.querySelectorAll('a').forEach(a =>
    a.addEventListener('click', () => {
      navLinks.classList.remove('is-open');
      burger?.setAttribute('aria-expanded', 'false');
    })
  );

  /* Filtros de categoría en la página de menú: resaltar el activo
     según la sección visible + scrollear suave al click */
  const filters = document.querySelectorAll('.l2-filter');
  const sections = document.querySelectorAll('.l2-cat-section');

  filters.forEach(btn => {
    btn.addEventListener('click', (e) => {
      e.preventDefault();
      const target = document.querySelector(btn.getAttribute('href'));
      target?.scrollIntoView({ behavior: 'smooth', block: 'start' });
    });
  });

  if ('IntersectionObserver' in window && sections.length) {
    const io = new IntersectionObserver((entries) => {
      entries.forEach(entry => {
        if (entry.isIntersecting) {
          const id = entry.target.getAttribute('id');
          filters.forEach(f => f.classList.toggle('is-active', f.getAttribute('href') === '#' + id));
        }
      });
    }, { rootMargin: '-160px 0px -60% 0px', threshold: 0 });
    sections.forEach(s => io.observe(s));
  }

  /* =================================================================
     CONSTRUCTORES ("Arma tu X") -- picker de ingredientes dinámico
     -----------------------------------------------------------------
     Cada .l2-builder trae sus grupos (.l2-group, con data-min/data-max
     ya resueltos desde la base de datos) y sus opciones (.l2-opt).
     Esta función:
       - abre/cierra el panel al hacer click en el toggle
       - respeta el máximo de cada grupo (deshabilita el resto de
         opciones al llegar al límite, sin bloquear las ya elegidas)
       - actualiza en vivo el texto de la regla ("Elige 2 · 1/2")
       - arma un resumen legible de la selección y el total en vivo
         (precio base ya con descuento + extras de cada ingrediente,
         también ya con descuento -- ver includes/data.php)
       - deja los datos listos para "Añadir al carrito"
  ================================================================= */
  function ruleLabel(min, max, count) {
    if (max !== null && min >= 1 && max === min) return `Elige ${min} · ${count}/${max}`;
    if (max !== null && min >= 1) return `Elige entre ${min} y ${max} · ${count}/${max}`;
    if (min >= 1) return `Elige al menos ${min} · ${count} elegido${count === 1 ? '' : 's'}`;
    if (max === 1) return `Opcional · elige 1 · ${count}/1`;
    if (max !== null) return `Opcional · hasta ${max} · ${count}/${max}`;
    return `Opcional · ${count} elegido${count === 1 ? '' : 's'}`;
  }

  function enforceGroup(groupEl, max) {
    const inputs = Array.from(groupEl.querySelectorAll('input'));
    let checked = inputs.filter(i => i.checked);

    // Si el tamaño (Normal/Mini) bajó el máximo permitido de este grupo y
    // ya había más ingredientes marcados de los que ahora caben (ej. 2
    // proteínas elegidas y se pasa a Mini, que solo permite 1), se
    // destildan los últimos para respetar el nuevo límite.
    if (max !== null && checked.length > max) {
      checked.slice(max).forEach(input => { input.checked = false; });
      checked = inputs.filter(i => i.checked);
    }

    inputs.forEach(input => {
      const opt = input.closest('.l2-opt');
      if (!opt) return;
      opt.classList.toggle('is-selected', input.checked);
      if (input.type === 'checkbox' && max !== null) {
        const disable = !input.checked && checked.length >= max;
        input.disabled = disable;
        opt.classList.toggle('is-disabled', disable);
      }
    });

    return checked;
  }

  function updateBuilder(builderEl) {
    const groups = Array.from(builderEl.querySelectorAll('[data-group]'));
    const summaryParts = [];

    // Tamaño Normal/Mini (solo existe el toggle en constructores que
    // tengan precio_mini configurado, ej. el Poke). Cuando está en Mini
    // se usa el precio mini y, por cada grupo, su límite "-mini" si tiene
    // uno definido (ej. proteína pasa de "2 max" a "1", toppings de "4
    // max" a "3"), tal como dice la nota del constructor.
    const sizeInput = builderEl.querySelector('[data-builder-size]:checked');
    const isMini = !!sizeInput && sizeInput.value === 'mini';
    const basePriceMiniAttr = builderEl.dataset.basePriceMini;
    const basePrice = (isMini && basePriceMiniAttr)
      ? (parseFloat(basePriceMiniAttr) || 0)
      : (parseFloat(builderEl.dataset.basePrice || '0') || 0);

    let extrasTotal = 0;
    let valid = true;
    const seleccionNombres = [];

    groups.forEach(groupEl => {
      const minAttr = (isMini && groupEl.dataset.minMini !== '' && groupEl.dataset.minMini !== undefined)
        ? groupEl.dataset.minMini : groupEl.dataset.min;
      const maxAttrRaw = (isMini && groupEl.dataset.maxMini !== '' && groupEl.dataset.maxMini !== undefined)
        ? groupEl.dataset.maxMini : groupEl.dataset.max;
      const min = parseInt(minAttr || '0', 10);
      const max = maxAttrRaw === '' || maxAttrRaw === undefined ? null : parseInt(maxAttrRaw, 10);
      const checked = enforceGroup(groupEl, max);

      const ruleEl = groupEl.querySelector('[data-rule]');
      if (ruleEl) {
        ruleEl.textContent = ruleLabel(min, max, checked.length);
        ruleEl.classList.toggle('is-invalid', checked.length > 0 && checked.length < min);
      }
      if (checked.length < min) valid = false;

      checked.forEach(input => {
        extrasTotal += parseFloat(input.dataset.extra || '0') || 0;
        seleccionNombres.push(input.value);
      });

      if (checked.length) {
        const nombreGrupo = groupEl.dataset.nombre || '';
        summaryParts.push(`${nombreGrupo}: ${checked.map(i => i.value).join(', ')}`);
      }
    });

    // La base fija (ej. "Base: Banana, fresa y piña." del Yogurt) no es
    // algo que el cliente elija, pero igual debe quedar claro qué trae --
    // se manda siempre primero en el resumen y por lo tanto también en el
    // mensaje de WhatsApp.
    const baseFija = builderEl.dataset.baseFija || '';
    if (baseFija) summaryParts.unshift(baseFija);

    // Combos: si el cliente eligió TODOS los ingredientes de algún combo
    // configurado para este constructor (puede haber elegido otros
    // ademas, no importa), se aplica el que dé el mayor descuento.
    let combos = [];
    try { combos = JSON.parse(builderEl.dataset.combos || '[]'); } catch { combos = []; }
    const subtotal = basePrice + extrasTotal;
    let comboAplicado = null;
    let descuentoCombo = 0;
    combos.forEach(combo => {
      const cumple = combo.ingredientes.every(nombre => seleccionNombres.includes(nombre));
      if (!cumple) return;
      const monto = combo.tipo === 'porcentaje' ? subtotal * (combo.valor / 100) : combo.valor;
      if (monto > descuentoCombo) { descuentoCombo = monto; comboAplicado = combo; }
    });
    // Se agrega al resumen (y por lo tanto al pedido de WhatsApp) para que
    // quede constancia de qué combo se aplicó y por qué el total no es la
    // simple suma de base + extras.
    if (comboAplicado) summaryParts.push(`🎉 Combo "${comboAplicado.nombre}" aplicado`);

    const summaryEl = builderEl.querySelector('[data-summary]');
    if (summaryEl) {
      summaryEl.textContent = summaryParts.length
        ? summaryParts.join(' · ')
        : 'Elige tus ingredientes arriba para armar tu pedido.';
    }

    const total = Math.max(0, subtotal - descuentoCombo);
    const totalEl = builderEl.querySelector('[data-total]');
    if (totalEl) {
      totalEl.innerHTML = comboAplicado
        ? `Total: $${total.toFixed(2)} <span class="l2-builder__combo-tag">🎉 Combo "${escapeHtml(comboAplicado.nombre)}" aplicado</span>`
        : `Total: $${total.toFixed(2)}`;
    }

    const titleEl = builderEl.querySelector('.l2-builder__info h3');
    const rawTitle = titleEl ? titleEl.textContent.trim() : 'mi pedido';
    // El h3 trae el nombre completo de la seccion ("Crea tu Yogurt",
    // "Arma tu Poke"...); para el pedido solo queremos el nombre del
    // producto ("Yogurt", "Poke"), sin el prefijo de encabezado.
    const tituloBase = rawTitle.replace(/^(crea|arma|elige)\s+(tu|el|la)\s+/i, '').trim() || rawTitle;
    const title = isMini ? `${tituloBase} (Mini)` : tituloBase;

    // Datos listos para cuando toquen "Añadir al carrito" (ver mas abajo).
    const descuento = comboAplicado ? comboAplicado.nombre : (builderEl.dataset.descuento || null);
    builderEl._cartData = { title, summaryParts: summaryParts.slice(), total, valid, descuento, size: isMini ? 'mini' : 'normal' };

    const addBtn = builderEl.querySelector('[data-add-to-cart]');
    if (addBtn) addBtn.disabled = !valid;
  }

  document.querySelectorAll('[data-builder]').forEach(builderEl => {
    const toggle = builderEl.querySelector('.l2-builder__toggle');
    toggle?.addEventListener('click', () => {
      const open = builderEl.classList.toggle('is-open');
      toggle.setAttribute('aria-expanded', String(open));
    });

    builderEl.addEventListener('change', (e) => {
      if (e.target instanceof HTMLInputElement && (e.target.type === 'checkbox' || e.target.type === 'radio')) {
        updateBuilder(builderEl);
      }
    });

    builderEl.querySelectorAll('.l2-tpl-btn').forEach(btn => {
      btn.addEventListener('click', () => {
        let nombres = [];
        try { nombres = JSON.parse(btn.dataset.plantilla || '[]'); } catch { nombres = []; }
        builderEl.querySelectorAll('.l2-opts input[type="checkbox"], .l2-opts input[type="radio"]').forEach(input => {
          input.checked = nombres.includes(input.value);
        });
        updateBuilder(builderEl);
        if (!builderEl.classList.contains('is-open')) toggle?.click();
      });
    });

    wireQtyStepper(builderEl);

    const addBtn = builderEl.querySelector('[data-add-to-cart]');
    const commentEl = builderEl.querySelector('[data-builder-comment]');
    addBtn?.addEventListener('click', () => {
      const data = builderEl._cartData;
      if (!data || !data.valid) return;
      const qty = getQty(builderEl);
      // Comentario libre del cliente (ej. "poco pistacho"). Se toma recien
      // al añadir al carrito y se agrega como una linea mas del detalle --
      // por lo tanto tambien sale en el mensaje de WhatsApp (que separa el
      // detalle por " · " y muestra cada parte como un renglon).
      const comentario = commentEl ? commentEl.value.trim() : '';
      const detalleParts = data.summaryParts.slice();
      if (comentario) detalleParts.push(`📝 ${comentario}`);
      addToCart({
        id: `build:${builderEl.dataset.builderId}:${data.size}:${data.summaryParts.join('|')}${comentario ? `:c:${comentario}` : ''}`,
        nombre: data.title,
        detalle: detalleParts.join(' · '),
        precio: data.total,
        descuento: data.descuento,
        qty,
      });
      resetQty(builderEl);
      if (commentEl) commentEl.value = '';
      flashAdded(addBtn);
    });

    updateBuilder(builderEl);
  });

  /* =================================================================
     SELECTOR DE CANTIDAD (+/-)
     Se usa en cada builder, en cada producto del catalogo y dentro
     de las lineas del carrito. min 1, max 30.
  ================================================================= */
  function wireQtyStepper(scopeEl) {
    const box = scopeEl.querySelector('[data-qty]');
    if (!box) return;
    const valueEl = box.querySelector('[data-qty-value]');
    const minus = box.querySelector('[data-qty-minus]');
    const plus = box.querySelector('[data-qty-plus]');
    minus?.addEventListener('click', () => {
      const v = Math.max(1, (parseInt(valueEl.textContent, 10) || 1) - 1);
      valueEl.textContent = String(v);
    });
    plus?.addEventListener('click', () => {
      const v = Math.min(30, (parseInt(valueEl.textContent, 10) || 1) + 1);
      valueEl.textContent = String(v);
    });
  }
  function getQty(scopeEl) {
    const valueEl = scopeEl.querySelector('[data-qty-value]');
    return valueEl ? (parseInt(valueEl.textContent, 10) || 1) : 1;
  }
  function resetQty(scopeEl) {
    const valueEl = scopeEl.querySelector('[data-qty-value]');
    if (valueEl) valueEl.textContent = '1';
  }
  function flashAdded(btn) {
    if (!btn) return;
    btn.classList.add('is-added');
    window.setTimeout(() => btn.classList.remove('is-added'), 900);
  }

  /* =================================================================
     PRODUCTOS DE CATALOGO ESTANDAR (Smoothies, Cafe, Postres...)
     Sin personalizacion: solo cantidad + añadir al carrito.
  ================================================================= */
  document.querySelectorAll('[data-catalog-item]').forEach(itemEl => {
    wireQtyStepper(itemEl);

    let tamanos = [];
    try { tamanos = JSON.parse(itemEl.dataset.tamanos || '[]'); } catch { tamanos = []; }
    const precioDisplay = itemEl.querySelector('[data-price-display]');
    const grupoTamanos = itemEl.querySelector('[data-tamanos-group]');
    let tamanoElegido = tamanos[0] || null;

    grupoTamanos?.addEventListener('change', (e) => {
      const idx = parseInt(e.target.value, 10);
      tamanoElegido = tamanos[idx];
      if (!tamanoElegido) return;
      itemEl.dataset.price = tamanoElegido.precio_final;
      if (precioDisplay) {
        precioDisplay.innerHTML = itemEl.dataset.descuento
          ? `<s class="l2-price-old">$${escapeHtml(tamanoElegido.precio)}</s> $${escapeHtml(tamanoElegido.precio_final)}`
          : `$${escapeHtml(tamanoElegido.precio)}`;
      }
    });

    const addBtn = itemEl.querySelector('[data-add-to-cart]');
    addBtn?.addEventListener('click', () => {
      const qty = getQty(itemEl);
      const sufijoId = tamanoElegido ? `:${tamanoElegido.nombre}` : '';
      addToCart({
        id: `cat:${itemEl.dataset.id}${sufijoId}`,
        nombre: tamanoElegido ? `${itemEl.dataset.nombre} (${tamanoElegido.nombre})` : itemEl.dataset.nombre,
        detalle: '',
        precio: parseFloat(itemEl.dataset.price || '0') || 0,
        descuento: itemEl.dataset.descuento || null,
        qty,
      });
      resetQty(itemEl);
      flashAdded(addBtn);
    });
  });

  /* =================================================================
     CARRITO
     -----------------------------------------------------------------
     Vive en localStorage (solo en este navegador) y se comparte entre
     la página principal y /menu/. Cada linea: {id, nombre, detalle,
     precio (unitario, ya con descuento resuelto), qty}. El id
     identifica una configuracion exacta (mismo builder + mismos
     ingredientes, o mismo producto de catalogo), asi que agregar lo
     mismo dos veces suma cantidad en vez de crear una linea repetida.
  ================================================================= */
  const CART_KEY = 'lf_cart_v1';
  // Numero configurado en el admin (Configuración); viene en el
  // atributo data-whatsapp-number del botón de enviar pedido. Si por
  // algo no está, se usa el de pruebas como último respaldo.
  const WHATSAPP_NUMBER = document.querySelector('[data-cart-whatsapp]')?.dataset.whatsappNumber || '584126448878';

  function loadCart() {
    try {
      const raw = JSON.parse(localStorage.getItem(CART_KEY));
      return Array.isArray(raw) ? raw : [];
    } catch {
      return [];
    }
  }

  function saveCart(items) {
    try { localStorage.setItem(CART_KEY, JSON.stringify(items)); } catch { /* almacenamiento no disponible */ }
    renderCart();
  }

  function addToCart(line) {
    const cart = loadCart();
    const existing = cart.find(l => l.id === line.id);
    if (existing) existing.qty += line.qty;
    else cart.push(line);
    saveCart(cart);
  }

  function setLineQty(id, qty) {
    let cart = loadCart();
    if (qty <= 0) {
      cart = cart.filter(l => l.id !== id);
    } else {
      const line = cart.find(l => l.id === id);
      if (line) line.qty = qty;
    }
    saveCart(cart);
  }

  function removeLine(id) {
    saveCart(loadCart().filter(l => l.id !== id));
  }

  function cartTotal(cart) {
    return cart.reduce((sum, l) => sum + l.precio * l.qty, 0);
  }

  function buildWhatsappMessage(cart) {
    const lineas = ['¡Hola! Quiero hacer este pedido:', ''];
    cart.forEach((l, i) => {
      lineas.push(`${i + 1}. *${l.nombre}* x${l.qty} · $${(l.precio * l.qty).toFixed(2)}${l.descuento ? ` (🏷️ ${l.descuento})` : ''}`);
      if (l.detalle) {
        l.detalle.split(' · ').forEach(part => lineas.push(`   - ${part}`));
      }
    });
    lineas.push('', `Total: $${cartTotal(cart).toFixed(2)}`);
    return lineas.join('\n');
  }

  function renderCart() {
    const cart = loadCart();
    const count = cart.reduce((sum, l) => sum + l.qty, 0);

    document.querySelectorAll('[data-cart-count]').forEach(el => {
      el.textContent = String(count);
      el.hidden = count === 0;
    });

    document.querySelectorAll('[data-cart-body]').forEach(bodyEl => {
      if (!cart.length) {
        bodyEl.innerHTML = '<p class="l2-cart-empty">Tu carrito está vacío. Arma un producto o elige algo del menú para empezar.</p>';
        return;
      }
      bodyEl.innerHTML = cart.map(l => `
        <div class="l2-cart-line" data-line-id="${escapeHtml(l.id)}">
          <div class="l2-cart-line__info">
            <p class="l2-cart-line__name">${escapeHtml(l.nombre)} · $${(l.precio * l.qty).toFixed(2)}${l.descuento ? ` <span class="l2-tag">${escapeHtml(l.descuento)}</span>` : ''}</p>
            ${l.detalle ? `<p class="l2-cart-line__detail">${escapeHtml(l.detalle)}</p>` : ''}
            <p class="l2-cart-line__price">$${l.precio.toFixed(2)} c/u</p>
          </div>
          <div class="l2-cart-line__actions">
            <button type="button" class="l2-cart-line__remove" data-line-remove aria-label="Quitar ${escapeHtml(l.nombre)}">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M3 6h18M8 6V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2m3 0-1 14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2L4 6"/></svg>
            </button>
            <div class="l2-qty">
              <button type="button" data-line-minus aria-label="Restar">−</button>
              <span>${l.qty}</span>
              <button type="button" data-line-plus aria-label="Sumar">+</button>
            </div>
          </div>
        </div>
      `).join('');
    });

    document.querySelectorAll('[data-cart-total]').forEach(el => {
      el.textContent = `$${cartTotal(cart).toFixed(2)}`;
    });

    document.querySelectorAll('[data-cart-whatsapp]').forEach(el => {
      const texto = encodeURIComponent(buildWhatsappMessage(cart));
      el.href = `https://api.whatsapp.com/send?phone=${WHATSAPP_NUMBER}&text=${texto}`;
      el.classList.toggle('is-disabled', cart.length === 0);
      if (cart.length === 0) el.setAttribute('aria-disabled', 'true');
      else el.removeAttribute('aria-disabled');
    });
  }

  function escapeHtml(str) {
    return String(str).replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
  }

  /* Delegacion de eventos: las lineas del carrito se regeneran en cada
     render, asi que sus botones se enganchan por delegacion en el
     contenedor fijo (que si existe desde el arranque). */
  document.querySelectorAll('[data-cart-body]').forEach(bodyEl => {
    bodyEl.addEventListener('click', (e) => {
      const lineEl = e.target.closest('[data-line-id]');
      if (!lineEl) return;
      const id = lineEl.dataset.lineId;
      const cart = loadCart();
      const line = cart.find(l => l.id === id);
      if (!line) return;

      if (e.target.closest('[data-line-remove]')) {
        removeLine(id);
      } else if (e.target.closest('[data-line-plus]')) {
        setLineQty(id, line.qty + 1);
      } else if (e.target.closest('[data-line-minus]')) {
        setLineQty(id, line.qty - 1);
      }
    });
  });

  /* Abrir / cerrar el panel */
  function openCart() {
    document.querySelector('[data-cart-drawer]')?.classList.add('is-open');
    document.querySelector('[data-cart-drawer]')?.setAttribute('aria-hidden', 'false');
    document.querySelector('[data-cart-backdrop]')?.classList.add('is-open');
    document.body.style.overflow = 'hidden';
  }
  function closeCart() {
    document.querySelector('[data-cart-drawer]')?.classList.remove('is-open');
    document.querySelector('[data-cart-drawer]')?.setAttribute('aria-hidden', 'true');
    document.querySelector('[data-cart-backdrop]')?.classList.remove('is-open');
    document.body.style.overflow = '';
  }
  document.querySelectorAll('[data-cart-toggle]').forEach(btn => btn.addEventListener('click', openCart));
  document.querySelectorAll('[data-cart-close]').forEach(btn => btn.addEventListener('click', closeCart));
  document.querySelectorAll('[data-cart-backdrop]').forEach(el => el.addEventListener('click', closeCart));
  document.addEventListener('keydown', (e) => { if (e.key === 'Escape') closeCart(); });

  renderCart();
})();
