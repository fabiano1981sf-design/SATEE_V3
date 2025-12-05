<?php
/**
 * ARQUIVO: configuracao.php
 * DESCRIÇÃO: Gerenciamento de Categorias e Setores do sistema GED.
 * AJUSTES: Layout lado a lado, compacto e uso de Flexbox para as colunas.
 */
require_once('config.php');
require_once('auth.php');
require_once('conexao.php'); 

if (!isset($pdo) || !($pdo instanceof PDO)) {
    die("Erro Crítico: O objeto de conexão \$pdo não está disponível.");
}

// 🚨 Verificação de perfil (Geralmente restrito ao Admin)
// Nota: O arquivo original não tinha a restrição de perfil, mas é altamente recomendado
// que esta página seja acessível APENAS para administradores.
// Se você usa o campo 'perfil' na sessão, adicione a verificação aqui.
// Exemplo:
/*
if (!isset($_SESSION['perfil']) || $_SESSION['perfil'] !== 'admin') {
     header('Location: index.php');
     exit;
}
*/


$mensagem_status_cat = '';
$mensagem_status_setor = '';

// =================================================================
// 2. LÓGICA DE GESTÃO DE SETORES (INSERÇÃO E EXCLUSÃO)
// =================================================================

// Lógica de EXCLUSÃO de SETOR
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['excluir_setor_id'])) {
    $excluir_id = (int)$_POST['excluir_setor_id'];
    try {
        $sql = "DELETE FROM setores WHERE id = :id";
        $stmt = $pdo->prepare($sql);
        if ($stmt->execute([':id' => $excluir_id])) {
            if ($stmt->rowCount() > 0) {
                $mensagem_status_setor = '<div class="alerta-sucesso">Setor excluído com sucesso!</div>';
            } else {
                $mensagem_status_setor = '<div class="alerta-erro">Erro: Setor não encontrado.</div>';
            }
        }
    } catch (\PDOException $e) {
        if ($e->getCode() == 23000) {
             $mensagem_status_setor = '<div class="alerta-erro">**ERRO DE INTEGRIDADE**: Este setor possui documentos ou usuários associados. Remova as associações antes de excluir.</div>';
        } else {
             $mensagem_status_setor = '<div class="alerta-erro">Erro ao excluir setor: ' . $e->getMessage() . '</div>';
        }
    }
}

// Lógica de CADASTRO de SETOR
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['novo_setor']) && !isset($_POST['excluir_setor_id'])) {
    $novo_setor = trim($_POST['novo_setor']);
    if (empty($novo_setor)) {
        $mensagem_status_setor = '<div class="alerta-erro">O nome do setor não pode ser vazio.</div>';
    } else {
        try {
            $sql = "INSERT INTO setores (nome) VALUES (:nome)";
            $stmt = $pdo->prepare($sql);
            if ($stmt->execute([':nome' => $novo_setor])) {
                $mensagem_status_setor = '<div class="alerta-sucesso">Setor **' . htmlspecialchars($novo_setor) . '** cadastrado com sucesso!</div>';
            }
        } catch (\PDOException $e) {
            if ($e->getCode() == 23000) {
                 $mensagem_status_setor = '<div class="alerta-erro">Erro: O setor **' . htmlspecialchars($novo_setor) . '** já existe.</div>';
            } else {
                 $mensagem_status_setor = '<div class="alerta-erro">Erro ao cadastrar setor: ' . $e->getMessage() . '</div>';
            }
        }
    }
}


// Lógica de GESTÃO DE CATEGORIAS (INSERÇÃO E EXCLUSÃO)
// Lógica de Exclusão de Categoria
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['excluir_id'])) {
    $excluir_id = (int)$_POST['excluir_id'];
    try {
        $sql = "DELETE FROM categorias_ged WHERE id = :id";
        $stmt = $pdo->prepare($sql);
        if ($stmt->execute([':id' => $excluir_id])) {
            if ($stmt->rowCount() > 0) {
                $mensagem_status_cat = '<div class="alerta-sucesso">Categoria excluída com sucesso!</div>';
            } else {
                $mensagem_status_cat = '<div class="alerta-erro">Erro: Categoria não encontrada ou ID inválido.</div>';
            }
        }
    } catch (\PDOException $e) {
        if ($e->getCode() == 23000) {
             $mensagem_status_cat = '<div class="alerta-erro">**ERRO DE INTEGRIDADE**: Esta categoria possui documentos associados.</div>';
        } else {
             $mensagem_status_cat = '<div class="alerta-erro">Erro ao excluir: ' . $e->getMessage() . '</div>';
        }
    }
}

