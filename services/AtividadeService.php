<?php
require_once __DIR__ . '/../config/conexao.php';

class AtividadeService
{
    private $conexao;

    public function __construct()
    {
        $this->conexao = conexao::conectar();
    }

    private function normalizarDataHora($valor)
    {
        $valor = trim((string)$valor);
    
        if ($valor === '') {
            return null;
        }

        $valor = str_replace('T', ' ', $valor);

        $data = DateTime::createFromFormat('Y-m-d H:i', substr($valor, 0, 16));
        $erros = DateTime::getLastErrors();

        if ($data === false || ($erros !== false && ($erros['warning_count'] > 0 || $erros['error_count'] > 0))) {
            return null;
        }

        return $data->format('Y-m-d H:i:s');
    }

    public function listar()
    {
        $sql = "SELECT a.*,
                       DATE_ADD(a.data_hora, INTERVAL a.duracao_minutos MINUTE) AS data_hora_fim,
                       COUNT(i.id) AS inscritos,
                       GREATEST(a.vagas - COUNT(i.id), 0) AS disponiveis
                FROM atividades a
                LEFT JOIN inscricoes i ON i.atividade_id = a.id
                GROUP BY a.id
                ORDER BY a.data_hora";

        return $this->conexao->query($sql)->fetchAll();
    }

    public function buscar($id)
    {
        $stmt = $this->conexao->prepare(
            "SELECT a.*,
                    DATE_ADD(a.data_hora, INTERVAL a.duracao_minutos MINUTE) AS data_hora_fim,
                    COUNT(i.id) AS inscritos,
                    GREATEST(a.vagas - COUNT(i.id), 0) AS disponiveis
             FROM atividades a
             LEFT JOIN inscricoes i ON i.atividade_id = a.id
             WHERE a.id = ?
             GROUP BY a.id"
        );

        $stmt->execute([(int)$id]);
        return $stmt->fetch();
    }

    public function listarInscritos($id)
    {
        $stmt = $this->conexao->prepare(
            "SELECT p.id,
                    p.nome,
                    p.email,
                    p.cpf,
                    p.telefone,
                    i.data_inscricao
             FROM inscricoes i
             INNER JOIN participantes p ON p.id = i.participante_id
             WHERE i.atividade_id = ?
             ORDER BY p.nome"
        );

        $stmt->execute([(int)$id]);
        return $stmt->fetchAll();
    }

    public function cadastrar($dados)
    {
        $titulo = trim($dados['titulo'] ?? '');
        $descricao = trim($dados['descricao'] ?? '');
        $vagas = filter_var($dados['vagas'] ?? null, FILTER_VALIDATE_INT);
        $dataHora = $this->normalizarDataHora($dados['data_hora'] ?? '');
        $duracao = filter_var($dados['duracao_minutos'] ?? null, FILTER_VALIDATE_INT);

        if ($titulo === '' || $vagas === false || $dataHora === null || $duracao === false) {
            return 'Preencha título, vagas, data/hora e duração.';
        }

        if ($vagas < 1) {
            return 'A atividade deve possuir pelo menos 1 vaga.';
        }

        if ($duracao < 1) {
            return 'A duração deve ser de pelo menos 1 minuto.';
        }

        try {
            $stmt = $this->conexao->prepare(
                "INSERT INTO atividades
                    (titulo, descricao, vagas, data_hora, duracao_minutos)
                 VALUES (?, ?, ?, ?, ?)"
            );

            $stmt->execute([
                $titulo,
                $descricao,
                $vagas,
                $dataHora,
                $duracao
            ]);

            return true;
        } catch (PDOException $e) {
            return 'Não foi possível cadastrar a atividade.';
        }
    }

