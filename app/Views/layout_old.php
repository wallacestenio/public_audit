<?php
$title = $title ?? 'Auditoria de Chamados';
$view  = $view  ?? null;

$base  = $base  ?? (function(){
    $s = $_SERVER['SCRIPT_NAME'] ?? '/index.php';
    $d = rtrim(str_replace('\\','/',dirname($s)), '/');
    return ($d==='/'||$d==='.')?'':$d;
})();

$user = $_SESSION['user'] ?? null;
$reqPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?? '/';
$hideUserGreeting = ($reqPath === $base . '/audit-entries');
?>
<!doctype html>
<html lang="pt-br">
<head>
  <meta charset="utf-8">
  <title><?= htmlspecialchars($title, ENT_QUOTES, 'UTF-8') ?></title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <script>window.APP_BASE = <?= json_encode($base, JSON_UNESCAPED_SLASHES) ?>;</script>
  <?php if (!empty($form_token_catalog)): ?>
    <script>window.CATALOG_TOKEN = <?= json_encode($form_token_catalog, JSON_UNESCAPED_SLASHES) ?>;</script>
  <?php endif; ?>
  <link rel="stylesheet" href="<?= htmlspecialchars($base, ENT_QUOTES, 'UTF-8') ?>/assets/style.css">
  <style>
    .topbar{display:flex;gap:12px;align-items:center;justify-content:space-between;margin:0 auto 12px;max-width:1100px}
    .topbar a{color:#0f172a;text-decoration:none;font-weight:600}
    .topbar small{color:#64748b}
    .topbar form{margin:0}
  </style>
  <style>
  /* Estado padrão dos segmented (ajuste se já tiver estilos globais) */
  .segmented-btn {
    transition: background-color .15s ease, color .15s ease, border-color .15s ease;
  }

  /* ----- SLA Atingido? (Não) = vermelho com texto branco quando selecionado ----- */
  input[name="sla_met"][value="0"]:checked + label.segmented-btn {
    background-color: #dc2626;   /* vermelho 600 */
    border-color: #dc2626;
    color: #fff;
  }

  /* ----- Chamado Conforme? (Não) = vermelho com texto branco quando selecionado ----- */
  input[name="is_compliant"][value="0"]:checked + label.segmented-btn {
    background-color: #dc2626;   /* vermelho 600 */
    border-color: #dc2626;
    color: #fff;
  }

  /* (opcional) manter o “Sim” com aparência normal quando selecionado */
  input[name="sla_met"][value="1"]:checked + label.segmented-btn,
  input[name="is_compliant"][value="1"]:checked + label.segmented-btn {
    /* Exemplo de estilo de selecionado padrão (ajuste às suas cores) */
    background-color: var(--btn, #2563eb);   /* azul padrão do seu tema, se existir */
    border-color: var(--btn, #2563eb);
    color: #fff;
  }

  /* Acessibilidade: foco visível nos radios desses dois grupos */
  input[name="sla_met"]:focus + label.segmented-btn,
  input[name="is_compliant"]:focus + label.segmented-btn {
    outline: 2px solid #2563eb;
    outline-offset: 2px;
  }
</style>
<style>
  /* ===== Barra fixa ===== */
.top-menu{
  position:fixed;
  top:0;
  left:0;
  right:0;
  height:56px;
  background:linear-gradient(to bottom,#111,#050505);
  z-index:10000;
}

.menu-container{
  max-width:1200px;
  height:100%;
  margin:0 auto;
  padding:0 16px;
  display:flex;
  align-items:center;
  gap:16px;
}

/* Marca */
.menu-brand{
  color:#fff;
  font-weight:700;
}

/* Links - desktop */
.menu-links{
  list-style:none;
  display:flex;
  gap:16px;
  margin:0;
  padding:0;
  align-items:center;
  flex:1;
}

.menu-links a,
.link-btn{
  color:#fff;
  background:none;
  border:0;
  padding:6px 10px;
  cursor:pointer;
  text-decoration:none;
  border-radius:6px;
}

.menu-links a:hover,
.link-btn:hover{
  background:rgba(255,255,255,.15);
}

/* Usuário à direita */
.menu-user{
  margin-left:auto;
}

/* Botão hambúrguer */
.menu-toggle{
  display:none;
  font-size:26px;
  background:none;
  border:0;
  color:#fff;
  cursor:pointer;
}

/* ===== MOBILE ===== */
@media (max-width: 768px){

  .menu-toggle{
    display:block;
    margin-left:auto;
  }

  .menu-links{
    position:absolute;
    top:56px;
    left:0;
    right:0;
    background:#050505;
    flex-direction:column;
    align-items:flex-start;
    padding:12px 16px;
    display:none;
  }

  .menu-links.open{
    display:flex;
  }

  .menu-user{
    margin-left:0;
    margin-top:8px;
    width:100%;
  }

  .menu-links li{
    width:100%;
  }

  .menu-links a,
  .menu-user form{
    width:100%;
  }
}
</style>

<?php if (!empty($_SESSION['user'])): ?>

<nav class="top-menu">
  <div class="menu-container">

    <!-- Marca -->
    
<div class="menu-logo">
   <img src="/assets/img/logos-kyndryl-petrobras.png" alt="Kyndryl Petrobras">

  </div>

    <!-- Botão Hambúrguer (só no mobile) -->
    <button
      class="menu-toggle"
      type="button"
      aria-label="Abrir menu"
      aria-expanded="false">
      ☰
    </button>

    <!-- Menu -->
    <ul class="menu-links">
      <li><a href="<?= $base ?>/"><strong>Formulário</strong></a></li>
      <li><a href="<?= $base ?>/apkia"><strong>APKIA</strong></a></li>
      <li><a href="<?= $base ?>/stats/noncompliance"><strong>Estatísticas</strong></a></li>      
      

      <?php if (($_SESSION['user']['is_admin'] ?? false)): ?>
        <li><a href="<?= $base ?>/admin">Admin</a></li>
      <?php endif; ?>

      <li class="menu-user">
        <form method="post" action="<?= $base ?>/logout">
          <small>
            <strong style="color: #fff;">Olá,</strong>
            <strong style="color: #fff;">
              <?= htmlspecialchars((string)($_SESSION['user']['name'] ?? ''), ENT_QUOTES, 'UTF-8') ?>
            </strong>
          </small>
          <button type="submit" class="link-btn"><strong>Sair</strong></button>
        </form>
      </li>
    </ul>

  </div>
</nav>

<main style="padding-top:72px">

<?php endif; ?>

</head>
<body>
  

  <div class="topbar">
    <div><a href="<?= htmlspecialchars($base, ENT_QUOTES, 'UTF-8') ?>/"></a></div>
    <?php if (!$hideUserGreeting): ?>
      <div>
        <?php if ($user): ?>
          
          
          <?php if ((int)($user['user_type'] ?? 1) === 0): ?>
            &nbsp;|&nbsp;<a href="<?= htmlspecialchars($base, ENT_QUOTES, 'UTF-8') ?>/admin">Admin</a>
          <?php endif; ?>
         
          
        <?php else: ?>
          <a href="<?= htmlspecialchars($base, ENT_QUOTES, 'UTF-8') ?>/login"></a>
        <?php endif; ?>
      </div>
    <?php endif; ?>
  </div>


  <script defer src="<?= htmlspecialchars($base, ENT_QUOTES, 'UTF-8') ?>/assets/scripts.js"></script>
<script>
document.addEventListener('DOMContentLoaded', () => {
  const toggle = document.querySelector('.menu-toggle');
  const menu   = document.querySelector('.menu-links');

  if (!toggle || !menu) return;

  toggle.addEventListener('click', () => {
    const open = menu.classList.toggle('open');
    toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
  });
});
</script>
</body>
</html>