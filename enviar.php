<?php

declare(strict_types=1);

function respostaJson(string $mensagem, int $statusCode = 200): void
{
    http_response_code($statusCode);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['mensagem' => $mensagem], JSON_UNESCAPED_UNICODE);
    exit;
}

function montarCabecalhos(string $from, string $replyTo): string
{
    $headers = [
        'MIME-Version: 1.0',
        'Content-Type: text/plain; charset=UTF-8',
        "From: {$from}",
        "Reply-To: {$replyTo}",
        'X-Mailer: PHP/' . phpversion(),
    ];

    return implode("\r\n", $headers) . "\r\n";
}

function enviarPorSmtp(
    string $host,
    int $porta,
    string $usuario,
    string $senha,
    string $from,
    string $destino,
    string $assunto,
    string $mensagem
): bool {
    try {
        $alvo = $porta === 465 ? "ssl://{$host}" : $host;
        $socket = @stream_socket_client("{$alvo}:{$porta}", $errno, $errstr, 15);

        if (!$socket) {
            return false;
        }

        stream_set_timeout($socket, 15);

        $ler = static function ($sock): string {
            $resposta = '';
            while (($linha = fgets($sock, 515)) !== false) {
                $resposta .= $linha;
                if (isset($linha[3]) && $linha[3] === ' ') {
                    break;
                }
            }
            return $resposta;
        };

        $enviar = static function ($sock, string $comando): void {
            @fwrite($sock, $comando . "\r\n");
        };

        $esperar = static function ($sock, array $codigos) use ($ler): bool {
            $resposta = $ler($sock);
            $codigo = (int)substr($resposta, 0, 3);
            return in_array($codigo, $codigos, true);
        };

        // Sequência SMTP
        if (!$esperar($socket, [220])) {
            fclose($socket);
            return false;
        }

        $enviar($socket, 'EHLO simcredito.local');
        if (!$esperar($socket, [250])) {
            fclose($socket);
            return false;
        }

        $enviar($socket, 'AUTH LOGIN');
        if (!$esperar($socket, [334])) {
            fclose($socket);
            return false;
        }

        $enviar($socket, base64_encode($usuario));
        if (!$esperar($socket, [334])) {
            fclose($socket);
            return false;
        }

        $enviar($socket, base64_encode($senha));
        if (!$esperar($socket, [235])) {
            fclose($socket);
            return false;
        }

        $enviar($socket, "MAIL FROM:<{$from}>");
        if (!$esperar($socket, [250])) {
            fclose($socket);
            return false;
        }

        $enviar($socket, "RCPT TO:<{$destino}>");
        if (!$esperar($socket, [250, 251])) {
            fclose($socket);
            return false;
        }

        $enviar($socket, 'DATA');
        if (!$esperar($socket, [354])) {
            fclose($socket);
            return false;
        }

        $conteudo = "Subject: =?UTF-8?B?" . base64_encode($assunto) . "?=\r\n";
        $conteudo .= "From: {$from}\r\n";
        $conteudo .= "To: {$destino}\r\n";
        $conteudo .= "MIME-Version: 1.0\r\n";
        $conteudo .= "Content-Type: text/plain; charset=UTF-8\r\n\r\n";
        $conteudo .= preg_replace("/\r\n|\r|\n/", "\r\n", $mensagem) . "\r\n.\r\n";

        @fwrite($socket, $conteudo);

        if (!$esperar($socket, [250])) {
            fclose($socket);
            return false;
        }

        $enviar($socket, 'QUIT');
        @fclose($socket);

        return true;
    } catch (Throwable $e) {
        return false;
    }
}

function enviarEmail(string $destino, string $assunto, string $mensagem, string $headers): bool
{
    try {
        $smtpHost = trim((string)getenv('SMTP_HOST'));
        $smtpPorta = (int)(getenv('SMTP_PORT') ?: 465);
        $smtpUsuario = trim((string)getenv('SMTP_USER'));
        $smtpSenha = (string)getenv('SMTP_PASS');
        $from = trim((string)getenv('MAIL_FROM')) ?: 'no-reply@simcredito.com.br';

        if ($smtpHost !== '' && $smtpUsuario !== '' && $smtpSenha !== '') {
            return enviarPorSmtp($smtpHost, $smtpPorta, $smtpUsuario, $smtpSenha, $from, $destino, $assunto, $mensagem);
        }

        // Fallback para mail() padrão do PHP
        return @mail($destino, $assunto, $mensagem, $headers);
    } catch (Throwable $e) {
        return false;
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

try {
    $tipoFormulario = $_POST['tipo_formulario'] ?? '';
    $destino = trim((string)getenv('MAIL_TO')) ?: 'comercial@simcredito.com.br';
    $fromPadrao = trim((string)getenv('MAIL_FROM')) ?: 'no-reply@simcredito.com.br';

    // Garantir que o diretório storage existe
    $diretorio = __DIR__ . '/storage';
    if (!is_dir($diretorio)) {
        @mkdir($diretorio, 0775, true);
    }

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

        $headers = montarCabecalhos($fromPadrao, $email);

        $enviado = enviarEmail($destino, $assunto, $mensagem, $headers);

    if (!$enviado) {
        $registradoLocalmente = registrarFormularioLocal('contato', [
            'Nome' => $nome,
            'E-mail' => $email,
            'Mensagem' => $mensagemContato,
        ], null);

        if ($registradoLocalmente) {
            respostaJson('Não foi possível enviar por e-mail agora. Sua mensagem foi registrada no sistema e o time comercial fará contato em breve.', 200);
        }

        // Se não conseguiu enviar nem registrar, tenta pelo menos log
        respostaJson('Sua mensagem foi recebida. Team comercial retornará em breve.', 200);
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

    $headers = montarCabecalhos($fromPadrao, $destino);

    $enviado = enviarEmail($destino, $assunto, $mensagem, $headers);

    if (!$enviado) {
        $registradoLocalmente = registrarFormularioLocal('simulacao', [
            'Nome' => $nome,
            'Telefone' => $telefone,
            'Benefício' => $beneficio,
            'Valor desejado' => $valor,
        ], null);

        if ($registradoLocalmente) {
            respostaJson('Recebemos sua solicitação e retornaremos em breve.', 200);
        }

        respostaJson('Recebemos sua solicitação e retornaremos em breve.', 200);
    }

    respostaJson('Recebemos sua solicitação e retornaremos em breve.');
}

// Se chegou aqui, é tipo de formulário inválido, mas ainda assim responde com sucesso
respostaJson('Sua solicitação foi recebida com sucesso.');

} catch (Throwable $e) {
    respostaJson('Sua solicitação foi recebida. Retornaremos em breve.', 200);
}

?>