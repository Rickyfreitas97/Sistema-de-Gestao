<?php
session_start();
if (!isset($_SESSION['logado'])) { header('Location: login.php'); exit; }

// Leitura do banco
function lerDB() {
    if (!file_exists('db.json')) return ['itens'=>[],'registros'=>[],'pessoas'=>[]];
    $f = fopen('db.json','r'); flock($f, LOCK_SH);
    $d = json_decode(fread($f, filesize('db.json')+1), true);
    flock($f, LOCK_UN); fclose($f);
    return $d ?? ['itens'=>[],'registros'=>[],'pessoas'=>[]];
}
function salvarDB($d) {
    $f = fopen('db.json','c+'); flock($f, LOCK_EX);
    ftruncate($f,0); rewind($f);
    fwrite($f, json_encode($d, JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE));
    flock($f, LOCK_UN); fclose($f);
}

$db = lerDB();
$msg = '';
$pagina = $_GET['p'] ?? 'dashboard';

// PROCESSAR ACOES
if ($_SERVER['REQUEST_METHOD']==='POST') {
    $acao = $_POST['acao'] ?? '';

    // Itens
    if ($acao==='add_item') {
        $nome = trim($_POST['nome'] ?? '');
        $valor = floatval($_POST['valor'] ?? 0);
        $qtd = intval($_POST['qtd'] ?? 0);
        $validade = trim($_POST['validade'] ?? '');
        if ($nome!=='') {
            $db['itens'][] = ['id'=>uniqid(),'nome'=>$nome,'valor'=>$valor,'qtd'=>$qtd,'validade'=>$validade,'criado'=>date('Y-m-d')];
            salvarDB($db);
            $msg = 'Item cadastrado.';
        }
    }
    if ($acao==='del_item') {
        $id = $_POST['id'] ?? '';
        $db['itens'] = array_values(array_filter($db['itens'], fn($i)=>$i['id']!==$id));
        salvarDB($db);
    }
    if ($acao==='edit_item') {
        $id = $_POST['id'] ?? '';
        foreach($db['itens'] as &$it) {
            if ($it['id']===$id) {
                $it['nome'] = trim($_POST['nome'] ?? $it['nome']);
                $it['valor'] = floatval($_POST['valor'] ?? $it['valor']);
                $it['qtd'] = intval($_POST['qtd'] ?? $it['qtd']);
                $it['validade'] = trim($_POST['validade'] ?? $it['validade']);
            }
        }
        salvarDB($db);
        $msg = 'Item atualizado.';
    }

    // Registros
    if ($acao==='add_registro') {
        $tipo = $_POST['tipo'] ?? 'entrada';
        $desc = trim($_POST['desc'] ?? '');
        $val = floatval($_POST['valor'] ?? 0);
        $data = trim($_POST['data'] ?? date('Y-m-d'));
        $item_id = trim($_POST['item_id'] ?? '');
        $qtd_saida = intval($_POST['qtd_saida'] ?? 0);

        if ($desc!=='' && $val>0) {
            $db['registros'][] = ['id'=>uniqid(),'tipo'=>$tipo,'desc'=>$desc,'valor'=>$val,'data'=>$data];

            // Se for saída e houver item selecionado, subtrai do estoque
            if ($tipo==='saida' && $item_id!=='' && $qtd_saida>0) {
                foreach($db['itens'] as &$it) {
                    if ($it['id']===$item_id) {
                        $it['qtd'] = max(0, $it['qtd'] - $qtd_saida);
                        break;
                    }
                }
                unset($it);
            }

            salvarDB($db);
            $msg = 'Registro salvo.';
        }
    }
    if ($acao==='del_registro') {
        $id = $_POST['id'] ?? '';
        $db['registros'] = array_values(array_filter($db['registros'], fn($r)=>$r['id']!==$id));
        salvarDB($db);
    }

    // Pessoas
    if ($acao==='add_pessoa') {
        $nome = trim($_POST['nome'] ?? '');
        $contato = trim($_POST['contato'] ?? '');
        if ($nome!=='') {
            $db['pessoas'][] = ['id'=>uniqid(),'nome'=>$nome,'contato'=>$contato,'criado'=>date('Y-m-d')];
            salvarDB($db);
            $msg = 'Pessoa cadastrada.';
        }
    }
    if ($acao==='del_pessoa') {
        $id = $_POST['id'] ?? '';
        $db['pessoas'] = array_values(array_filter($db['pessoas'], fn($p)=>$p['id']!==$id));
        salvarDB($db);
    }

    header('Location: index.php?p='.$pagina.($msg?'&msg='.urlencode($msg):''));
    exit;
}

