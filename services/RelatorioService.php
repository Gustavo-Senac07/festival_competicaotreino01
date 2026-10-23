<?php

require_once __DIR__ . '/../config/conexao.php';

class RelatorioService
{
    private $conexao;

    public function __construct()
    {
        $this->conexao = conexao::conectar();
    }

    public function resumo()
    {
        return [
            'participantes' => (int)$this->conexao->query("SELECT COUNT(*) FROM paticipantes")->fetchColumn(),
            'atividades' => (int)$this->conexao->query("SELECT COUNT(*) FROM atividades")->fetchColumn(),
            'inscricoes' => (int)$this->conexao->query("SELECT COUNT(*) FROM isncricoes")->fetchColumn(),
        ];
    }

    public function atividades()
    {
        $sql = "SELECT a.id,
                       a.titulo,
                       a.descricao,
                       a.vagas,
                       a.data_hora,
                       a.duracao_minutos,
                       DATE_ADD(a.data_hora, INTERVAL a.duracao_minutos MINUTE) AS data_hora_fim,
                       COUNT(i.id) AS inscritos,
                       GREATEST(a.vagas - COUNT(i.id), 0) AS disponiveis,
                       CASE WHEN COUNT(i.id) >= a.vagas THEN 'LOTADA' ELSE 'DISPONÍVEL' END AS situacao
                FROM atividades a
                LEFT JOIN inscricoes i ON i.atividade_id = a.id
                GROUP BY a.id, a.titulo a.descricao, a.vagas, a.data_hora, a.duracao_minutos
                ORDER BY a.data_hora";

        return $this->conexao->query($sql)->fetchAll();
    }

    public function participantes()
    {
        return $this->conexao->query(
            "SELECT id, nome, email, cpf, telefone, criado_em
            FROM participantes 
            ORDER BY nome"
        )->fetchAll();
    }

    public function inscricoes()
    {
        $sql = "SELECT p.nome AS participante,
                       p.email,
                       p.cpf,
                       a.titulo AS atividade,
                       a.data_hora,
                       a.duracao_minutos,
                       DATE_ADD(a.data_hora, INTERVAL a.duracao_minutos MINUTE) AS data_hora_fim,
                       i.data_inscricao
                FROM inscricoes i
                INNER JOIN participantes ON p.id = i.participante_id
                INNER JOIN atividades a ON a.id = i.atividade_id
                ORDER BY a.data_hora, p.nome";

        return $this->conexao->query($sql)->fetchAll();
    }

    public function inscritosPorAtividade($atividadeId)
    {
        $stmt = $this->conexao->prepare(
            "SELECT a.id AS atividade_id,
                    a.titulo,
                    a.data_hora,
                    a.duracao_minutos,
                    DATE_ADD(a.data_hora, INTERVAL a.duracao_minutos MINUTE) AS data_hora_fim,
                    a.vagas,
                    p.nome AS participante,
                    p.email,
                    p.cpf,
                    i.data_inscricao
            FROM inscricoes i
            INNER JOIN participantes p ON p.id = i.participante_id
            INNER JOIN atividades a ON a.id = i.atividade_id
            WHERE a.id = ?
            ORDER BY p.nome"
        );
        $stmt->execute([(int)$atividadeId]);
        return $stmt->fetchAll();
    }

    public function atividadePorId($atividadeId)
    {
        $stmt = $this->conexao->prepare(
            "SELECT id, titulo, descricao, vagas, data_hora, duracao_minutos
            FROM atividades
            WHERE id = ?"
        );
        $stmt->execute([(int)$atividadeId]);
        return $stmt->fetch() ?: null;
    }
}