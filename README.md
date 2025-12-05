Sistema de agenda e acompanhamento de tarefas em equipe.

⚙️ Requisitos do Sistema Certifique-se de que você possui o seguinte software instalado em sua máquina:
Servidor Web (Apache): Para rodar o PHP.

Linguagem de Programação (PHP): Versão 7.4 ou superior, com extensões como PDO ativas (geralmente ativas por padrão).

Banco de Dados (MySQL/MariaDB ou SQLite): Depende do que você configurou no arquivo conexao.php.

Pacote Integrado: Recomenda-se usar XAMPP (Windows, Linux, macOS) ou WAMP (Windows), pois eles instalam o Apache, PHP e MySQL juntos.

📂 Preparação do Ambiente Siga estas etapas para preparar o local dos arquivos do seu sistema:
Inicie o Servidor: Inicie os serviços Apache e MySQL/MariaDB (ou PHP/Apache se for usar SQLite) através do painel de controle do XAMPP/WAMP.

Localize a Pasta Raiz: Navegue até a pasta de documentos do seu servidor:

XAMPP: Geralmente, C:\xampp\htdocs\

WAMP: Geralmente, C:\wamp\www\

Crie a Pasta do Projeto: Dentro da pasta raiz (ex: htdocs), crie uma nova pasta para o seu sistema.

Exemplo: Crie C:\xampp\htdocs\sistema-tarefas

Copie os Arquivos: Copie todos os seus arquivos PHP (index.php, tarefas.php, equipe.php, conexao.php, etc.) para esta nova pasta (sistema-tarefas).

💾 Configuração do Banco de Dados Você precisa criar o banco de dados e as tabelas que o sistema espera.
A. Criação do Banco de Dados (MySQL/MariaDB) Acesse a interface de gerenciamento do banco de dados (ex: phpMyAdmin) no seu navegador, geralmente em http://localhost/phpmyadmin.

Clique em "Novo" ou "Criar Banco de Dados".

Defina um nome para o banco (Ex: sistema_tarefas).

Clique em "Criar".

B. Criação das Estruturas (Tabelas) O sistema requer, no mínimo, as tabelas usuarios e tarefas. Você pode precisar criar um arquivo schema.sql com as seguintes estruturas e importá-lo via phpMyAdmin:

-- Insira um usuário inicial para login (senha: 123456) INSERT INTO usuarios (nome, email, senha, cargo) VALUES ('Admin', 'admin@sistema.com', '$2y$10$tM3Nq8Yc9Gz3L2W9B4S1I.oHh4g2N.i3J2C6X7Y8Z9'); 4. 🔗 Configuração da Aplicação O último passo é garantir que o sistema possa se conectar ao banco de dados que você acabou de criar.

Abra o arquivo de conexão do seu projeto, que é o conexao.php.

Ajuste as variáveis de conexão para corresponderem às configurações do seu servidor local:

PHP
