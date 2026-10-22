<?php

class Inscricao
{
    public $id, $data_inscricao, $participante_id, $atividade_id;

    public function __construct(
        $id = null,
        $data_inscricao = null,
        $participante_id = 0,
        $atividade_id = 0
    )
    {
        $this->id = $id;
        $this->data_inscricao = $data_inscricao;
        $this->participante_id = $participante_id;
        $this->atividade_id = $atividade_id;
    }
}