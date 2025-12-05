<?php
/**
 * ARQUIVO: ged.php
 * DESCRIÇÃO: Versão Unificada e Segura do GED.
 * - Lógica de Upload e Listagem agora disponível para perfil 'user' (RH).
 * - Exclusão de documentos continua restrita ao 'admin'.
 */

// =================================================================
// 1. INCLUSÃO DE ARQUIVOS BASE E INICIALIZAÇÃO
// =================================================================
require_once('config.php');   
require_once('auth.php');     
require_once('conexao.php');  

if (!isset($pdo) || !($pdo instanceof PDO)) {
    die("Erro Crítico: O objeto de conexão \$pdo não está disponível.");
}

// Diretório onde os arquivos serão salvos no servidor
define('UPLOAD_DIR', 'documentos/ged/'); 

if (!is_dir(UPLOAD_DIR)) {
    if (!mkdir(UPLOAD_DIR, 0755, true)) {
        die("Erro: Não foi possível criar o diretório de upload. Verifique as permissões de pasta.");
    }
}

$mensagem_status = '';


// =================================================================
// 2. CARREGAR DADOS DO USUÁRIO LOGADO (para segurança e exibição)
// =================================================================
$usuario_logado = null;
if (isset($_SESSION['usuario_id'])) {
    try {
        $stmt = $pdo->prepare("SELECT nome, perfil, id_setor FROM usuarios WHERE id = :id");
        $stmt->execute([':id' => $_SESSION['usuario_id']]);
        $usuario_logado = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$usuario_logado) {
             session_destroy();
             header("Location: login.php");
             exit();
        }
    } catch (\PDOException $e) {
        die("Erro Crítico ao carregar dados do usuário: " . $e->getMessage());
    }
} else {
    header("Location: login.php");
    exit();
}

$is_admin = (isset($usuario_logado['perfil']) && $usuario_logado['perfil'] === 'admin');
$id_setor_usuario = $usuario_logado['id_setor'];
$id_setor_logado = $id_setor_usuario; 

// 🚨 NOVO: Flag que determina quem PODE FAZER UPLOAD. 
// Definimos que 'admin' e 'user' podem (atendendo ao pedido do RH).
$pode_fazer_upload = ($is_admin || $usuario_logado['perfil'] === 'user');


// =================================================================
// 3. LÓGICA DE EXCLUSÃO DE DOCUMENTOS (RESTRITO AO ADMIN)
// =================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['excluir_documento_id'])) {
    
    // Verificação de segurança: Apenas ADMIN pode excluir
    if (!$is_admin) {
        $mensagem_status = '<div class="alerta-erro">Apenas administradores podem excluir documentos.</div>';
    } else {
        $documento_id = (int)$_POST['excluir_documento_id'];
        
        try {
            $pdo->beginTransaction();

            $stmt_select = $pdo->prepare("SELECT caminho_arquivo FROM documentos_ged WHERE id = :id");
            $stmt_select->execute([':id' => $documento_id]);
            $documento = $stmt_select->fetch();

            if ($documento) {
                $caminho_arquivo = $documento['caminho_arquivo'];

                $stmt_delete = $pdo->prepare("DELETE FROM documentos_ged WHERE id = :id");
                $stmt_delete->execute([':id' => $documento_id]);
                
                if ($stmt_delete->rowCount() > 0) {
                    if (file_exists($caminho_arquivo) && unlink($caminho_arquivo)) {
                        $mensagem_status = '<div class="alerta-sucesso">Documento e arquivo excluídos com sucesso!</div>';
                    } else {
                        $mensagem_status = '<div class="alerta-sucesso">Documento excluído do banco, mas o arquivo físico não foi encontrado/removido.</div>';
                    }
                } else {
                    $mensagem_status = '<div class="alerta-erro">Erro: Documento não encontrado no banco de dados.</div>';
                }
            } else {
                $mensagem_status = '<div class="alerta-erro">Erro: Documento não encontrado para exclusão.</div>';
            }

            $pdo->commit();

        } catch (\PDOException $e) {
            if ($pdo->inTransaction()) {
                 $pdo->rollBack();
            }
            $mensagem_status = '<div class="alerta-erro">Erro ao excluir documento: ' . $e->getMessage() . '</div>';
        }
    }
}


