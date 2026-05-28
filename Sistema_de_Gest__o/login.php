<?php
session_start();
if (isset($_SESSION['logado'])) { header('Location: index.php'); exit; }

$erro = '';
if ($_SERVER['REQUEST_METHOD']==='POST') {
    $user = trim($_POST['usuario'] ?? '');
    $pass = trim($_POST['senha'] ?? '');
    if ($user === 'admin' && $pass === 'admin') {
        $_SESSION['logado'] = true;
        $_SESSION['usuario'] = 'admin';
        header('Location: index.php'); exit;
    } else {
        $erro = 'Usuário ou senha inválidos.';
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Vortexa — Login</title>
<style>
*{margin:0;padding:0;box-sizing:border-box;}
body{font-family:'Segoe UI',Arial,sans-serif;background:linear-gradient(135deg,#2c2f33 0%,#3a3d42 50%,#2a2d30 100%);min-height:100vh;display:flex;align-items:center;justify-content:center;}
.login-wrap{width:360px;}

/* Logo */
.logo-block{text-align:center;margin-bottom:32px;}
.logo-symbol{
  display:inline-flex;align-items:center;justify-content:center;
  width:64px;height:64px;border-radius:14px;
  background:linear-gradient(135deg,#4e7ea6,#2c5f82);
  margin-bottom:14px;
  box-shadow:0 4px 18px rgba(78,126,166,.4);
}
.logo-symbol svg{width:34px;height:34px;fill:none;stroke:#fff;stroke-width:2.5;stroke-linecap:round;stroke-linejoin:round;}
.logo-name{font-size:2rem;font-weight:800;letter-spacing:3px;color:#e8eaed;text-transform:uppercase;line-height:1;}
.logo-name span{color:#a0c4e8;}
.logo-tagline{font-size:.68rem;letter-spacing:4px;color:#7a8a9a;text-transform:uppercase;margin-top:6px;}

/* Card */
.card{background:#fff;border-radius:8px;padding:34px 32px;box-shadow:0 8px 32px rgba(0,0,0,.3);}
.card h2{font-size:.9rem;color:#555;font-weight:500;margin-bottom:22px;text-align:center;letter-spacing:.5px;}
.form-group{margin-bottom:16px;}
.form-group label{display:block;font-size:.72rem;text-transform:uppercase;letter-spacing:.8px;color:#777;margin-bottom:5px;}
.form-group input{width:100%;padding:10px 12px;border:1px solid #ccc;border-radius:5px;font-size:.9rem;background:#f8f9fa;color:#2c2c2c;outline:none;transition:border .15s;}
.form-group input:focus{border-color:#7a9fc0;background:#fff;}
.btn-login{width:100%;padding:11px;background:linear-gradient(135deg,#4e7ea6,#3d6a8a);color:#fff;border:none;border-radius:5px;font-size:.92rem;font-weight:700;letter-spacing:1px;cursor:pointer;margin-top:6px;transition:opacity .15s;}
.btn-login:hover{opacity:.9;}
.erro{background:#fce8e8;color:#a03a38;border:1px solid #f0b8b8;border-radius:4px;padding:9px 12px;font-size:.82rem;margin-bottom:16px;text-align:center;}
footer{text-align:center;padding:18px 0 10px;font-size:.72rem;color:#556;margin-top:20px;}
</style>
</head>
<body>
<div class="login-wrap">
  <div class="logo-block">
    <div class="logo-symbol">
      <svg viewBox="0 0 24 24"><polygon points="12 2 19 7 19 17 12 22 5 17 5 7"/><line x1="12" y1="2" x2="12" y2="22"/><line x1="5" y1="7" x2="19" y2="17"/><line x1="19" y1="7" x2="5" y2="17"/></svg>
    </div>
    <div class="logo-name">Vor<span>te</span>xa</div>
    <div class="logo-tagline">Gestão Modular</div>
  </div>
  <div class="card">
    <h2>Acesse sua conta</h2>
    <?php if($erro): ?><div class="erro"><?= htmlspecialchars($erro) ?></div><?php endif; ?>
    <form method="POST" action="login.php">
      <div class="form-group">
        <label>Usuário</label>
        <input type="text" name="usuario" required autocomplete="username" placeholder="admin">
      </div>
      <div class="form-group">
        <label>Senha</label>
        <input type="password" name="senha" required autocomplete="current-password" placeholder="••••••">
      </div>
      <button type="submit" class="btn-login">ENTRAR</button>
    </form>
  </div>
  <footer>Desenvolvido por: Luiz</footer>
</div>
</body>
</html>