<?php

$nome = $_POST['nome'] ?? '';
$email = $_POST['email'] ?? '';
$telefone = $_POST['telefone'] ?? '';

echo "Formulário recebido com sucesso! <br><br>";

echo "Nome: " . $nome . "<br>";
echo "Email: " . $email . "<br>";
echo "Telefone: " . $telefone . "<br>";

?>