// =================================================================
// 4. LÓGICA DE UPLOAD DE DOCUMENTOS (DISPONÍVEL PARA USER E ADMIN)
// =================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['arquivo_upload'])) {
    
    // Verificação de segurança: Apenas quem pode fazer upload executa o restante do bloco
    if (!$pode_fazer_upload) {
        $mensagem_status = '<div class="alerta-erro">Você não tem permissão para realizar o upload de documentos.</div>';
    } else {
    
        $id_categoria = (int)$_POST['id_categoria'];
        $setores_acesso = isset($_POST['setores_acesso']) ? (array)$_POST['setores_acesso'] : []; 
        $descricao    = $_POST['descricao'];

        if (empty($setores_acesso)) {
             $mensagem_status = '<div class="alerta-erro">Selecione pelo menos um Setor para liberar o acesso ao documento.</div>';
        } else {
            
            $arquivo = $_FILES['arquivo_upload'];
            $nome_original = $arquivo['name'];
            $extensao = strtolower(pathinfo($nome_original, PATHINFO_EXTENSION));
            $nome_salvo = uniqid('GED_') . '_' . time() . '.' . $extensao; 
            $caminho_completo = UPLOAD_DIR . $nome_salvo;

            $extensoes_permitidas = ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'jpg', 'jpeg', 'png'];
            
            if (!in_array($extensao, $extensoes_permitidas)) {
                $mensagem_status = '<div class="alerta-erro">Erro: Tipo de arquivo **não permitido**.</div>';
            } elseif ($arquivo['error'] !== UPLOAD_ERR_OK) {
                $mensagem_status = '<div class="alerta-erro">Erro no upload: Código ' . $arquivo['error'] . '</div>';
            } else {
                if (move_uploaded_file($arquivo['tmp_name'], $caminho_completo)) {
                    try {
                        $pdo->beginTransaction(); 
        
                        // 1. Insere o Documento principal
                        $sql = "INSERT INTO documentos_ged 
                                (nome_original, nome_salvo, caminho_arquivo, id_categoria, descricao) 
                                VALUES (:nome_original, :nome_salvo, :caminho_arquivo, :id_categoria, :descricao)";
                        
                        $stmt = $pdo->prepare($sql);
                        $stmt->execute([
                            ':nome_original' => $nome_original,
                            ':nome_salvo' => $nome_salvo,
                            ':caminho_arquivo' => $caminho_completo,
                            ':id_categoria' => $id_categoria,
                            ':descricao' => $descricao
                        ]);
                        
                        // Obtém o ID do documento recém-inserido
                        $id_documento = $pdo->lastInsertId();
                        
                        // 2. Insere as Permissões na tabela documento_setor
                        $sql_permissoes = "INSERT INTO documento_setor (id_documento, id_setor) VALUES (:id_documento, :id_setor)";
                        $stmt_permissoes = $pdo->prepare($sql_permissoes);

                        foreach ($setores_acesso as $id_setor) {
                            $stmt_permissoes->execute([
                                ':id_documento' => $id_documento,
                                ':id_setor' => (int)$id_setor
                            ]);
                        }
        
                        // Commit da transação se tudo deu certo
                        $pdo->commit(); 
                        $mensagem_status = '<div class="alerta-sucesso">Documento **' . htmlspecialchars($nome_original) . '** salvo e permissões definidas com sucesso!</div>';
                        
                    } catch (\PDOException $e) {
                        if ($pdo->inTransaction()) {
                             $pdo->rollBack();
                        }
                       
                        unlink($caminho_completo);
                        $mensagem_status = '<div class="alerta-erro">Erro ao salvar no banco/permissões: ' . $e->getMessage() . '</div>';
                    }
                } else {
                    $mensagem_status = '<div class="alerta-erro">Erro ao mover o arquivo. Verifique as permissões de pasta.</div>';
                }
            }
        }
    }
}


