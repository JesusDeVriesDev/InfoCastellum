<?php
// ── Configuración ThingSpeak ──────────────────────────────────────────────────
// ⚠ Reemplaza TU_CHANNEL_ID con tu número de canal (ej: 2456789)
$TS_CHANNEL  = '3399574';
$TS_READ_KEY = '5SMWGEYYPQVMT10V';
// field1=Temperatura  field2=Humedad aire
// field3=Humedad suelo  field4=Nivel agua  field5=Sonido

// ── Conexión a Supabase PostgreSQL ────────────────────────────────────────────
$host     = 'aws-1-us-east-1.pooler.supabase.com';
$port     = 6543;
$dbname   = 'postgres';
$user     = 'postgres.dsayylzehvehmscwkhma';
$password = 'awuebo21xdA';

$error   = null;
$last    = null;
$led_on  = false;
$history = [];

try {
    $pdo = new PDO(
        "pgsql:host=$host;port=$port;dbname=$dbname",
        $user,
        $password,
        [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            // ✅ FIX: Supabase pooler (puerto 6543) no soporta prepared statements nativos
            PDO::ATTR_EMULATE_PREPARES   => true,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]
    );

    $last = $pdo->query("
        SELECT humedad_suelo, nivel_agua, dht_temp, dht_hum, sonido, created_at
        FROM sensors_data ORDER BY created_at DESC LIMIT 1
    ")->fetch();

    $history = $pdo->query("
        SELECT humedad_suelo, nivel_agua, dht_temp, dht_hum, sonido, created_at
        FROM sensors_data ORDER BY created_at DESC LIMIT 20
    ")->fetchAll();

    $row = $pdo->query("SELECT led FROM control_dispositivos WHERE id = 1")->fetch();
    if ($row) $led_on = (bool)$row['led'];

} catch (PDOException $e) {
    $error = $e->getMessage();
}

// ── Endpoint AJAX: devuelve JSON con datos frescos ────────────────────────────
if (isset($_GET['ajax'])) {
    header('Content-Type: application/json');
    echo json_encode([
        'error'   => $error,
        'last'    => $last,
        'led_on'  => $led_on,
        'history' => $history,
    ]);
    exit;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Monitor de Sensores</title>
<!-- PWA -->
<link rel="manifest" href="manifest.json">
<meta name="mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
<meta name="apple-mobile-web-app-title" content="Sensor.IO">
<meta name="theme-color" content="#3ddc84">
<link rel="apple-touch-icon" href="icons/icon-192x192.png">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=DM+Mono:wght@400;500&family=Syne:wght@400;600;700;800&display=swap" rel="stylesheet">
<style>
  *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

  :root {
    --bg:      #0a0b0e;
    --surface: #111318;
    --surface2:#181c23;
    --border:  #222730;
    --border2: #2e3545;
    --text:    #e2e6f0;
    --muted:   #5a6478;
    --accent:  #3ddc84;
    --accent2: #1a7a42;
    --warn:    #f0b429;
    --danger:  #e84040;
    --info:    #4a9eff;
    --mono:    'DM Mono', monospace;
    --display: 'Syne', sans-serif;
  }

  html { scroll-behavior: smooth; }

  body {
    background: var(--bg);
    color: var(--text);
    font-family: var(--mono);
    font-size: 13px;
    min-height: 100vh;
    line-height: 1.6;
  }

  body::before {
    content: '';
    position: fixed;
    inset: 0;
    background: repeating-linear-gradient(0deg,transparent,transparent 2px,rgba(0,0,0,0.04) 2px,rgba(0,0,0,0.04) 4px);
    pointer-events: none;
    z-index: 999;
  }

  /* Header */
  header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 20px 32px;
    border-bottom: 1px solid var(--border);
    background: var(--surface);
    position: sticky;
    top: 0;
    z-index: 100;
  }
  .logo { font-family: var(--display); font-weight: 800; font-size: 18px; letter-spacing: -0.5px; }
  .logo span { color: var(--accent); }
  .status-dot { display: flex; align-items: center; gap: 8px; font-size: 11px; color: var(--muted); text-transform: uppercase; letter-spacing: 1px; }
  .dot { width: 7px; height: 7px; border-radius: 50%; background: var(--accent); box-shadow: 0 0 8px var(--accent); animation: pulse 2s ease-in-out infinite; }
  @keyframes pulse { 0%,100%{opacity:1;transform:scale(1)} 50%{opacity:.5;transform:scale(.85)} }
  .header-time { font-size: 12px; color: var(--muted); }

  /* Layout */
  main { max-width: 1100px; margin: 0 auto; padding: 32px 24px; display: flex; flex-direction: column; gap: 28px; }

  .error-banner { background: rgba(232,64,64,0.1); border: 1px solid var(--danger); border-radius: 8px; padding: 14px 18px; color: var(--danger); font-size: 12px; }

  .section-label {
    font-family: var(--display); font-size: 11px; font-weight: 600;
    text-transform: uppercase; letter-spacing: 2px; color: var(--muted);
    margin-bottom: 14px; display: flex; align-items: center; gap: 10px;
  }
  .section-label::after { content: ''; flex: 1; height: 1px; background: var(--border); }

  /* Sensor cards */
  .sensor-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 14px; }

  .sensor-card {
    background: var(--surface); border: 1px solid var(--border); border-radius: 12px;
    padding: 20px 18px; position: relative; overflow: hidden;
    transition: border-color .2s, transform .2s;
  }
  .sensor-card:hover { border-color: var(--border2); transform: translateY(-2px); }
  .sensor-card::before { content: ''; position: absolute; top: 0; left: 0; right: 0; height: 2px; border-radius: 12px 12px 0 0; }
  .card-temp::before    { background: linear-gradient(90deg,var(--danger),var(--warn)); }
  .card-hum-air::before { background: linear-gradient(90deg,var(--info),#7b4fff); }
  .card-hum-soil::before{ background: linear-gradient(90deg,#a8e063,var(--accent)); }
  .card-water::before   { background: linear-gradient(90deg,var(--info),#00cfff); }
  .card-sound::before   { background: linear-gradient(90deg,#ff6bff,var(--warn)); }

  .card-icon  { font-size: 20px; margin-bottom: 10px; display: block; }
  .card-label { font-size: 10px; text-transform: uppercase; letter-spacing: 1.5px; color: var(--muted); margin-bottom: 6px; }
  .card-value { font-family: var(--display); font-weight: 700; font-size: 36px; line-height: 1; }
  .card-value .unit { font-size: 16px; font-weight: 400; color: var(--muted); margin-left: 2px; }
  .card-bar { margin-top: 12px; height: 3px; background: var(--border); border-radius: 2px; overflow: hidden; }
  .card-bar-fill { height: 100%; border-radius: 2px; transition: width .8s cubic-bezier(.4,0,.2,1); }
  .card-updated { font-size: 10px; color: var(--muted); margin-top: 8px; opacity: .6; }
  .refresh-info { text-align: center; font-size: 11px; color: var(--muted); padding: 8px; }
  .refresh-info span { color: var(--accent); }
  .no-data { text-align: center; padding: 48px; color: var(--muted); }

  /* LED panel */
  .led-panel {
    background: var(--surface); border: 1px solid var(--border); border-radius: 12px;
    padding: 24px 28px; display: flex; align-items: center; justify-content: space-between;
    gap: 24px; flex-wrap: wrap;
  }
  .led-info { flex: 1; min-width: 200px; }
  .led-title { font-family: var(--display); font-size: 16px; font-weight: 700; margin-bottom: 4px; }
  .led-desc  { font-size: 11px; color: var(--muted); line-height: 1.5; }
  .led-state { display: flex; align-items: center; gap: 20px; }
  .led-visual { width: 48px; height: 48px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 22px; transition: all .4s; }
  .led-visual.on  { background: rgba(61,220,132,.15); box-shadow: 0 0 20px rgba(61,220,132,.4),0 0 40px rgba(61,220,132,.15); border: 1px solid var(--accent2); }
  .led-visual.off { background: var(--surface2); border: 1px solid var(--border); opacity: .5; }
  .led-badge { font-family: var(--display); font-size: 13px; font-weight: 700; padding: 4px 12px; border-radius: 20px; text-transform: uppercase; letter-spacing: 1px; }
  .led-badge.on  { background: rgba(61,220,132,.15); color: var(--accent); border: 1px solid var(--accent2); }
  .led-badge.off { background: var(--surface2); color: var(--muted); border: 1px solid var(--border); }
  .btn-toggle { font-family: var(--display); font-weight: 700; font-size: 13px; letter-spacing: .5px; padding: 12px 28px; border-radius: 8px; border: none; cursor: pointer; transition: all .2s; text-transform: uppercase; }
  .btn-on  { background: var(--accent); color: #000; }
  .btn-on:hover  { background: #52e896; transform: translateY(-1px); box-shadow: 0 4px 16px rgba(61,220,132,.3); }
  .btn-off { background: var(--surface2); color: var(--text); border: 1px solid var(--border2); }
  .btn-off:hover { background: var(--border); transform: translateY(-1px); }
  .btn-toggle:active { transform: translateY(0); }
  .btn-toggle:disabled { opacity: .5; cursor: not-allowed; transform: none; }

  /* Charts */
  .charts-grid { display: grid; grid-template-columns: repeat(2,1fr); gap: 14px; }
  .chart-card { background: var(--surface); border: 1px solid var(--border); border-radius: 12px; overflow: hidden; transition: border-color .2s; }
  .chart-card:hover { border-color: var(--border2); }
  .chart-card--wide { grid-column: 1 / -1; }
  .chart-label { display: flex; align-items: center; gap: 8px; padding: 12px 16px 8px; font-size: 11px; font-weight: 500; text-transform: uppercase; letter-spacing: 1px; color: var(--muted); border-bottom: 1px solid var(--border); }
  .chart-dot  { width: 7px; height: 7px; border-radius: 50%; flex-shrink: 0; }
  .chart-field { margin-left: auto; font-size: 10px; padding: 2px 7px; border-radius: 4px; background: var(--surface2); border: 1px solid var(--border); color: var(--muted); }

  /* ✅ FIX: iframe envuelto en contenedor con altura fija; NO se destruye con reload */
  .chart-frame-wrap { position: relative; width: 100%; height: 220px; overflow: hidden; background: var(--bg); }
  .chart-card--wide .chart-frame-wrap { height: 240px; }

  .chart-frame-wrap iframe {
    /* ThingSpeak inyecta sus propios estilos; forzamos tamaño completo */
    width: calc(100% + 0px);
    height: calc(100% + 0px);
    border: none;
    display: block;
    background: transparent;
    opacity: 0;
    transition: opacity .6s;
  }
  .chart-frame-wrap iframe.loaded { opacity: 1; }

  /* Shimmer mientras carga */
  .chart-frame-wrap::after {
    content: 'Cargando...';
    position: absolute;
    inset: 0;
    display: flex;
    align-items: center;
    justify-content: center;
    color: var(--muted);
    font-size: 11px;
    letter-spacing: 1px;
    text-transform: uppercase;
    background: linear-gradient(90deg, var(--bg) 25%, var(--surface) 50%, var(--bg) 75%);
    background-size: 200% 100%;
    animation: shimmer 1.8s infinite;
    pointer-events: none;
    z-index: 0;
    transition: opacity .3s;
  }
  .chart-frame-wrap.ready::after { opacity: 0; }

  @keyframes shimmer { 0%{background-position:200% 0} 100%{background-position:-200% 0} }

  .ts-warning { margin-top: 12px; padding: 12px 16px; background: rgba(240,180,41,.08); border: 1px solid rgba(240,180,41,.3); border-radius: 8px; color: var(--warn); font-size: 12px; }
  .ts-warning code { background: rgba(240,180,41,.15); padding: 1px 5px; border-radius: 3px; }

  /* Tabla */
  .table-wrapper { overflow-x: auto; border: 1px solid var(--border); border-radius: 12px; background: var(--surface); }
  table { width: 100%; border-collapse: collapse; font-size: 12px; }
  thead tr { border-bottom: 1px solid var(--border); }
  th { padding: 12px 16px; text-align: left; font-size: 10px; text-transform: uppercase; letter-spacing: 1.5px; color: var(--muted); font-weight: 500; white-space: nowrap; }
  td { padding: 11px 16px; border-bottom: 1px solid rgba(34,39,48,.6); white-space: nowrap; }
  tbody tr:last-child td { border-bottom: none; }
  tbody tr:hover { background: var(--surface2); }
  .val-temp  { color: var(--warn); }
  .val-hum   { color: var(--info); }
  .val-soil  { color: var(--accent); }
  .val-water { color: #00cfff; }
  .val-sound { color: #ff6bff; }
  .val-time  { color: var(--muted); font-size: 11px; }

  /* Animación de actualización en tarjetas */
  @keyframes flash { 0%,100%{opacity:1} 50%{opacity:.4} }
  .updating { animation: flash .4s ease; }

  /* Banner instalación PWA */
  #install-banner {
    display: none;
    position: fixed;
    bottom: 16px;
    left: 50%;
    transform: translateX(-50%);
    width: calc(100% - 32px);
    max-width: 480px;
    background: var(--surface);
    border: 1px solid var(--accent2);
    border-radius: 14px;
    padding: 14px 16px;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    z-index: 200;
    box-shadow: 0 8px 32px rgba(0,0,0,0.5), 0 0 0 1px rgba(61,220,132,0.1);
    animation: slideUp .3s cubic-bezier(.4,0,.2,1);
  }
  @keyframes slideUp { from{transform:translateX(-50%) translateY(20px);opacity:0} to{transform:translateX(-50%) translateY(0);opacity:1} }
  .install-left { display: flex; align-items: center; gap: 12px; }
  .install-left img { width: 40px; height: 40px; border-radius: 10px; }
  .install-title { font-family: var(--display); font-weight: 700; font-size: 14px; }
  .install-sub   { font-size: 11px; color: var(--muted); margin-top: 2px; }
  .install-actions { display: flex; align-items: center; gap: 8px; flex-shrink: 0; }
  .install-btn-yes {
    font-family: var(--display); font-weight: 700; font-size: 12px;
    padding: 8px 18px; border-radius: 8px; border: none;
    background: var(--accent); color: #000; cursor: pointer;
    text-transform: uppercase; letter-spacing: .5px;
  }
  .install-btn-yes:active { opacity: .8; }
  .install-btn-no {
    background: none; border: none; color: var(--muted);
    font-size: 16px; cursor: pointer; padding: 4px 8px;
    border-radius: 6px; line-height: 1;
  }
  .install-btn-no:hover { color: var(--text); background: var(--surface2); }

  @media (max-width:600px) {
    header { padding: 14px 16px; }
    main   { padding: 20px 14px; }
    .led-panel { flex-direction: column; }
    .card-value { font-size: 28px; }
    .charts-grid { grid-template-columns: 1fr; }
    .chart-card--wide { grid-column: auto; }
    .chart-frame-wrap { height: 200px; }
  }
  .btn-video {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  padding: 12px 24px;
  background: var(--surface);
  border: 1px solid var(--border2);
  border-radius: 8px;
  color: var(--text);
  text-decoration: none;
  font-family: var(--display);
  font-weight: 700;
  font-size: 13px;
  text-transform: uppercase;
  letter-spacing: 0.5px;
  transition: all .2s;
}
.btn-video:hover {
  border-color: var(--accent);
  color: var(--accent);
  transform: translateY(-1px);
}
</style>
</head>
<body>

<header>
  <div class="logo">INFOCASTELLUM</div>
  <div class="status-dot"><div class="dot"></div><span>EN VIVO</span></div>
  <div class="header-time" id="clock">--:--:--</div>
  <a href="video.php" class="btn-video">▶ Ver video</a>
</header>

<!-- Banner instalación PWA (Android lo muestra automáticamente) -->
<div id="install-banner">
  <div class="install-left">
    <img src="icons/icon-72x72.png" alt="icon">
    <div>
      <div class="install-title">Instalar InfoCastellum</div>
      <div class="install-sub">Agregar a pantalla de inicio</div>
    </div>
  </div>
  <div class="install-actions">
    <button id="btn-install" class="install-btn-yes">Instalar</button>
    <button id="btn-install-close" class="install-btn-no">✕</button>
  </div>
</div>

<main>

<!-- Error banner -->
<?php if (!empty($_GET['db_error'])): ?>
<div class="error-banner">
  ⚠ Error al cambiar el LED: <?= htmlspecialchars($_GET['db_error']) ?>
</div>
<?php endif; ?>
<div id="error-banner" style="display:none" class="error-banner"></div>

<!-- Tarjetas de sensores -->
<div>
  <div class="section-label">Lecturas actuales</div>
  <div class="sensor-grid" id="sensor-grid">
    <!-- Renderizado inicial por PHP -->
    <?php if ($last): ?>
    <?php
      $t = (float)$last['dht_temp'];
      $t_pct   = min(100, max(0, ($t / 50) * 100));
      $t_color = $t > 30 ? '#e84040' : ($t > 20 ? '#f0b429' : '#4a9eff');
      $t_label = $t > 30 ? '🔴 Alta' : ($t > 20 ? '🟡 Normal' : '🔵 Baja');
      $s_pct   = min(100, round((int)$last['sonido'] / 10.23));
    ?>
    <div class="sensor-card card-temp">
      <span class="card-icon">🌡️</span>
      <div class="card-label">Temperatura Aire</div>
      <div class="card-value" id="val-temp"><?= number_format($t,1) ?><span class="unit">°C</span></div>
      <div class="card-bar"><div class="card-bar-fill" id="bar-temp" style="width:<?= $t_pct ?>%;background:<?= $t_color ?>;"></div></div>
      <div class="card-updated" id="lbl-temp"><?= $t_label ?></div>
    </div>
    <div class="sensor-card card-hum-air">
      <span class="card-icon">💧</span>
      <div class="card-label">Humedad Aire</div>
      <div class="card-value" id="val-hum-air"><?= number_format((float)$last['dht_hum'],1) ?><span class="unit">%</span></div>
      <div class="card-bar"><div class="card-bar-fill" id="bar-hum-air" style="width:<?= (float)$last['dht_hum'] ?>%;background:var(--info);"></div></div>
      <div class="card-updated">DHT11</div>
    </div>
    <div class="sensor-card card-hum-soil">
      <span class="card-icon">🌱</span>
      <div class="card-label">Humedad Suelo</div>
      <div class="card-value" id="val-hum-soil"><?= (int)$last['humedad_suelo'] ?><span class="unit">%</span></div>
      <div class="card-bar"><div class="card-bar-fill" id="bar-hum-soil" style="width:<?= (int)$last['humedad_suelo'] ?>%;background:var(--accent);"></div></div>
      <div class="card-updated" id="lbl-soil"><?= (int)$last['humedad_suelo'] < 30 ? '⚠ Seco' : 'OK' ?></div>
    </div>
    <div class="sensor-card card-water">
      <span class="card-icon">🌊</span>
      <div class="card-label">Nivel Agua</div>
      <div class="card-value" id="val-water"><?= (int)$last['nivel_agua'] ?><span class="unit">%</span></div>
      <div class="card-bar"><div class="card-bar-fill" id="bar-water" style="width:<?= (int)$last['nivel_agua'] ?>%;background:#00cfff;"></div></div>
      <div class="card-updated" id="lbl-water"><?= (int)$last['nivel_agua'] < 20 ? '⚠ Bajo' : 'OK' ?></div>
    </div>
    <div class="sensor-card card-sound">
      <span class="card-icon">🎙️</span>
      <div class="card-label">Sonido</div>
      <div class="card-value" id="val-sound"><?= (int)$last['sonido'] ?><span class="unit">raw</span></div>
      <div class="card-bar"><div class="card-bar-fill" id="bar-sound" style="width:<?= $s_pct ?>%;background:#ff6bff;"></div></div>
      <div class="card-updated" id="lbl-sound"><?= (int)$last['sonido'] > 700 ? '🔊 Detectado' : '🔇 Silencio' ?></div>
    </div>
    <?php else: ?>
    <div class="no-data" id="no-data-msg">No hay datos de sensores aún.</div>
    <?php endif; ?>
  </div>
  <div class="refresh-info" style="margin-top:10px;">
    Última lectura: <span id="last-ts"><?= $last ? htmlspecialchars((string)$last['created_at']) : '—' ?></span>
    &nbsp;·&nbsp; Actualización automática cada <span>10 seg</span>
    &nbsp;·&nbsp; <span id="countdown" style="color:var(--muted)">10s</span>
  </div>
</div>

<!-- Control LED -->
<div>
  <div class="section-label">Control de dispositivos</div>
  <div class="led-panel">
    <div class="led-info">
      <div class="led-title">LED de control</div>
      <div class="led-desc">
        Controlado desde esta interfaz (Supabase) o automáticamente<br>
        cuando el sensor de sonido supera el umbral de 35.
      </div>
    </div>
    <div class="led-state">
      <div class="led-visual <?= $led_on ? 'on' : 'off' ?>" id="led-visual">
        <?= $led_on ? '💡' : '○' ?>
      </div>
      <div class="led-badge <?= $led_on ? 'on' : 'off' ?>" id="led-badge">
        <?= $led_on ? 'ENCENDIDO' : 'APAGADO' ?>
      </div>
    </div>
    <form method="POST" action="toggle_led.php" id="led-form">
      <input type="hidden" name="estado" id="led-estado" value="<?= $led_on ? '0' : '1' ?>">
      <button type="submit" class="btn-toggle <?= $led_on ? 'btn-off' : 'btn-on' ?>" id="led-btn">
        <?= $led_on ? '⏻ Apagar LED' : '⚡ Encender LED' ?>
      </button>
    </form>
  </div>
</div>

<!-- Gráficas ThingSpeak — los iframes se crean una vez y NUNCA se destruyen -->
<div>
  <div class="section-label">Gráficas en vivo — ThingSpeak</div>
  <?php
    $tsb = "https://thingspeak.com/channels/{$TS_CHANNEL}/charts";
    $tsp = "api_key={$TS_READ_KEY}&dynamic=true&results=60&type=spline&bgcolor=%230a0b0e&color=";
  ?>
  <div class="charts-grid">

    <div class="chart-card">
      <div class="chart-label"><span class="chart-dot" style="background:var(--warn)"></span>Temperatura Aire<span class="chart-field">field1</span></div>
      <div class="chart-frame-wrap" id="wrap-1">
        <iframe id="iframe-1" src="<?= $tsb ?>/1?<?= $tsp ?>ef9f27&title=Temperatura+%28%C2%B0C%29&yaxis=%C2%B0C&xaxis=Hora&width=auto&height=auto"></iframe>
      </div>
    </div>

    <div class="chart-card">
      <div class="chart-label"><span class="chart-dot" style="background:var(--info)"></span>Humedad Aire<span class="chart-field">field2</span></div>
      <div class="chart-frame-wrap" id="wrap-2">
        <iframe id="iframe-2" src="<?= $tsb ?>/2?<?= $tsp ?>4a9eff&title=Humedad+Aire+%28%25%29&yaxis=%25&xaxis=Hora&width=auto&height=auto"></iframe>
      </div>
    </div>

    <div class="chart-card">
      <div class="chart-label"><span class="chart-dot" style="background:var(--accent)"></span>Humedad Suelo<span class="chart-field">field3</span></div>
      <div class="chart-frame-wrap" id="wrap-3">
        <iframe id="iframe-3" src="<?= $tsb ?>/3?<?= $tsp ?>3ddc84&title=Humedad+Suelo+%28%25%29&yaxis=%25&xaxis=Hora&width=auto&height=auto"></iframe>
      </div>
    </div>

    <div class="chart-card">
      <div class="chart-label"><span class="chart-dot" style="background:#00cfff"></span>Nivel Agua<span class="chart-field">field4</span></div>
      <div class="chart-frame-wrap" id="wrap-4">
        <iframe id="iframe-4" src="<?= $tsb ?>/4?<?= $tsp ?>00cfff&title=Nivel+Agua+%28%25%29&yaxis=%25&xaxis=Hora&width=auto&height=auto"></iframe>
      </div>
    </div>

    <div class="chart-card chart-card--wide">
      <div class="chart-label"><span class="chart-dot" style="background:#ff6bff"></span>Nivel de Sonido<span class="chart-field">field5</span></div>
      <div class="chart-frame-wrap" id="wrap-5">
        <iframe id="iframe-5" src="<?= $tsb ?>/5?<?= $tsp ?>ff6bff&title=Sonido+%28raw%29&yaxis=raw&xaxis=Hora&width=auto&height=auto"></iframe>
      </div>
    </div>

  </div>
  <?php if ($TS_CHANNEL === 'TU_CHANNEL_ID'): ?>
  <div class="ts-warning">⚠ Reemplaza <code>$TS_CHANNEL</code> en la parte superior de <code>index.php</code> con tu Channel ID real.</div>
  <?php endif; ?>
</div>

<!-- Historial -->
<div>
  <div class="section-label">Historial reciente</div>
  <div class="table-wrapper">
    <table>
      <thead><tr><th>Fecha / Hora</th><th>Temp °C</th><th>Hum. Aire %</th><th>Hum. Suelo %</th><th>Nivel Agua %</th><th>Sonido</th></tr></thead>
      <tbody id="history-body">
        <?php if (!empty($history)): ?>
        <?php foreach ($history as $r): ?>
        <tr>
          <td class="val-time"><?= htmlspecialchars((string)$r['created_at']) ?></td>
          <td class="val-temp"><?= number_format((float)$r['dht_temp'],1) ?></td>
          <td class="val-hum"><?= number_format((float)$r['dht_hum'],1) ?></td>
          <td class="val-soil"><?= (int)$r['humedad_suelo'] ?></td>
          <td class="val-water"><?= (int)$r['nivel_agua'] ?></td>
          <td class="val-sound"><?= (int)$r['sonido'] ?></td>
        </tr>
        <?php endforeach; ?>
        <?php else: ?>
        <tr><td colspan="6" class="no-data">No hay registros aún.</td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

</main>

<script>
// ── Service Worker (PWA) ─────────────────────────────────────────────────────
if ('serviceWorker' in navigator) {
  window.addEventListener('load', () => {
    navigator.serviceWorker.register('/sw.js')
      .then(() => console.log('SW registrado'))
      .catch(e => console.warn('SW error:', e));
  });
}

// ── Prompt de instalación Android ────────────────────────────────────────────
let deferredPrompt = null;
window.addEventListener('beforeinstallprompt', e => {
  e.preventDefault();
  deferredPrompt = e;
  // Mostrar banner de instalación
  const banner = document.getElementById('install-banner');
  if (banner) banner.style.display = 'flex';
});

document.getElementById('btn-install')?.addEventListener('click', () => {
  if (!deferredPrompt) return;
  deferredPrompt.prompt();
  deferredPrompt.userChoice.then(() => {
    deferredPrompt = null;
    const banner = document.getElementById('install-banner');
    if (banner) banner.style.display = 'none';
  });
});

document.getElementById('btn-install-close')?.addEventListener('click', () => {
  const banner = document.getElementById('install-banner');
  if (banner) banner.style.display = 'none';
});

// ── Reloj ────────────────────────────────────────────────────────────────────
(function clock() {
  document.getElementById('clock').textContent =
    new Date().toLocaleTimeString('es-CO', { hour12: false });
  setTimeout(clock, 1000);
})();

// ── Iframes: marcar como cargados (fade-in + quitar shimmer) ─────────────────
for (let i = 1; i <= 5; i++) {
  const iframe = document.getElementById('iframe-' + i);
  const wrap   = document.getElementById('wrap-' + i);
  if (!iframe) continue;
  iframe.addEventListener('load', function() {
    this.classList.add('loaded');
    wrap.classList.add('ready');
  });
}

// ── Helpers DOM ──────────────────────────────────────────────────────────────
function setText(id, val) {
  const el = document.getElementById(id);
  if (el) { el.classList.add('updating'); el.innerHTML = val; setTimeout(() => el.classList.remove('updating'), 400); }
}
function setStyle(id, prop, val) {
  const el = document.getElementById(id);
  if (el) el.style[prop] = val;
}
function setClass(id, cls1, cls2, use1) {
  const el = document.getElementById(id);
  if (!el) return;
  el.classList.toggle(cls1, use1);
  el.classList.toggle(cls2, !use1);
}

// ── Actualizar tarjetas con datos AJAX (SIN recargar la página ni los iframes) ─
function refreshData() {
  fetch('index.php?ajax=1')
    .then(r => r.json())
    .then(d => {
      // Error banner
      const eb = document.getElementById('error-banner');
      if (d.error) { eb.textContent = '⚠ Error: ' + d.error; eb.style.display = 'block'; }
      else { eb.style.display = 'none'; }

      if (!d.last) return;
      const t = parseFloat(d.last.dht_temp);
      const h = parseFloat(d.last.dht_hum);
      const s = parseInt(d.last.humedad_suelo);
      const w = parseInt(d.last.nivel_agua);
      const n = parseInt(d.last.sonido);

      // Temperatura
      const tColor = t > 30 ? '#e84040' : (t > 20 ? '#f0b429' : '#4a9eff');
      const tLabel = t > 30 ? '🔴 Alta' : (t > 20 ? '🟡 Normal' : '🔵 Baja');
      setText('val-temp', t.toFixed(1) + '<span class="unit">°C</span>');
      setStyle('bar-temp', 'width', Math.min(100, (t/50)*100) + '%');
      setStyle('bar-temp', 'background', tColor);
      setText('lbl-temp', tLabel);

      // Humedad aire
      setText('val-hum-air', h.toFixed(1) + '<span class="unit">%</span>');
      setStyle('bar-hum-air', 'width', h + '%');

      // Humedad suelo
      setText('val-hum-soil', s + '<span class="unit">%</span>');
      setStyle('bar-hum-soil', 'width', s + '%');
      setText('lbl-soil', s < 30 ? '⚠ Seco' : 'OK');

      // Nivel agua
      setText('val-water', w + '<span class="unit">%</span>');
      setStyle('bar-water', 'width', w + '%');
      setText('lbl-water', w < 20 ? '⚠ Bajo' : 'OK');

      // Sonido
      setText('val-sound', n + '<span class="unit">raw</span>');
      setStyle('bar-sound', 'width', Math.min(100, Math.round(n/10.23)) + '%');
      setText('lbl-sound', n > 700 ? '🔊 Detectado' : '🔇 Silencio');

      // Timestamp
      const ts = document.getElementById('last-ts');
      if (ts) ts.textContent = d.last.created_at;

      // LED
      const ledOn = d.led_on;
      const visual = document.getElementById('led-visual');
      const badge  = document.getElementById('led-badge');
      const btn    = document.getElementById('led-btn');
      const estado = document.getElementById('led-estado');
      if (visual) { visual.className = 'led-visual ' + (ledOn ? 'on' : 'off'); visual.textContent = ledOn ? '💡' : '○'; }
      if (badge)  { badge.className  = 'led-badge ' + (ledOn ? 'on' : 'off'); badge.textContent = ledOn ? 'ENCENDIDO' : 'APAGADO'; }
      if (btn)    { btn.className    = 'btn-toggle ' + (ledOn ? 'btn-off' : 'btn-on'); btn.textContent = ledOn ? '⏻ Apagar LED' : '⚡ Encender LED'; }
      if (estado) { estado.value = ledOn ? '0' : '1'; }

      // Historial
      if (d.history && d.history.length) {
        const tbody = document.getElementById('history-body');
        if (tbody) {
          tbody.innerHTML = d.history.map(r =>
            `<tr>
              <td class="val-time">${r.created_at}</td>
              <td class="val-temp">${parseFloat(r.dht_temp).toFixed(1)}</td>
              <td class="val-hum">${parseFloat(r.dht_hum).toFixed(1)}</td>
              <td class="val-soil">${r.humedad_suelo}</td>
              <td class="val-water">${r.nivel_agua}</td>
              <td class="val-sound">${r.sonido}</td>
            </tr>`
          ).join('');
        }
      }
    })
    .catch(err => console.warn('AJAX error:', err));
}

// ── Countdown + refresh automático cada 10s ──────────────────────────────────
let countdown = 10;
const cdEl = document.getElementById('countdown');

setInterval(() => {
  countdown--;
  if (cdEl) cdEl.textContent = countdown + 's';
  if (countdown <= 0) {
    countdown = 10;
    refreshData();
  }
}, 1000);
</script>

</body>
</html>