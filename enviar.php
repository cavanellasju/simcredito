<?php

declare(strict_types=1);

function respostaJson(string $mensagem, int $statusCode = 200): void
{
    http_response_code($statusCode);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['mensagem' => $mensagem], JSON_UNESCAPED_UNICODE);
    exit;
}

function enviarEmail(string $destino, string $assunto, string $mensagem, string $headers, ?string &$erro = null): bool
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

function registrarFormularioLocal(string $tipo, array $campos, ?string $erroMail = null): bool
{
    $diretorio = __DIR__ . '/storage';

    if (!is_dir($diretorio) && !mkdir($diretorio, 0775, true) && !is_dir($diretorio)) {
        return false;
    }

    $arquivo = $diretorio . '/contatos.log';
    $partes = [];

    foreach ($campos as $chave => $valor) {
        $partes[] = sprintf('%s: %s', $chave, str_replace(["\r", "\n"], ' ', (string)$valor));
    }

    $conteudo = sprintf(
        "[%s] Tipo: %s | %s | Erro mail(): %s%s",
        date('Y-m-d H:i:s'),
        $tipo,
        implode(' | ', $partes),
        $erroMail !== null && $erroMail !== '' ? $erroMail : 'não informado',
        PHP_EOL
    );

    return file_put_contents($arquivo, $conteudo, FILE_APPEND | LOCK_EX) !== false;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    respostaJson('Método não permitido.', 405);
}

$tipoFormulario = $_POST['tipo_formulario'] ?? '';
$destino = 'comercial@simcredito.com.br';

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

    $assunto = 'Nova mensagem de contato do site';
    $mensagem = "Nome: {$nome}\n"
        . "E-mail: {$email}\n\n"
        . "Mensagem:\n{$mensagemContato}\n";

    $headers = "From: no-reply@simcredito.com.br\r\n";
    $headers .= "Reply-To: {$email}\r\n";

    $erroMail = null;
    $enviado = enviarEmail($destino, $assunto, $mensagem, $headers, $erroMail);

    if (!$enviado) {
        $registradoLocalmente = registrarFormularioLocal('contato', [
            'Nome' => $nome,
            'E-mail' => $email,
            'Mensagem' => $mensagemContato,
        ], $erroMail);

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
    $valor = trim($_POST['valor'] ?? '');

    if ($nome === '' || $telefone === '' || $beneficio === '' || $valor === '') {
        respostaJson('Preencha corretamente todos os campos da simulação.', 422);
    }

    if (!preg_match('/^[0-9]{1,13}$/', $telefone)) {
        respostaJson('Telefone inválido. Use apenas números com até 13 dígitos.', 422);
    }

    $assunto = 'Nova solicitação de simulação de crédito';
    $mensagem = "Nome: {$nome}\n"
        . "Telefone: {$telefone}\n"
        . "Benefício: {$beneficio}\n"
        . "Valor desejado: {$valor}\n";

    $headers = "From: no-reply@simcredito.com.br\r\n";
    $headers .= "Reply-To: {$destino}\r\n";

    $erroMail = null;
    $enviado = enviarEmail($destino, $assunto, $mensagem, $headers, $erroMail);

    if (!$enviado) {
        $registradoLocalmente = registrarFormularioLocal('simulacao', [
            'Nome' => $nome,
            'Telefone' => $telefone,
            'Benefício' => $beneficio,
            'Valor desejado' => $valor,
        ], $erroMail);

        if ($registradoLocalmente) {
            respostaJson('Recebemos sua solicitação e retornaremos em breve.', 202);
        }

        respostaJson('Não foi possível enviar sua simulação neste momento.', 500);
    }

    respostaJson('Recebemos sua solicitação e retornaremos em breve.');
}

respostaJson('Tipo de formulário inválido.', 400);
?>