    public function atualizar($id, $dados)
    {
        
        $id = (int)$id;
        $titulo = trim($dados['titulo'] ?? '');
        $descricao = trim($dados['descricao'] ?? '');
        $vagas = filter_var($dados['vagas'] ?? null, FILTER_VALIDATE_INT);
        $dataHora = $this->normalizarDataHora($dados['data_hora'] ?? '');
        $duracao = filter_var($dados['duracao_minutos'] ?? null, FILTER_VALIDATE_INT);

        if ($id <= 0 || $titulo === '' || $vagas === false || $dataHora === null || $duracao === false) {
            return 'Preencha título, vagas, data/hora e duração.';
        }

        if ($vagas < 1) {
            return 'A atividade deve possuir pelo menos 1 vaga.';
        }

        if ($duracao < 1) {
            return 'A duração deve ser de pelo menos 1 minuto.';
        }

        try {
            $this->conexao->beginTransaction();

            /* Bloqueia a atividade enquanto ela é alterada. */
            $stmt = $this->conexao->prepare(
                "SELECT id, vagas
                 FROM atividades
                 WHERE id = ?
                 FOR UPDATE"
            );
            $stmt->execute([$id]);
            $existente = $stmt->fetch();

            if (!$existente) {
                $this->conexao->rollBack();
                return 'Atividade não encontrada.';
            }

            /* RN08: capacidade não pode ficar abaixo dos inscritos. */
            $stmt = $this->conexao->prepare(
                "SELECT COUNT(*)
                 FROM inscricoes
                 WHERE atividade_id = ?"
            );
            $stmt->execute([$id]);
            $inscritos = (int)$stmt->fetchColumn();

            if ($vagas < $inscritos) {
                $this->conexao->rollBack();
                return 'A quantidade de vagas não pode ser menor que o número de participantes inscritos.';
            }

            /*
             * Se a atividade já possui inscritos, uma alteração de horário
             * não pode criar conflito para nenhum deles.
             */
            $stmt = $this->conexao->prepare(
                "SELECT p.nome AS participante,
                        a2.titulo AS atividade_conflito,
                        a2.data_hora,
                        a2.duracao_minutos,
                        DATE_ADD(a2.data_hora, INTERVAL a2.duracao_minutos MINUTE) AS data_hora_fim
                 FROM inscricoes i
                 INNER JOIN participantes p ON p.id = i.participante_id
                 INNER JOIN inscricoes i2 ON i2.participante_id = i.participante_id
                 INNER JOIN atividades a2 ON a2.id = i2.atividade_id
                 WHERE i.atividade_id = ?
                   AND a2.id <> ?
                   AND a2.data_hora < DATE_ADD(?, INTERVAL ? MINUTE)
                   AND DATE_ADD(a2.data_hora, INTERVAL a2.duracao_minutos MINUTE) > ?
                 LIMIT 1"
            );

            $stmt->execute([
                $id,
                $id,
                $dataHora,
                $duracao,
                $dataHora
            ]);

            $conflito = $stmt->fetch();

            if ($conflito) {
                $inicio = date('d/m/Y H:i', strtotime($conflito['data_hora']));
                $fim = date('d/m/Y H:i', strtotime($conflito['data_hora_fim']));

                $this->conexao->rollBack();

                return 'Não é possível alterar o horário: o participante "' .
                    $conflito['participante'] .
                    '" já está inscrito em "' .
                    $conflito['atividade_conflito'] .
                    '" das ' . $inicio . ' às ' . $fim . '.';
            }

            $stmt = $this->conexao->prepare(
                "UPDATE atividades
                 SET titulo = ?
                     descricao = ?,
                     vagas = ?,
                     data_hora = ?,
                     duracao_minutos = ?
                 WHERE id = ?"
            );

            $stmt->execute([
                $titulo,
                $descricao,
                $vagas,
                $dataHora,
                $duracao,
                $id
            ]);

            $this->conexao->commit();
            return true;

        } catch (PDOException $e) {
            if ($this->conexao->inTransaction()) {
                $this->conexao->rollBack();
            }

            return 'Não foi possível atualizar a atividade.';
        }
    }

    public function excluir($id)
    {
        $id = (int)$id;

        /* RN07: atividade com inscrições não pode ser excluída. */
        $stmt = $this->conexao->prepare(
            "SELECT COUNT(*)
             FROM inscricoes
             WHERE atividade_id = ?"
        );
        $stmt->execute([$id]);

        if ((int)$stmt->fetchColumn() > 0) {
            return 'A atividade não pode ser excluída porque possui participantes inscritos.';
        }

        try {
            $stmt = $this->conexao->prepare(
                "DELETE FROM atividades WHERE id = ?"
            );
            $stmt->execute([$id]);

            return $stmt->rowCount() > 0
                ? true
                : 'Atividade não encontrada.';
        } catch (PDOException $e) {
            return 'Não foi possível excluir a atividade.';
        }
    }
}