$msg = urldecode($_GET['msg'] ?? '');

// Calculos
// 'saida' = venda = entrada de caixa (dinheiro entra)
// 'entrada' = compra/despesa = saida de caixa (dinheiro sai)
$entradas = array_sum(array_map(fn($r)=>$r['tipo']==='saida'?$r['valor']:0, $db['registros']));
$saidas   = array_sum(array_map(fn($r)=>$r['tipo']==='entrada'?$r['valor']:0, $db['registros']));
$saldo    = $entradas - $saidas;
$hoje     = date('Y-m-d');
$dt10     = date('Y-m-d', strtotime('+10 days'));

$itens_baixos   = array_filter($db['itens'], fn($i)=>$i['qtd']<=5);
$itens_vencendo = array_filter($db['itens'], fn($i)=>!empty($i['validade']) && $i['validade']<=$dt10 && $i['validade']>=$hoje);

$editando = null;
if (isset($_GET['edit_item'])) {
    foreach($db['itens'] as $it) if($it['id']===$_GET['edit_item']) $editando=$it;
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Vortexa</title>
<style>
*{margin:0;padding:0;box-sizing:border-box;}
body{font-family:'Segoe UI',Arial,sans-serif;background:#d6d8db;color:#2c2c2c;display:flex;min-height:100vh;}

/* Sidebar */
.sidebar{width:220px;min-height:100vh;background:linear-gradient(180deg,#3a3d42 0%,#2c2f33 100%);display:flex;flex-direction:column;position:fixed;top:0;left:0;bottom:0;z-index:100;box-shadow:2px 0 8px rgba(0,0,0,.3);}
.sidebar-logo{padding:28px 20px 22px;border-bottom:1px solid #444;}
.logo-mark{font-size:1.65rem;font-weight:800;letter-spacing:2px;color:#fff;text-transform:uppercase;}
.logo-mark span{color:#a0c4e8;}
.logo-sub{font-size:.65rem;color:#888;letter-spacing:3px;text-transform:uppercase;margin-top:2px;}
.sidebar nav{flex:1;padding:18px 0;}
.sidebar nav a{display:flex;align-items:center;gap:10px;padding:11px 22px;color:#b0b4b8;text-decoration:none;font-size:.88rem;transition:background .15s,color .15s;border-left:3px solid transparent;}
.sidebar nav a:hover,.sidebar nav a.ativo{background:rgba(255,255,255,.07);color:#e8eaed;border-left-color:#a0c4e8;}
.sidebar nav a .ico{font-size:1rem;width:18px;text-align:center;}
.sidebar-foot{padding:16px 20px;font-size:.68rem;color:#555;border-top:1px solid #3a3d42;}

/* Main */
.main{margin-left:220px;flex:1;padding:28px 30px;min-height:100vh;}
.topbar{display:flex;justify-content:space-between;align-items:center;margin-bottom:24px;}
.topbar h1{font-size:1.3rem;font-weight:600;color:#3a3d42;}
.topbar .user{font-size:.8rem;color:#666;display:flex;align-items:center;gap:10px;}
.topbar .user a{color:#5b8db8;text-decoration:none;font-size:.78rem;}

/* Cards */
.cards{display:flex;gap:16px;flex-wrap:wrap;margin-bottom:24px;}
.card{background:#fff;border-radius:6px;padding:20px 24px;flex:1;min-width:160px;box-shadow:0 1px 4px rgba(0,0,0,.1);border-top:3px solid #b0b8c1;}
.card.verde{border-top-color:#5a9e6f;}
.card.vermelho{border-top-color:#c0504d;}
.card.azul{border-top-color:#4e7ea6;}
.card.amarelo{border-top-color:#c8a020;}
.card-label{font-size:.7rem;text-transform:uppercase;letter-spacing:1px;color:#888;margin-bottom:6px;}
.card-val{font-size:1.5rem;font-weight:700;color:#2c2c2c;}

/* Alertas dashboard */
.alertas{display:flex;gap:14px;flex-wrap:wrap;margin-bottom:22px;}
.alerta-box{background:#fff;border-radius:6px;padding:14px 18px;flex:1;min-width:220px;box-shadow:0 1px 4px rgba(0,0,0,.08);}
.alerta-box h4{font-size:.75rem;text-transform:uppercase;letter-spacing:1px;margin-bottom:10px;padding-bottom:6px;border-bottom:1px solid #eee;}
.alerta-box.verm h4{color:#c0504d;}
.alerta-box.amr h4{color:#b89010;}
.alerta-box ul{list-style:none;}
.alerta-box ul li{font-size:.82rem;padding:4px 0;color:#444;border-bottom:1px solid #f3f3f3;}
.alerta-box ul li:last-child{border:none;}
.badge-qtd{display:inline-block;background:#c0504d;color:#fff;border-radius:10px;font-size:.68rem;padding:1px 7px;margin-left:6px;}
.badge-val{display:inline-block;background:#b89010;color:#fff;border-radius:10px;font-size:.68rem;padding:1px 7px;margin-left:6px;}

/* Seções */
.section{background:#fff;border-radius:6px;padding:22px 24px;box-shadow:0 1px 4px rgba(0,0,0,.09);margin-bottom:20px;}
.section h2{font-size:1rem;font-weight:600;margin-bottom:16px;color:#3a3d42;padding-bottom:10px;border-bottom:1px solid #eee;}

/* Forms */
.form-row{display:flex;gap:12px;flex-wrap:wrap;align-items:flex-end;margin-bottom:16px;}
.form-group{display:flex;flex-direction:column;gap:4px;}
.form-group label{font-size:.72rem;color:#666;text-transform:uppercase;letter-spacing:.5px;}
select,input[type=text],input[type=number],input[type=date]{background:#f5f6f7;border:1px solid #ccc;border-radius:4px;padding:7px 10px;font-size:.85rem;color:#2c2c2c;outline:none;transition:border .15s;}
select:focus,input:focus{border-color:#7a9fc0;background:#fff;}
.btn{padding:8px 18px;border:none;border-radius:4px;cursor:pointer;font-size:.83rem;font-weight:600;transition:background .15s;}
.btn-prim{background:#4e7ea6;color:#fff;}
.btn-prim:hover{background:#3d6a8a;}
.btn-danger{background:#c0504d;color:#fff;}
.btn-danger:hover{background:#a03a38;}
.btn-sm{padding:4px 10px;font-size:.75rem;}
.btn-edit{background:#7a8a9a;color:#fff;}
.btn-edit:hover{background:#5a6a7a;}

/* Msg */
.msg-ok{background:#dff0d8;color:#3a6630;border:1px solid #b2d8a0;border-radius:4px;padding:8px 14px;margin-bottom:14px;font-size:.84rem;}

/* Tabela */
table{width:100%;border-collapse:collapse;font-size:.84rem;}
th{background:#f0f2f4;color:#555;font-size:.72rem;text-transform:uppercase;letter-spacing:.5px;padding:9px 12px;text-align:left;border-bottom:2px solid #ddd;}
td{padding:9px 12px;border-bottom:1px solid #eee;color:#333;vertical-align:middle;}
tr:hover td{background:#f8f9fa;}
.tag{display:inline-block;border-radius:3px;padding:2px 8px;font-size:.7rem;font-weight:600;}
.tag-entrada{background:#dff0d8;color:#3a6630;}
.tag-saida{background:#fce8e8;color:#a03a38;}
.tag-alerta{background:#fff3cd;color:#856404;}
.tag-ok{background:#dff0d8;color:#3a6630;}

/* Campos condicionais de saída */
.saida-fields{display:none;gap:12px;flex-wrap:wrap;align-items:flex-end;}
.saida-fields.visivel{display:flex;}

footer{text-align:center;padding:18px 0 10px;font-size:.72rem;color:#555;border-top:1px solid rgba(0,0,0,.08);margin-top:32px;}
</style>
</head>
<body>

<div class="sidebar">
  <div class="sidebar-logo">
    <div class="logo-mark">Vor<span>te</span>xa</div>
    <div class="logo-sub">Gestão modular</div>
  </div>
  <nav>
    <a href="index.php?p=dashboard" class="<?= $pagina==='dashboard'?'ativo':'' ?>"><span class="ico">&#9632;</span> Dashboard</a>
    <a href="index.php?p=itens"     class="<?= $pagina==='itens'?'ativo':'' ?>"><span class="ico">&#9733;</span> Itens</a>
    <a href="index.php?p=registros" class="<?= $pagina==='registros'?'ativo':'' ?>"><span class="ico">&#9654;</span> Registros</a>
    <a href="index.php?p=pessoas"   class="<?= $pagina==='pessoas'?'ativo':'' ?>"><span class="ico">&#9786;</span> Pessoas</a>
    <a href="index.php?p=relatorio" class="<?= $pagina==='relatorio'?'ativo':'' ?>"><span class="ico">&#9776;</span> Relatório</a>
  </nav>
  <div class="sidebar-foot">v1.0 &copy; Vortexa</div>
</div>

<div class="main">
  <div class="topbar">
    <h1><?= ['dashboard'=>'Dashboard','itens'=>'Itens','registros'=>'Registros','pessoas'=>'Pessoas','relatorio'=>'Relatório'][$pagina] ?? 'Vortexa' ?></h1>
    <div class="user">Administrador &nbsp;<a href="logout.php">[Sair]</a></div>
  </div>

  <?php if($msg): ?><div class="msg-ok"><?= htmlspecialchars($msg) ?></div><?php endif; ?>

<?php if($pagina==='dashboard'): ?>
  <div class="cards">
    <div class="card verde"><div class="card-label">Entradas (Vendas)</div><div class="card-val">R$ <?= number_format($entradas,2,',','.') ?></div></div>
    <div class="card vermelho"><div class="card-label">Saídas (Despesas)</div><div class="card-val">R$ <?= number_format($saidas,2,',','.') ?></div></div>
    <div class="card azul"><div class="card-label">Saldo</div><div class="card-val">R$ <?= number_format($saldo,2,',','.') ?></div></div>
    <div class="card"><div class="card-label">Itens</div><div class="card-val"><?= count($db['itens']) ?></div></div>
    <div class="card"><div class="card-label">Pessoas</div><div class="card-val"><?= count($db['pessoas']) ?></div></div>
  </div>

  <div class="alertas">
    <div class="alerta-box verm">
      <h4>&#9888; Estoque Baixo (&le; 5)</h4>
      <?php if(empty($itens_baixos)): ?><p style="font-size:.8rem;color:#aaa;">Nenhum item.</p>
      <?php else: ?><ul><?php foreach($itens_baixos as $i): ?>
        <li><?= htmlspecialchars($i['nome']) ?> <span class="badge-qtd"><?= $i['qtd'] ?> un.</span></li>
      <?php endforeach; ?></ul><?php endif; ?>
    </div>
    <div class="alerta-box amr">
      <h4>&#8987; Vencimento Próximo (&le; 10 dias)</h4>
      <?php if(empty($itens_vencendo)): ?><p style="font-size:.8rem;color:#aaa;">Nenhum item.</p>
      <?php else: ?><ul><?php foreach($itens_vencendo as $i): ?>
        <li><?= htmlspecialchars($i['nome']) ?> <span class="badge-val"><?= $i['validade'] ?></span></li>
      <?php endforeach; ?></ul><?php endif; ?>
    </div>
  </div>

  <div class="section">
    <h2>Últimos Registros</h2>
    <table><tr><th>Data</th><th>Descrição</th><th>Tipo</th><th>Valor</th></tr>
    <?php $ult = array_slice(array_reverse($db['registros']),0,8); foreach($ult as $r): ?>
    <tr>
      <td><?= htmlspecialchars($r['data']) ?></td>
      <td><?= htmlspecialchars($r['desc']) ?></td>
      <td><span class="tag tag-<?= $r['tipo'] ?>"><?= ucfirst($r['tipo']) ?></span></td>
      <td>R$ <?= number_format($r['valor'],2,',','.') ?></td>
    </tr>
    <?php endforeach; ?>
    <?php if(empty($ult)): ?><tr><td colspan="4" style="color:#aaa;text-align:center;padding:18px;">Nenhum registro.</td></tr><?php endif; ?>
    </table>
  </div>

<?php elseif($pagina==='itens'): ?>
  <div class="section">
    <h2><?= $editando ? 'Editar Item' : 'Cadastrar Item' ?></h2>
    <form method="POST" action="index.php?p=itens">
      <input type="hidden" name="acao" value="<?= $editando ? 'edit_item' : 'add_item' ?>">
      <?php if($editando): ?><input type="hidden" name="id" value="<?= $editando['id'] ?>"><?php endif; ?>
      <div class="form-row">
        <div class="form-group"><label>Nome</label><input type="text" name="nome" value="<?= htmlspecialchars($editando['nome'] ?? '') ?>" required placeholder="Ex: Produto X"></div>
        <div class="form-group"><label>Valor (R$)</label><input type="number" name="valor" step="0.01" min="0" value="<?= $editando['valor'] ?? '' ?>" placeholder="0.00"></div>
        <div class="form-group"><label>Quantidade</label><input type="number" name="qtd" min="0" value="<?= $editando['qtd'] ?? '' ?>" placeholder="0"></div>
        <div class="form-group"><label>Validade</label><input type="date" name="validade" value="<?= $editando['validade'] ?? '' ?>"></div>
        <div class="form-group"><label>&nbsp;</label><button class="btn btn-prim" type="submit"><?= $editando ? 'Salvar' : 'Cadastrar' ?></button></div>
        <?php if($editando): ?><div class="form-group"><label>&nbsp;</label><a href="index.php?p=itens" class="btn btn-edit">Cancelar</a></div><?php endif; ?>
      </div>
    </form>
  </div>
  <div class="section">
    <h2>Lista de Itens</h2>
    <table><tr><th>Nome</th><th>Valor</th><th>Qtd</th><th>Validade</th><th>Status</th><th>Ações</th></tr>
    <?php foreach($db['itens'] as $it):
      $baixo = $it['qtd']<=5;
      $venc  = !empty($it['validade']) && $it['validade']<=$dt10 && $it['validade']>=$hoje;
    ?>
    <tr>
      <td><?= htmlspecialchars($it['nome']) ?></td>
      <td>R$ <?= number_format($it['valor'],2,',','.') ?></td>
      <td><?= $it['qtd'] ?> <?= $baixo ? '<span class="tag tag-alerta">Baixo</span>' : '' ?></td>
      <td><?= htmlspecialchars($it['validade'] ?? '-') ?></td>
      <td><?= $venc ? '<span class="tag tag-alerta">Vencendo</span>' : '<span class="tag tag-ok">OK</span>' ?></td>
      <td>
        <a href="index.php?p=itens&edit_item=<?= $it['id'] ?>" class="btn btn-sm btn-edit">Editar</a>
        <form method="POST" action="index.php?p=itens" style="display:inline;" onsubmit="return confirm('Excluir?')">
          <input type="hidden" name="acao" value="del_item"><input type="hidden" name="id" value="<?= $it['id'] ?>">
          <button class="btn btn-sm btn-danger" type="submit">Excluir</button>
        </form>
      </td>
    </tr>
    <?php endforeach; ?>
    <?php if(empty($db['itens'])): ?><tr><td colspan="6" style="color:#aaa;text-align:center;padding:18px;">Nenhum item cadastrado.</td></tr><?php endif; ?>
    </table>
  </div>

<?php elseif($pagina==='registros'): ?>
  <div class="section">
    <h2>Novo Registro</h2>
    <form method="POST" action="index.php?p=registros">
      <input type="hidden" name="acao" value="add_registro">
      <div class="form-row">
        <div class="form-group">
          <label>Tipo</label>
          <select name="tipo" id="sel_tipo" onchange="toggleSaidaFields()">
            <option value="entrada">Entrada (Despesa/Compra)</option>
            <option value="saida">Saída (Venda)</option>
          </select>
        </div>
        <div class="form-group"><label>Descrição</label><input type="text" name="desc" required placeholder="Descreva..."></div>
        <div class="form-group"><label>Valor (R$)</label><input type="number" name="valor" step="0.01" min="0.01" required placeholder="0.00"></div>
        <div class="form-group"><label>Data</label><input type="date" name="data" value="<?= date('Y-m-d') ?>"></div>
        <div class="form-group"><label>&nbsp;</label><button class="btn btn-prim" type="submit">Registrar</button></div>
      </div>
      <div class="saida-fields" id="saida_fields">
        <div class="form-group">
          <label>Item do Estoque</label>
          <select name="item_id">
            <option value="">— Selecione um item —</option>
            <?php foreach($db['itens'] as $it): ?>
            <option value="<?= $it['id'] ?>"><?= htmlspecialchars($it['nome']) ?> (<?= $it['qtd'] ?> em estoque)</option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="form-group">
          <label>Qtd. Vendida / Consumida</label>
          <input type="number" name="qtd_saida" min="1" value="1" placeholder="1">
        </div>
      </div>
    </form>
  </div>
  <div class="section">
    <h2>Histórico de Registros</h2>
    <table><tr><th>Data</th><th>Descrição</th><th>Tipo</th><th>Valor</th><th>Ação</th></tr>
    <?php foreach(array_reverse($db['registros']) as $r): ?>
    <tr>
      <td><?= htmlspecialchars($r['data']) ?></td>
      <td><?= htmlspecialchars($r['desc']) ?></td>
      <td><span class="tag tag-<?= $r['tipo'] ?>"><?= ucfirst($r['tipo']) ?></span></td>
      <td>R$ <?= number_format($r['valor'],2,',','.') ?></td>
      <td>
        <form method="POST" action="index.php?p=registros" style="display:inline;" onsubmit="return confirm('Excluir?')">
          <input type="hidden" name="acao" value="del_registro"><input type="hidden" name="id" value="<?= $r['id'] ?>">
          <button class="btn btn-sm btn-danger" type="submit">Excluir</button>
        </form>
      </td>
    </tr>
    <?php endforeach; ?>
    <?php if(empty($db['registros'])): ?><tr><td colspan="5" style="color:#aaa;text-align:center;padding:18px;">Nenhum registro.</td></tr><?php endif; ?>
    </table>
  </div>

<?php elseif($pagina==='pessoas'): ?>
  <div class="section">
    <h2>Cadastrar Pessoa</h2>
    <form method="POST" action="index.php?p=pessoas">
      <input type="hidden" name="acao" value="add_pessoa">
      <div class="form-row">
        <div class="form-group"><label>Nome</label><input type="text" name="nome" required placeholder="Nome completo"></div>
        <div class="form-group"><label>Contato</label><input type="text" name="contato" placeholder="Telefone ou e-mail"></div>
        <div class="form-group"><label>&nbsp;</label><button class="btn btn-prim" type="submit">Cadastrar</button></div>
      </div>
    </form>
  </div>
  <div class="section">
    <h2>Lista de Pessoas</h2>
    <table><tr><th>Nome</th><th>Contato</th><th>Cadastrado em</th><th>Ação</th></tr>
    <?php foreach($db['pessoas'] as $p): ?>
    <tr>
      <td><?= htmlspecialchars($p['nome']) ?></td>
      <td><?= htmlspecialchars($p['contato'] ?? '-') ?></td>
      <td><?= $p['criado'] ?? '-' ?></td>
      <td>
        <form method="POST" action="index.php?p=pessoas" style="display:inline;" onsubmit="return confirm('Excluir?')">
          <input type="hidden" name="acao" value="del_pessoa"><input type="hidden" name="id" value="<?= $p['id'] ?>">
          <button class="btn btn-sm btn-danger" type="submit">Excluir</button>
        </form>
      </td>
    </tr>
    <?php endforeach; ?>
    <?php if(empty($db['pessoas'])): ?><tr><td colspan="4" style="color:#aaa;text-align:center;padding:18px;">Nenhuma pessoa cadastrada.</td></tr><?php endif; ?>
    </table>
  </div>

<?php elseif($pagina==='relatorio'): ?>
  <div class="cards">
    <div class="card verde"><div class="card-label">Total Entradas (Vendas)</div><div class="card-val">R$ <?= number_format($entradas,2,',','.') ?></div></div>
    <div class="card vermelho"><div class="card-label">Total Saídas (Despesas)</div><div class="card-val">R$ <?= number_format($saidas,2,',','.') ?></div></div>
    <div class="card <?= $saldo>=0?'azul':'vermelho' ?>"><div class="card-label">Saldo Final</div><div class="card-val">R$ <?= number_format($saldo,2,',','.') ?></div></div>
  </div>
  <div class="section">
    <h2>Entradas (Vendas)</h2>
    <table><tr><th>Data</th><th>Descrição</th><th>Valor</th></tr>
    <?php $ents = array_filter($db['registros'],fn($r)=>$r['tipo']==='saida');
    foreach(array_reverse($ents) as $r): ?>
    <tr><td><?= $r['data'] ?></td><td><?= htmlspecialchars($r['desc']) ?></td><td>R$ <?= number_format($r['valor'],2,',','.') ?></td></tr>
    <?php endforeach; ?>
    <?php if(empty($ents)): ?><tr><td colspan="3" style="color:#aaa;text-align:center;padding:14px;">Sem entradas.</td></tr><?php endif; ?>
    </table>
  </div>
  <div class="section">
    <h2>Saídas (Despesas/Compras)</h2>
    <table><tr><th>Data</th><th>Descrição</th><th>Valor</th></tr>
    <?php $sais = array_filter($db['registros'],fn($r)=>$r['tipo']==='entrada');
    foreach(array_reverse($sais) as $r): ?>
    <tr><td><?= $r['data'] ?></td><td><?= htmlspecialchars($r['desc']) ?></td><td>R$ <?= number_format($r['valor'],2,',','.') ?></td></tr>
    <?php endforeach; ?>
    <?php if(empty($sais)): ?><tr><td colspan="3" style="color:#aaa;text-align:center;padding:14px;">Sem saídas.</td></tr><?php endif; ?>
    </table>
  </div>
<?php endif; ?>

  <footer>Desenvolvido por: Luiz</footer>
</div>

<script>
function toggleSaidaFields() {
    var tipo = document.getElementById('sel_tipo');
    var fields = document.getElementById('saida_fields');
    if (tipo && fields) {
        if (tipo.value === 'saida') {
            fields.classList.add('visivel');
        } else {
            fields.classList.remove('visivel');
        }
    }
}
</script>

</body>
</html>