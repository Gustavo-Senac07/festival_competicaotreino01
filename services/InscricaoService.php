<?php
require_once __DIR__ . '/../config/conexao.php';

class InscricaoService
{
    private $conexao;

    public function __construct()
    {
        $this->conexao = conexao::conectar();
    }

    public function listar()
    {
        $sql = "SELECT i.id,
                       p.nome AS participante,
                       p.email,
                       p.cpf,
                       a.id AS atividade_id,
                       a.titulo AS atividade,
                       a.data_hora,
                       a.duracao_minutos,
                       DATE_ADD(a.data_hora, INTERVAL a.duracao_minutos MINUTE) AS data_hora_fim,
                       i.data_inscricao
                FROM inscricoes i
                INNER JOIN participantes p ON p.id = i.participante_id
                INNER JOIN atividades a ON a.id = i.atividade_id
                ORDER BY a.data_hora, p.nome";

        return $this->conexao->query($sql)->fetchAll();
    }

    public function listarPorAtividade($atividadeId)
    {
        $stmt = $this->conexao->prepare(
            "SELECT i.id,
                    p.nome AS participante,
                    p.email,
                    p.cpf,
                    i.data_inscricao
             FROM inscricoes i
             INNER JOIN participantes p ON p.id = i.participante_id
             WHERE i.atividade_id = ?
             ORDER BY p.nome"
        );

        $stmt->execute([(int)$atividadeId]);
        return $stmt->fetchAll();
    }

    /**
     * Converte datetime-local (YYYY-MM-DDTHH:MM) para o formato SQL.
     */
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

    public function cadastrar($participanteId, $atividadeId)
    {
        $participanteId = (int)$participanteId;
        $atividadeId = (int)$atividadeId;

        if ($participanteId <= 0 || $atividadeId <= 0) {
            return 'Selecione um participante e uma atividade.';
        }

        try {
            $this->conexao->beginTransaction();

            /*
             * Bloqueia a atividade durante a operação.
             * Isso ajuda a impedir duas inscrições simultâneas de
             * ultrapassarem a quantidade de vagas.
             */
            $stmt = $this->conexao->prepare(
                "SELECT id, titulo, vagas, data_hora, duracao_minutos
                 FROM atividades
                 WHERE id = ?
                 FOR UPDATE"
            );
            $stmt->execute([$atividadeId]);
            $atividade = $stmt->fetch();

            if (!$atividade) {
                $this->conexao->rollBack();
                return 'Atividade não encontrada.';
            }

            $dataHora = $atividade['data_hora'];
            $duracao = (int)$atividade['duracao_minutos'];
            $vagas = (int)$atividade['vagas'];

            if ($duracao <= 0) {
                $this->conexao->rollBack();
                return 'A atividade possui uma duração inválida.';
            }

            /*
             * Bloqueia o participante também. Assim, duas requisições
             * simultâneas para o mesmo participante não conseguem passar
             * pela verificação de conflito ao mesmo tempo.
             */
            $stmt = $this->conexao->prepare(
                "SELECT id
                 FROM participantes
                 WHERE id = ?
                 FOR UPDATE"
            );
            $stmt->execute([$participanteId]);

            if (!$stmt->fetch()) {
                $this->conexao->rollBack();
                return 'Participante não encontrado.';
            }

            /* RN05: não permite a mesma atividade duas vezes. */
            $stmt = $this->conexao->prepare(
                "SELECT COUNT(*)
                 FROM inscricoes
                 WHERE participante_id = ?
                   AND atividade_id = ?"
            );
            $stmt->execute([$participanteId, $atividadeId]);

            if ((int)$stmt->fetchColumn() > 0) {
                $this->conexao->rollBack();
                return 'Participante já está inscrito nesta atividade.';
            }

            /*
             * REGRA DE CONFLITO DE HORÁRIO
             *
             * Uma atividade A conflita com B quando:
             * A.início < B.fim
             * E
             * A.fim > B.início
             *
             * O término é calculado por data_hora + duracao_minutos.
             *
             * Portanto:
             * 09:00-11:00 x 10:00-12:00 => conflito
             * 09:00-11:00 x 11:00-12:30 => permitido
             */
            $stmt = $this->conexao->prepare(
                "SELECT a.titulo,
                        a.data_hora,
                        a.duracao_minutos,
                        DATE_ADD(a.data_hora, INTERVAL a.duracao_minutos MINUTE) AS data_hora_fim
                 FROM inscricoes i
                 INNER JOIN atividades a ON a.id = i.atividade_id
                 WHERE i.participante_id = ?
                   AND a.id <> ?
                   AND a.data_hora < DATE_ADD(?, INTERVAL ? MINUTE)
                   AND DATE_ADD(a.data_hora, INTERVAL a.duracao_minutos MINUTE) > ?
                 ORDER BY a.data_hora
                 LIMIT 1"
            );

            $stmt->execute([
                $participanteId,
                $atividadeId,
                $dataHora,
                $duracao,
                $dataHora
            ]);

            $conflito = $stmt->fetch();

            if ($conflito) {
                $inicio = date('d/m/Y H:i', strtotime($conflito['data_hora']));
                $fim = date('d/m/Y H:i', strtotime($conflito['data_hora_fim']));

                $this->conexao->rollBack();

                return 'Conflito de horário: o participante já está inscrito na atividade "' .
                    $conflito['titulo'] .
                    '" das ' . $inicio . ' às ' . $fim . '.';
            }

            /* RN06: verifica vagas dentro da mesma transação. */
            $stmt = $this->conexao->prepare(
                "SELECT COUNT(*)
                 FROM inscricoes
                 WHERE atividade_id = ?"
            );
            $stmt->execute([$atividadeId]);
            $inscritos = (int)$stmt->fetchColumn();

            if ($inscritos >= $vagas) {
                $this->conexao->rollBack();
                return 'Não há vagas disponíveis para esta atividade.';
            }

            /* Insere a inscrição. */
            $stmt = $this->conexao->prepare(
                "INSERT INTO inscricoes (participante_id, atividade_id)
                 VALUES (?, ?)"
            );
            $stmt->execute([$participanteId, $atividadeId]);

            $this->conexao->commit();
            return true;

        } catch (PDOException $e) {
            if ($this->conexao->inTransaction()) {
                $this->conexao->rollBack();
            }

            return 'Não foi possível realizar a inscrição.';
        }
    }

    public function cancelar($id)
    {
        try {
            $stmt = $this->conexao->prepare(
                "DELETE FROM inscricoes WHERE id = ?"
            );
            $stmt->execute([(int)$id]);

            return $stmt->rowCount() > 0
                ? true
                : 'Inscrição não encontrada.';
        } catch (PDOException $e) {
            return 'Não foi possível cancelar a inscrição.';
        }
    }
}