// =================================================================
// 5. RECUPERAR DADOS PARA EXIBIÇÃO E FILTRAGEM (SEGURA E CORRIGIDA)
// =================================================================

// 5.1 Recuperar Categorias (para o formulário de upload)
$categorias_options = "";
try {
    $stmt_cat = $pdo->query("SELECT id, nome FROM categorias_ged ORDER BY nome");
    while ($cat = $stmt_cat->fetch()) {
        $categorias_options .= "<option value=\"{$cat['id']}\">{$cat['nome']}</option>";
    }
} catch (\PDOException $e) { /* ignore */ }


// 5.2 Recuperar Setores (para o formulário de upload)
$setores_list = [];
try {
    $stmt_setor = $pdo->query("SELECT id, nome FROM setores ORDER BY nome ASC");
    $setores_list = $stmt_setor->fetchAll(PDO::FETCH_ASSOC);
} catch (\PDOException $e) { /* ignore */ }


// 5.3 Construir Consulta de Documentos (SEGURA e CORRIGIDA)
$documentos_result = [];
$select_cols = "dg.id, dg.nome_original AS nome_original, dg.descricao, dg.data_upload, dg.caminho_arquivo, cg.nome as categoria";

if ($is_admin) {
    // Admin: Visualiza todos os documentos.
    $sql_documentos = "SELECT {$select_cols}
                       FROM documentos_ged dg
                       JOIN categorias_ged cg ON dg.id_categoria = cg.id
                       ORDER BY dg.data_upload DESC";
    $params = [];
    $aviso_filtro = "Acesso Total (Admin)";
    
} elseif ($id_setor_logado !== null) {
    // User COM Setor: Visualiza apenas documentos vinculados ao seu setor.
    $sql_documentos = "SELECT DISTINCT {$select_cols}
                       FROM documentos_ged dg
                       JOIN categorias_ged cg ON dg.id_categoria = cg.id
                       JOIN documento_setor ds ON dg.id = ds.id_documento
                       WHERE ds.id_setor = :id_setor_logado
                       ORDER BY dg.data_upload DESC";
    $params = [':id_setor_logado' => $id_setor_logado];
    $aviso_filtro = "Filtrado por Setor: ID " . $id_setor_logado;
} else {
    // User SEM Setor (e não admin): Não vê documentos restritos
    $sql_documentos = "SELECT {$select_cols} FROM documentos_ged dg WHERE 1=0"; 
    $params = [];
    $aviso_filtro = "Nenhum setor associado";
}


try {
    $stmt_doc = $pdo->prepare($sql_documentos);
    $stmt_doc->execute($params);
    $documentos_result = $stmt_doc->fetchAll(PDO::FETCH_ASSOC);
    
} catch (\PDOException $e) {
    $mensagem_status .= '<div class="alerta-erro">Erro ao carregar documentos (Filtro de Setor): ' . $e->getMessage() . '</div>';
}

