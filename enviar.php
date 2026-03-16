<?php

declare(strict_types=1);

function respostaJson(string $mensagem, int $statusCode = 200): void
{
    http_response_code($statusCode);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['mensagem' => $mensagem], JSON_UNESCAPED_UNICODE);
    exit;
}

function enviarEmailContato(string $destino, string $assunto, string $mensagem, string $headers, ?string &$erro = null): bool
{
    $erro = null;

    set_error_handler(static function (int $severity, string $message) use (&$erro): bool {
        $erro = $message;
        return true;
    });

    try {
        return mail($destino, $assunto, $mensagem, $headers);
    } finally {
        restore_error_handler();
    }
}

function registrarContatoLocal(string $nome, string $email, string $mensagemContato, ?string $erroMail = null): bool
{
    $diretorio = __DIR__ . '/storage';

    if (!is_dir($diretorio) && !mkdir($diretorio, 0775, true) && !is_dir($diretorio)) {
        return false;
    }

    $arquivo = $diretorio . '/contatos.log';
    $conteudo = sprintf(
        "[%s] Nome: %s | E-mail: %s | Mensagem: %s | Erro mail(): %s%s",
        date('Y-m-d H:i:s'),
        str_replace(["\r", "\n"], ' ', $nome),
        str_replace(["\r", "\n"], ' ', $email),
        str_replace(["\r", "\n"], ' ', $mensagemContato),
        $erroMail !== null && $erroMail !== '' ? $erroMail : 'não informado',
        PHP_EOL
    );

    return file_put_contents($arquivo, $conteudo, FILE_APPEND | LOCK_EX) !== false;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    respostaJson('Método não permitido.', 405);
}

$tipoFormulario = $_POST['tipo_formulario'] ?? '';

if ($tipoFormulario === 'contato') {
    $nome = trim($_POST['nome'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $mensagemContato = trim($_POST['mensagem'] ?? '');

    if ($nome === '' || $email === '' || $mensagemContato === '') {
        respostaJson('Preencha todos os campos do formulário de contato.', 422);
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        respostaJson('E-mail inválido.', 422);
    }

    $destino = 'comercial@simcredito.com.br';
    $assunto = 'Nova mensagem de contato do site';

    $mensagem = "Nome: {$nome}\n"
        . "E-mail: {$email}\n\n"
        . "Mensagem:\n{$mensagemContato}\n";

    $headers = "From: no-reply@seudominio.com\r\n";
    $headers .= "Reply-To: {$email}\r\n";

    $erroMail = null;
    $enviado = enviarEmailContato($destino, $assunto, $mensagem, $headers, $erroMail);

    if (!$enviado) {
        $registradoLocalmente = registrarContatoLocal($nome, $email, $mensagemContato, $erroMail);

        if ($registradoLocalmente) {
            respostaJson('Não foi possível enviar por e-mail agora. Sua mensagem foi registrada e o time comercial fará contato em breve.', 202);
        }

        respostaJson('Não foi possível enviar sua mensagem neste momento.', 500);
    }

    respostaJson('Mensagem enviada com sucesso para a caixa de entrada.');
}

if ($tipoFormulario === 'simulacao') {
    $nome = trim($_POST['nome'] ?? '');
    $telefone = trim($_POST['telefone'] ?? '');
    $beneficio = trim($_POST['beneficio'] ?? '');
    $valor = (float)($_POST['valor'] ?? 0);

    if ($nome === '' || $telefone === '' || $beneficio === '' || $valor <= 0) {
        respostaJson('Preencha corretamente todos os campos da simulação.', 422);
    }

    $dbHost = getenv('DB_HOST') ?: '127.0.0.1';
    $dbPort = getenv('DB_PORT') ?: '3306';
    $dbName = getenv('DB_NAME') ?: 'simcredito';
    $dbUser = getenv('DB_USER') ?: 'root';
    $dbPass = getenv('DB_PASS') ?: '';

    try {
        $dsn = "mysql:host={$dbHost};port={$dbPort};dbname={$dbName};charset=utf8mb4";
        $pdo = new PDO($dsn, $dbUser, $dbPass, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);

        $sql = 'INSERT INTO simulacoes_credito (nome, telefone, beneficio, valor_desejado, criado_em) VALUES (:nome, :telefone, :beneficio, :valor, NOW())';
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':nome' => $nome,
            ':telefone' => $telefone,
            ':beneficio' => $beneficio,
            ':valor' => $valor,
        ]);
    } catch (PDOException $e) {
        respostaJson('Não foi possível salvar a simulação na base de dados.', 500);
    }

    respostaJson('Simulação enviada com sucesso para análise da API.');
}

respostaJson('Tipo de formulário inválido.', 400);
?>