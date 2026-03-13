<?php

$nome = $_POST['nome'];
$telefone = $_POST['telefone'];
$beneficio = $_POST['beneficio'];
$valor = $_POST['valor'];

$destino = "seuemail@email.com";

$assunto = "Nova simulação de crédito";

$mensagem = "
Nome: $nome
Telefone: $telefone
Benefício: $beneficio
Valor desejado: $valor
";

$headers = "From: site@seudominio.com";

mail($destino, $assunto, $mensagem, $headers);

echo "Solicitação enviada com sucesso!";
?>