// Lógica de Cadastro de Categoria
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['nova_categoria']) && !isset($_POST['excluir_id'])) {
    $nova_categoria = trim($_POST['nova_categoria']);
    if (empty($nova_categoria)) {
        $mensagem_status_cat = '<div class="alerta-erro">O nome da categoria não pode ser vazio.</div>';
    } else {
        try {
            $sql = "INSERT INTO categorias_ged (nome) VALUES (:nome)";
            $stmt = $pdo->prepare($sql);
            if ($stmt->execute([':nome' => $nova_categoria])) {
                $mensagem_status_cat = '<div class="alerta-sucesso">Categoria **' . htmlspecialchars($nova_categoria) . '** cadastrada com sucesso!</div>';
            }
        } catch (\PDOException $e) {
            if ($e->getCode() == 23000) {
                 $mensagem_status_cat = '<div class="alerta-erro">Erro: A categoria **' . htmlspecialchars($nova_categoria) . '** já existe.</div>';
            } else {
                 $mensagem_status_cat = '<div class="alerta-erro">Erro ao cadastrar: ' . $e->getMessage() . '</div>';
            }
        }
    }
}


// =================================================================
// 3. RECUPERAR LISTAS PARA EXIBIÇÃO
// =================================================================

// Recuperar Lista de Categorias
$categorias_list = [];
try {
    $stmt = $pdo->query("SELECT id, nome FROM categorias_ged ORDER BY nome ASC");
    $categorias_list = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (\PDOException $e) { /* ignore */ }

// Recuperar Lista de Setores
$setores_list = [];
try {
    $stmt = $pdo->query("SELECT id, nome FROM setores ORDER BY nome ASC");
    $setores_list = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (\PDOException $e) { /* ignore */ }


?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <title>SATEE - Configurações do GED</title>
    <style>
        /* CSS BÁSICO DO LAYOUT E ESTILOS */
        /* Alertas mais compactos */
        .alerta-sucesso { background-color: #d4edda; color: #155724; padding: 8px; margin-bottom: 12px; border: 1px solid #c3e6cb; border-radius: 4px; font-size: 0.9em;}
        .alerta-erro { background-color: #f8d7da; color: #721c24; padding: 8px; margin-bottom: 12px; border: 1px solid #f5c6cb; border-radius: 4px; font-size: 0.9em; }
        .container { width: 100%; max-width: 1100px; margin: 20px auto; padding: 20px; border: 1px solid #ddd; }
        
        /* Layout de Colunas (Flexbox) para LADO A LADO */
        .row { display: flex; flex-wrap: wrap; margin: 0 -10px; }
        .col-6 { flex: 0 0 50%; max-width: 50%; padding: 0 10px; box-sizing: border-box; }
        
        /* Estilos Compactos */
        h1 { font-size: 1.8em; border-bottom: 2px solid #007bff; padding-bottom: 8px; }
        h2 { font-size: 1.3em; margin-top: 15px; margin-bottom: 10px; border-bottom: 1px solid #eee; padding-bottom: 5px; }
        
        /* Formulário Compacto e Alinhamento */
        .form-group { margin-bottom: 15px; }
        .form-group label { display: block; margin-bottom: 3px; font-weight: bold; font-size: 0.95em; }
        .input-group-inline {
            display: flex;
            align-items: center; 
            width: 100%;
            max-width: none;
        }
        .input-group-inline input[type="text"] {
            flex-grow: 1; 
            padding: 8px; /* Menos padding */
            font-size: 0.9em; /* Menor fonte */
            margin-right: 8px;
            box-sizing: border-box;
            border: 1px solid #ccc; 
            border-radius: 4px; 
            max-width: none;
        }
        .input-group-inline button { 
            flex-shrink: 0; 
            padding: 8px 12px; /* Menos padding */
            font-size: 0.9em; /* Menor fonte */
            background-color: #007bff; 
            color: white; 
            border: none; 
            border-radius: 4px; 
            cursor: pointer; 
        }

        /* Tabelas Compactas */
        table { width: 100%; margin-top: 0px; border-collapse: collapse; font-size: 0.9em; }
        th, td { padding: 6px 8px; /* Menos padding */ text-align: left; border: 1px solid #ddd; }
        th { background-color: #f1f1f1; }
        .btn-excluir { background-color: #dc3545; color: white; border: none; padding: 4px 8px; border-radius: 4px; cursor: pointer; font-size: 0.8em; }
        
        /* LAYOUT (Mantém o sidebar) */
        #main-wrapper { overflow: auto; width: 100%; }
        #sidebar-wrapper { float: left; width: 250px; } 
        #content-wrapper { margin-left: 260px; padding: 10px 20px; }

        .clear-float { clear: both; } 
    </style>
    
    <script>
        function confirmarExclusao(tipo, nome) {
            return confirm('Tem certeza que deseja EXCLUIR o(a) ' + tipo + ' "' + nome + '"?');
        }
    </script>
</head>
<body>

<?php 
include('header.php'); 
?>

<div id="main-wrapper">
    
    <div id="sidebar-wrapper">
        <?php include('sidebar.php'); ?>
    </div>

    <div id="content-wrapper">
        <div class="container">
            <h1><i class="fa fa-cog"></i> Configurações do GED</h1>
            
            <hr>
            
            <div class="row">
                
                <div class="col-6">
                    <h2><i class="fa fa-tag"></i> Gestão de Categorias</h2>
            
                    <?php echo $mensagem_status_cat; ?>

                    <form method="POST">
                        <div class="form-group">
                            <label for="nova_categoria">Nome da Categoria:</label>
                            <div class="input-group-inline">
                                <input type="text" name="nova_categoria" id="nova_categoria" required placeholder="Ex: Contratos, Portarias">
                                <button type="submit">Cadastrar</button>
                            </div>
                        </div>
                    </form>

                    <table>
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Nome</th>
                                <th>Ação</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (count($categorias_list) > 0): ?>
                                <?php foreach($categorias_list as $cat): ?>
                                    <tr>
                                        <td><?= $cat['id']; ?></td>
                                        <td><?= htmlspecialchars($cat['nome']); ?></td>
                                        <td>
                                            <form method='POST' onsubmit='return confirmarExclusao("Categoria", "<?= addslashes($cat['nome']); ?>");'>
                                                <input type='hidden' name='excluir_id' value='<?= $cat['id']; ?>'>
                                                <button type='submit' class='btn-excluir'><i class="fa fa-trash"></i></button>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr><td colspan='3'>Nenhuma categoria cadastrada.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <div class="col-6">
                    <h2><i class="fa fa-users"></i> Gestão de Setores</h2>
            
                    <?php echo $mensagem_status_setor; ?>

                    <form method="POST">
                        <div class="form-group">
                            <label for="novo_setor">Nome do Setor:</label>
                            <div class="input-group-inline">
                                <input type="text" name="novo_setor" id="novo_setor" required placeholder="Ex: Financeiro, RH, Marketing">
                                <button type="submit">Cadastrar</button>
                            </div>
                        </div>
                    </form>

                    <table>
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Nome</th>
                                <th>Ação</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (count($setores_list) > 0): ?>
                                <?php foreach($setores_list as $setor): ?>
                                    <tr>
                                        <td><?= $setor['id']; ?></td>
                                        <td><?= htmlspecialchars($setor['nome']); ?></td>
                                        <td>
                                            <form method='POST' onsubmit='return confirmarExclusao("Setor", "<?= addslashes($setor['nome']); ?>");'>
                                                <input type='hidden' name='excluir_setor_id' value='<?= $setor['id']; ?>'>
                                                <button type='submit' class='btn-excluir'><i class="fa fa-trash"></i></button>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr><td colspan='3'>Nenhum setor cadastrado.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
                
            </div> <div class="clear-float"></div>

        </div>
    </div>
    
</div>

</body>
</html>