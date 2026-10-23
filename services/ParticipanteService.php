<?php
require_once __DIR__ . '/../config/conexao.php';

class ParticipanteService
{
    private $conexao;

    public function __construct()
    {
        $this->conexao = conexao::conectar();
    }

    public function listar()
    {
        return $this->conexao->query(
            "SELECT * FROM participantes ORDER BY nome"
        )->fetchAll();
    }

    public function buscar($id)
    {
        $stmt = $this->conexao->prepare(
            "SELECT * FROM participantes WHERE id = ?"
        );
        $stmt->execute([$id]);
        return $stmt->fetch();
    }

    public function cadastrar($dados)
    {
        $nome = trim($dados['nome'] ?? '');
        $email = trim($dados['email'] ?? '');
        $cpf = trim($dados['cpf'] ?? '');
        $telefone = trim($dados['telefone'] ?? '');

        if ($nome === '' || $email === '' || $cpf === '') {
            return 'Preencha nome, e-mail e CPF.';
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return 'Informe um e-mail válido.';
        }

        if ($this->emailExiste($email)) {
            return 'Não é permitido cadastrar dois participantes com o mesmo e-mail.';
        }

        if ($this->cpfExiste($cpf)) {
            return 'CPF já cadastrado.';
        }

        try {
            $stmt = $this->conexao->prepare(
                "INSERT INTO participantes (nome, email, cpf, telefone)
                 VALUES (?, ?, ?, ?)"
            );
            $stmt->execute([$nome, $email, $cpf, $telefone]);
            return true;
        } catch (PDOException $e) {
            return 'Não foi possível cadastrar o participante.';
        }
    }

    public function atualizar($id, $dados)
    {
        $nome = trim($dados['nome'] ?? '');
        $email = trim($dados['email'] ?? '');
        $cpf = trim($dados['cpf'] ?? '');
        $telefone = trim($dados['telefone'] ?? '');

        if ($nome === '' || $email === '' || $cpf === '') {
            return 'Preencha nome, e-mail e CPF.';
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return 'Informe um e-mail válido.';
        }

        if ($this->emailExiste($email, $id)) {
            return 'Não é permitido utilizar um e-mail já cadastrado.';
        }

        if ($this->cpfExiste($cpf, $id)) {
            return 'Não é permitido utilizar um cpf já cadastrado';
        }

        try {
            $stmt = $this->conexao->prepare(
                "UPDATE participantes
                SET nome = ?, email = ?, cpf = ?, telefone = ?
                WHERE id = ?"
            );
            $stmt->execute([$nome, $email, $cpf, $telefone, $id]);
            return true;
        } catch (PDOException $e) {
            return 'Não foi possível atualizar o participante.';
        }
    }

    public function excluir($id) 
    {
        try {
            $stmt = $this->conexao->prepare(
                "DELETE FROM participantes WHERE id = ?"
            );
            $stmt->execute([$id]);

            return $stmt->rowCount() > 0 ? true : 'Participante não encontrado.';
        } catch (PDOException $e) {
            return 'Não foi possível excluir o participante.';
        }
    }

    private function emailExiste($email, $ignorarId = null) 
    {
        $sql = "SELECT COUNT(*) FROM participantes WHERE email = ?";
        $params = [$email];

        if ($ignorarId !== null) {
            $sql .= " AND id <> ?";
            $params[] = $ignorarId;
        }

        $stmt = $this->conexao->prepare($sql);
        $stmt->execute($params);

        return (int)$stmt->fetchColumn() > 0;
    }

    private function cpfExiste($cpf, $ignorarId = null)
    {
        $sql = "SELECT COUNT(*) FROM participantes WHERE cpf = ?";
        $params = [$cpf];

        if ($ignorarId !== null) {
            $sql .= " AND id <> ?";
            $params[] = $ignorarId;
        }

        $stmt = $this->conexao->prepare($sql);
        $stmt->execute($params);

        return (int)$stmt->fetchColumn() > 0;
    }
}