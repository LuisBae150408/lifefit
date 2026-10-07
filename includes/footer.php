<?php /** footer.php — contacto + cierre */ ?>

<footer class="footer" id="contacto">
  <div class="footer__inner">
    <div class="footer__brand">
      <?= render_logo('peanut-mark peanut-mark--lg') ?>
      <p><?= htmlspecialchars($info['eslogan']) ?></p>
    </div>

    <div class="footer__col">
      <span class="footer__label">Visítanos</span>
      <p><?= htmlspecialchars($info['ubicacion']) ?></p>
      <p><?= htmlspecialchars($info['horario_semana']) ?></p>
      <p><?= htmlspecialchars($info['horario_finde']) ?></p>
    </div>

    <div class="footer__col">
      <span class="footer__label">Síguenos</span>
      <div class="footer__socials">
        <a href="<?= htmlspecialchars($info['instagram']) ?>" aria-label="Instagram">Instagram</a>
        <a href="<?= htmlspecialchars($info['whatsapp']) ?>" aria-label="WhatsApp" class="js-whatsapp">WhatsApp</a>
      </div>
    </div>
  </div>

  <div class="footer__bottom">
    <span>© <?= date('Y') ?> LIFE FIT. Todos los derechos reservados.</span>
  </div>
</footer>

<!-- Botón flotante de WhatsApp — aún no funcional, se conectará luego -->
<button class="whatsapp-fab js-whatsapp" type="button" aria-label="Escribir por WhatsApp">
  <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true">
    <path d="M4 20l1.3-4.1A8 8 0 1 1 9 18.6L4 20Z"/>
    <path d="M8.5 9.7c0 3.3 2.7 6 6 6"/>
  </svg>
  <span class="whatsapp-fab__tip" role="status">Muy pronto 🥜</span>
</button>

<script src="assets/js/main.js"></script>
</body>
</html>
