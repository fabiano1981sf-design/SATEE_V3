<?php
/**
 * ARQUIVO: perfil.php
 * DESCRIÇÃO: Cadastro e Edição de Usuários com vínculo a Setor.
 * CORREÇÃO: Implementação de verificação de PERFIL ADMIN para gestão de usuários.
 */
require_once('config.php');   
require_once('auth.php');     
require_once('conexao.php');  

if (!isset($pdo) || !($pdo instanceof PDO)) {
    die("Erro Crítico: Objeto de conexão \$pdo não está disponível.");
}

// =================================================================
// CONFIGURAÇÕES BASEADAS NA ESTRUTURA DO BANCO
// =================================================================
// Nome real da coluna de login/username na tabela 'usuarios' é 'email'.
define('COLUNA_LOGIN_DB', 'email');
// Nome real da coluna de senha na tabela 'usuarios' é 'senha_hash'.
define('COLUNA_SENHA_DB', 'senha_hash'); 


$mensagem_status = '';
$usuario_data = ['nome' => '', 'login' => '', 'id_setor' => ''];
$is_edit = false;
$user_id = null;


// =================================================================
// 🚨 NOVO: BUSCAR DADOS DO USUÁRIO LOGADO E CONTROLAR ACESSO 🚨
// =================================================================
$usuario_logado = null;
$is_admin = false;

if (isset($_SESSION['usuario_id'])) {
    try {
        // Busca o nome e o perfil do usuário logado
        $stmt = $pdo->prepare("SELECT nome, perfil FROM usuarios WHERE id = :id");
        $stmt->execute([':id' => $_SESSION['usuario_id']]);
        $usuario_logado = $stmt->fetch();
        
        if ($usuario_logado) {
            // Define a variável de controle
            $is_admin = ($usuario_logado['perfil'] === 'admin');
        } else {
             // Usuário não encontrado, forçar logout por segurança
             session_destroy();
             header("Location: login.php");
             exit();
        }
    } catch (\PDOException $e) {
        $mensagem_status .= '<div class="alerta-erro">Erro ao carregar perfil do usuário logado.</div>';
    }
} 


// =================================================================
// LÓGICA DE SALVAMENTO E EDIÇÃO (CRIAR OU ATUALIZAR)
// Executado APENAS se o usuário for ADMIN
// =================================================================
if ($is_admin && $_SERVER['REQUEST_METHOD'] === 'POST') {
    
    $nome      = trim($_POST['nome']);
    $login_val = trim($_POST['login']); 
    
    $id_setor = (int)$_POST['id_setor']; 
    $id_setor = $id_setor > 0 ? $id_setor : null; 

    $senha    = $_POST['senha'];
    $user_id  = isset($_POST['user_id']) ? (int)$_POST['user_id'] : null;

    if (empty($nome) || empty($login_val)) {
        $mensagem_status = '<div class="alerta-erro">Preencha nome e login.</div>';
    } elseif ($user_id) { // EDIÇÃO
        
        $sql = "UPDATE usuarios SET nome = :nome, " . COLUNA_LOGIN_DB . " = :login_val, id_setor = :id_setor";
        $params = [
            ':nome' => $nome, 
            ':login_val' => $login_val, 
            ':id_setor' => $id_setor,
            ':id' => $user_id
        ];
        
        if (!empty($senha)) {
            $sql .= ", " . COLUNA_SENHA_DB . " = :senha_hash";
            $params[':senha_hash'] = password_hash($senha, PASSWORD_DEFAULT);
        }
        
        $sql .= " WHERE id = :id";
        
        try {
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $mensagem_status = '<div class="alerta-sucesso">Usuário atualizado com sucesso!</div>';
        } catch (\PDOException $e) {
            $mensagem_status = '<div class="alerta-erro">Erro ao atualizar usuário: ' . $e->getMessage() . '</div>';
        }

    } else { // CRIAÇÃO
        if (empty($senha)) {
             $mensagem_status = '<div class="alerta-erro">A senha é obrigatória para um novo usuário.</div>';
        } else {
             try {
                $sql = "INSERT INTO usuarios (nome, " . COLUNA_LOGIN_DB . ", " . COLUNA_SENHA_DB . ", perfil, id_setor) 
                        VALUES (:nome, :login_val, :senha_hash, 'user', :id_setor)";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([
                    ':nome' => $nome, 
                    ':login_val' => $login_val, 
                    ':senha_hash' => password_hash($senha, PASSWORD_DEFAULT),
                    ':id_setor' => $id_setor
                ]);
                $mensagem_status = '<div class="alerta-sucesso">Usuário criado com sucesso!</div>';
            } catch (\PDOException $e) {
                if ($e->getCode() == 23000) {
                    $mensagem_status = '<div class="alerta-erro">Erro: Login/E-mail já existe.</div>';
                } else {
                    $mensagem_status = '<div class="alerta-erro">Erro ao criar usuário: ' . $e->getMessage() . '</div>';
                }
            }
        }
    }
    
    // Recarrega os dados do formulário
    if ($user_id) {
        try {
            $stmt = $pdo->prepare("SELECT nome, " . COLUNA_LOGIN_DB . " as login_db, id_setor FROM usuarios WHERE id = :id");
            $stmt->execute([':id' => $user_id]);
            if ($data = $stmt->fetch()) {
                 $usuario_data = ['nome' => $data['nome'], 'login' => $data['login_db'], 'id_setor' => $data['id_setor']];
            }
            $is_edit = true;
        } catch (\PDOException $e) { /* ignore */ }
    } else {
        $usuario_data = ['nome' => '', 'login' => '', 'id_setor' => '']; 
    }
} 