?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <title>SATEE - Gerenciamento Eletrônico de Documentos (GED)</title>
    <style>
        /* ------------------------------------------- */
        /* CSS BÁSICO DO LAYOUT E ESTILOS */
        /* ------------------------------------------- */
        #main-wrapper { overflow: auto; width: 100%; }
        #sidebar-wrapper { float: left; width: 250px; } 
        #content-wrapper { margin-left: 260px; padding: 10px 20px; }
        .container { width: 100%; max-width: 900px; margin: 20px auto; padding: 20px; border: 1px solid #ddd; }
        h1 { border-bottom: 2px solid #007bff; padding-bottom: 10px; }
        .alerta-sucesso { background-color: #d4edda; color: #155724; padding: 10px; margin-bottom: 15px; border: 1px solid #c3e6cb; border-radius: 4px; }
        .alerta-erro { background-color: #f8d7da; color: #721c24; padding: 10px; margin-bottom: 15px; border: 1px solid #f5c6cb; border-radius: 4px; }
        .alerta-aviso { background-color: #fff3cd; color: #856404; padding: 10px; margin-bottom: 15px; border: 1px solid #ffeeba; border-radius: 4px; }
        table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        th, td { padding: 12px; text-align: left; border: 1px solid #ddd; }
        th { background-color: #e9ecef; }
        
        .btn-acao { 
            display: inline-block; 
            padding: 5px 8px; 
            margin: 0 2px;
            border-radius: 4px;
            text-decoration: none;
            color: white;
            border: none;
            cursor: pointer;
            font-size: 1em; 
        }
        .btn-download { background-color: #28a745; } 
        .btn-excluir { background-color: #dc3545; } 

        .form-group input[type="file"],
        .form-group select, 
        .form-group select[multiple],
        .form-group textarea { 
            width: 100%; 
            max-width: 400px; 
            padding: 10px; 
            box-sizing: border-box; 
            border: 1px solid #ccc; 
            border-radius: 4px; 
            display: block; 
            margin-top: 5px;
        }

        /* MODAL CSS */
        .modal { display: none; position: fixed; z-index: 1000; left: 0; top: 0; width: 100%; height: 100%; overflow: auto; background-color: rgba(0,0,0,0.4); }
        .modal-content { background-color: #fefefe; margin: 15% auto; padding: 20px; border: 1px solid #888; width: 80%; max-width: 400px; text-align: center; border-radius: 8px; }
        .close-btn { color: #aaa; float: right; font-size: 28px; font-weight: bold; }
        .close-btn:hover, .close-btn:focus { color: #000; text-decoration: none; cursor: pointer; }
    </style>
    
    <script>
        function confirmarExclusao(id, nome) {
            document.getElementById('modal-nome-documento').textContent = nome;
            
            if(document.getElementById('form-upload')) {
                document.getElementById('form-upload').reset(); 
            }
            
            document.getElementById('input-excluir-id').value = id;
            
            document.getElementById('confirm-modal').style.display = 'block';
        }

        function fecharModal() {
            document.getElementById('confirm-modal').style.display = 'none';
        }

        window.onclick = function(event) {
            var modal = document.getElementById('confirm-modal');
            if (event.target == modal) {
                modal.style.display = 'none';
            }
        }
    </script>
</head>
<body>

<?php 
include('header.php'); 
?>

<div id="main-wrapper">
    
    <div id="sidebar-wrapper">
        <?php 
        include('sidebar.php'); 
        ?>
    </div>

    <div id="content-wrapper">
        <div class="container">
            <h1><i class="fa fa-envelope"></i> Gerenciamento Eletrônico de Documentos (GED)</h1>
            
            <?php echo $mensagem_status; ?>

            <hr>
            
            <?php if ($pode_fazer_upload): ?>
                <h2><span style="color: #007bff;">1.</span> Upload de Novo Documento</h2>
                <form method="POST" enctype="multipart/form-data" id="form-upload">
                    <div class="form-group">
                        <label for="arquivo_upload">Arquivo:</label>
                        <input type="file" name="arquivo_upload" id="arquivo_upload" required>
                        <small>Extensões permitidas: PDF, DOCX, XLSX, JPG, PNG.</small>
                    </div>
                    
                    <div class="form-group">
                        <label for="id_categoria">Categoria:</label>
                        <select name="id_categoria" id="id_categoria" required>
                            <option value="">-- Selecione uma Categoria --</option>
                            <?php echo $categorias_options; ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="setores_acesso">Acesso Liberado para (Setores):</label>
                        <select name="setores_acesso[]" id="setores_acesso" required multiple size="5" style="height: auto;">
                            <?php if (!empty($setores_list)): ?>
                                <?php foreach($setores_list as $setor): ?>
                                    <option value="<?= $setor['id']; ?>"><?= htmlspecialchars($setor['nome']); ?></option>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <option value="" disabled>⚠️ Cadastre setores primeiro em Configurações.</option>
                            <?php endif; ?>
                        </select>
                        <small>Mantenha a tecla **Ctrl (Windows)** ou **Cmd (Mac)** pressionada para selecionar múltiplos setores.</small>
                    </div>

                    <div class="form-group">
                        <label for="descricao">Descrição:</label>
                        <textarea name="descricao" id="descricao" rows="2" placeholder="Ex: Contrato de 2024 - Turma A"></textarea>
                    </div>
                    
                    <button type="submit" style="background-color: #007bff; color: white; padding: 12px 20px; border: none; border-radius: 4px; cursor: pointer;">
                        Fazer Upload e Arquivar
                    </button>
                </form>
                
                <hr>
            <?php endif; // Fim do IF para Upload ?>

            <h2><span style="color: #007bff;">2.</span> Documentos Arquivados</h2>
            
            <?php 
            // Aviso de filtro de setor 
            if (!$is_admin && $id_setor_logado !== null): 
            ?>
                 <div class="alerta-sucesso" style="background-color: #fff3cd; color: #856404; border-color: #ffeeba;">
                    <i class="fa fa-lock"></i> **Aviso:** Você está visualizando documentos **filtrados** por seu setor (**<?= $aviso_filtro; ?>**).
                </div>
            <?php endif; ?>

            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Nome do Arquivo</th>
                        <th>Categoria</th>
                        <th>Data Upload</th>
                        <th>Ações</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    if (count($documentos_result) > 0) {
                        foreach($documentos_result as $doc) {
                            
                            echo "<tr>";
                            echo "<td>{$doc['id']}</td>";
                            echo "<td>" . htmlspecialchars($doc['nome_original']) . "</td>";
                            echo "<td>{$doc['categoria']}</td>";
                            echo "<td>" . date("d/m/Y H:i", strtotime($doc['data_upload'])) . "</td>";
                            
                            echo "<td>";
                            
                            // Botão/Link de Download
                            echo "<a href='download.php?file_id={$doc['id']}' class='btn-acao btn-download' title='Baixar Documento'>";
                            echo "<i class='fa fa-download' aria-hidden='true'></i>"; 
                            echo "</a>";

                            // Botão de Excluir (SÓ PARA ADMIN)
                            if ($is_admin) {
                                echo "<button type='button' class='btn-acao btn-excluir' title='Excluir Documento'
                                    onclick='confirmarExclusao({$doc['id']}, \"" . addslashes(htmlspecialchars($doc['nome_original'])) . "\")'>";
                                echo "<i class='fa fa-trash' aria-hidden='true'></i>";
                                echo "</button>";
                            }
                            
                            echo "</td>";
                            
                            echo "</tr>";
                        }
                    } else {
                        echo "<tr><td colspan='5'>Nenhum documento arquivado ou liberado para o seu setor.</td></tr>";
                    }
                    ?>
                </tbody>
            </table>
        </div>
    </div>
    
</div>

<div id="confirm-modal" class="modal">
    <div class="modal-content">
        <span class="close-btn" onclick="fecharModal()">&times;</span>
        <h3><i class='fa fa-exclamation-triangle' style='color: #ffc107;'></i> Confirmação de Exclusão</h3>
        <p>Você tem certeza que deseja **EXCLUIR** o documento:</p>
        <p><strong><span id="modal-nome-documento" style="color: #dc3545;"></span></strong></p>
        <p>Esta ação removerá o registro do banco de dados e o arquivo físico.</p>
        
        <form method="POST" style="margin-top: 20px;">
            <input type="hidden" name="excluir_documento_id" id="input-excluir-id" value="">
            <button type="button" onclick="fecharModal()" style="padding: 10px 15px; margin-right: 10px; background-color: #6c757d; color: white; border: none; border-radius: 4px;">Cancelar</button>
            <button type="submit" style="padding: 10px 15px; background-color: #dc3545; color: white; border: none; border-radius: 4px;">Confirmar Exclusão</button>
        </form>
    </div>
</div>

<?php 
// include('footer.php'); 
?>

</body>
</html>