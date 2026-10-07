<?php
require_once __DIR__ . '/../includes/auth.php';
admin_cerrar_sesion();
header('Location: login.php');
exit;