// =================================================================
// LÓGICA DE CARREGAMENTO PARA EDIÇÃO (GET)
// Executado APENAS se o usuário for ADMIN
// =================================================================
if ($is_admin && $_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['id'])) {
    $user_id = (int)$_GET['id'];
    try {
        $stmt = $pdo->prepare("SELECT nome, " . COLUNA_LOGIN_DB . " as login_db, id_setor FROM usuarios WHERE id = :id");
        $stmt->execute([':id' => $user_id]);
        if ($data = $stmt->fetch()) {
            $usuario_data = ['nome' => $data['nome'], 'login' => $data['login_db'], 'id_setor' => $data['id_setor']];
            $is_edit = true;
        } else {
            $mensagem_status = '<div class="alerta-erro">Usuário não encontrado.</div>';
            $user_id = null;
        }
    } catch (\PDOException $e) {
        $mensagem_status = '<div class="alerta-erro">Erro Crítico ao carregar dados do usuário: ' . $e->getMessage() . '</div>';
        $user_id = null;
    }
}


// =================================================================
// RECUPERAR SETORES PARA O DROPDOWN (Necessário para Admin)
// =================================================================
$setores_list = [];
if ($is_admin) {
    try {
        $stmt = $pdo->query("SELECT id, nome FROM setores ORDER BY nome ASC");
        $setores_list = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (\PDOException $e) {
        $mensagem_status .= '<div class="alerta-erro">Erro ao carregar lista de setores (Tabela setores?).</div>';
    }
}


// =================================================================
// RECUPERAR LISTA DE USUÁRIOS (Executado APENAS se o usuário for ADMIN)
// =================================================================
$usuarios_list = [];
if ($is_admin) {
    try {
        $sql = "SELECT u.id, u.nome, u." . COLUNA_LOGIN_DB . " as login_db, s.nome as setor_nome 
                FROM usuarios u
                LEFT JOIN setores s ON u.id_setor = s.id
                ORDER BY u.nome ASC";
        $stmt = $pdo->query($sql);
        
        while ($user = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $user['login'] = $user['login_db']; 
            unset($user['login_db']); 
            $usuarios_list[] = $user;
        }
    } catch (\PDOException $e) {
        $mensagem_status .= '<div class="alerta-erro">Erro ao carregar lista de usuários: ' . $e->getMessage() . '</div>';
    }
}

?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <title>SATEE - Perfil e Usuários</title>
    <style>
        /* CSS BÁSICO DO LAYOUT E ESTILOS */
        .alerta-sucesso { background-color: #d4edda; color: #155724; padding: 10px; margin-bottom: 15px; border: 1px solid #c3e6cb; border-radius: 4px; }
        .alerta-erro { background-color: #f8d7da; color: #721c24; padding: 10px; margin-bottom: 15px; border: 1px solid #f5c6cb; border-radius: 4px; }
        .alerta-aviso { background-color: #fff3cd; color: #856404; padding: 10px; margin-bottom: 15px; border: 1px solid #ffeeba; border-radius: 4px; }
        .container { width: 90%; max-width: 1000px; margin: 20px auto; padding: 20px; border: 1px solid #ddd; }
        .form-group { margin-bottom: 15px; }
        .form-group label { display: block; margin-bottom: 5px; font-weight: bold; }
        .form-group input[type="text"], 
        .form-group input[type="password"],
        .form-group select { 
            width: 100%; 
            max-width: 400px; 
            padding: 10px; 
            box-sizing: border-box; 
            border: 1px solid #ccc; 
            border-radius: 4px; 
            display: block; 
        }
        .form-group button { padding: 10px 15px; background-color: #007bff; color: white; border: none; border-radius: 4px; cursor: pointer; }
        
        table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        th, td { padding: 10px; text-align: left; border: 1px solid #ddd; }
        th { background-color: #e9ecef; }
        
        .btn-edit { background-color: #ffc107; color: #333; padding: 5px 10px; border: none; border-radius: 4px; text-decoration: none; display: inline-block; }
        
        /* LAYOUT (Para funcionar com Sidebar) */
        #main-wrapper { overflow: auto; width: 100%; }
        #sidebar-wrapper { float: left; width: 250px; } 
        #content-wrapper { margin-left: 260px; padding: 10px 20px; }
    </style>
</head>
<body>

<?php 
// Passa o nome para o header, se o header estiver preparado para recebê-lo
$nome_logado = $usuario_logado ? $usuario_logado['nome'] : 'Visitante';
// Você pode precisar ajustar seu header.php para usar $nome_logado
include('header.php'); 
?>

<div id="main-wrapper">
    
    <div id="sidebar-wrapper">
        <?php include('sidebar.php'); ?>
    </div>

    <div id="content-wrapper">
        <div class="container">
            
            <h1><i class="fa fa-user"></i> Olá, <?= htmlspecialchars($nome_logado); ?>!</h1>
            
            <?php echo $mensagem_status; ?>
            
            <hr>
            
            <?php if ($is_admin): ?>
            
                <h2><?= $is_edit ? 'Editar Usuário (ID: ' . $user_id . ')' : 'Novo Cadastro de Usuário'; ?></h2>
                <form method="POST">
                    <?php if ($is_edit): ?>
                        <input type="hidden" name="user_id" value="<?= $user_id; ?>">
                    <?php endif; ?>
                    
                    <div class="form-group">
                        <label for="nome">Nome Completo:</label>
                        <input type="text" name="nome" id="nome" value="<?= htmlspecialchars($usuario_data['nome']); ?>" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="login">Login/E-mail:</label>
                        <input type="text" name="login" id="login" value="<?= htmlspecialchars($usuario_data['login']); ?>" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="id_setor">Setor de Acesso:</label>
                        <select name="id_setor" id="id_setor">
                            <option value="0">-- Nenhum (Admin/Global) --</option>
                            <?php foreach($setores_list as $setor): ?>
                                <option value="<?= $setor['id']; ?>" 
                                    <?= $setor['id'] == $usuario_data['id_setor'] ? 'selected' : ''; ?>>
                                    <?= htmlspecialchars($setor['nome']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <small>Define quais documentos do GED o usuário poderá visualizar. Selecione "Nenhum" para acesso total.</small>
                    </div>
                    
                    <div class="form-group">
                        <label for="senha">Senha: <?= $is_edit ? '(Deixe em branco para não alterar)' : ''; ?></label>
                        <input type="password" name="senha" id="senha" <?= $is_edit ? '' : 'required'; ?>>
                    </div>
                    
                    <button type="submit"><?= $is_edit ? 'Salvar Alterações' : 'Cadastrar Usuário'; ?></button>
                    <?php if ($is_edit): ?>
                        <a href="perfil.php" class="btn-edit" style="background-color: #6c757d; color: white;">Cancelar Edição</a>
                    <?php endif; ?>
                </form>

                <hr>
                
                <h2>Lista de Usuários</h2>
                <table>
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Nome</th>
                            <th>Login/E-mail</th>
                            <th>Setor</th>
                            <th>Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($usuarios_list) > 0): ?>
                            <?php foreach($usuarios_list as $user): ?>
                                <tr>
                                    <td><?= $user['id']; ?></td>
                                    <td><?= htmlspecialchars($user['nome']); ?></td>
                                    <td><?= htmlspecialchars($user['login']); ?></td>
                                    <td><?= htmlspecialchars($user['setor_nome'] ?: 'Nenhum'); ?></td>
                                    <td>
                                        <a href="perfil.php?id=<?= $user['id']; ?>" class="btn-edit">
                                            <i class="fa fa-pencil"></i> Editar
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr><td colspan='5'>Nenhum usuário cadastrado.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            
            <?php else: ?>
            
                <h2>Meu Perfil</h2>
                <div class="alerta-aviso">
                    Você não tem permissão para gerenciar outros usuários.
                    Esta página pode ser utilizada para visualizar seu perfil e, se desejar, alterar sua senha.
                </div>
                <?php
                if ($usuario_logado) {
                    try {
                        // Recarrega os dados do próprio usuário logado para um formulário de edição de senha/nome.
                        $stmt = $pdo->prepare("SELECT nome, " . COLUNA_LOGIN_DB . " as login_db, id_setor FROM usuarios WHERE id = :id");
                        $stmt->execute([':id' => $_SESSION['usuario_id']]);
                        $meu_perfil_data = $stmt->fetch();
                        
                        // Busca o nome do setor
                        $setor_atual = '';
                        if ($meu_perfil_data['id_setor'] !== null) {
                            $stmt_setor = $pdo->prepare("SELECT nome FROM setores WHERE id = :id");
                            $stmt_setor->execute([':id' => $meu_perfil_data['id_setor']]);
                            $setor_atual = $stmt_setor->fetchColumn();
                        }
                        
                        // Exibe os dados do perfil
                        if ($meu_perfil_data): ?>
                            <p><strong>Nome:</strong> <?= htmlspecialchars($meu_perfil_data['nome']); ?></p>
                            <p><strong>E-mail:</strong> <?= htmlspecialchars($meu_perfil_data['login_db']); ?></p>
                            <p><strong>Setor:</strong> <?= htmlspecialchars($setor_atual ?: 'Nenhum/Global'); ?></p>
                            
                            <h3>Alterar Dados ou Senha</h3>
                            <form method="POST">
                                <input type="hidden" name="user_id" value="<?= $_SESSION['usuario_id']; ?>">
                                <div class="form-group">
                                    <label for="nome_self">Nome Completo:</label>
                                    <input type="text" name="nome" id="nome_self" value="<?= htmlspecialchars($meu_perfil_data['nome']); ?>" required>
                                </div>
                                <div class="form-group">
                                    <label for="login_self">Login/E-mail:</label>
                                    <input type="text" name="login" id="login_self" value="<?= htmlspecialchars($meu_perfil_data['login_db']); ?>" readonly>
                                    <small>Seu login não pode ser alterado por aqui.</small>
                                </div>
                                <input type="hidden" name="id_setor" value="<?= $meu_perfil_data['id_setor'] ?: 0; ?>">
                                
                                <div class="form-group">
                                    <label for="senha_self">Nova Senha: (Deixe em branco para não alterar)</label>
                                    <input type="password" name="senha" id="senha_self">
                                </div>
                                
                                <button type="submit">Salvar Alterações do Perfil</button>
                            </form>

                        <?php endif;
                        
                    } catch (\PDOException $e) {
                         echo '<div class="alerta-erro">Erro ao carregar dados do seu perfil: ' . $e->getMessage() . '</div>';
                    }
                }
                ?>
            
            <?php endif; ?>

        </div>
    </div>
    
</div>

</body>
</html>