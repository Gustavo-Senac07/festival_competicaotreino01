<?php

class Atividade
{
    public $id, $titulo, $descricao, $vagas, $data_hora, $duracao_minutos;

    public function __construct(
        $id = null,
        $titulo = '',
        $descricao = '',
        $vagas = 0,
        $data_hora = '',
        $duracao_minutos = 60
    )
    {
        $this->id = $id;
        $this->titulo = $titulo;
        $this->descricao = $descricao;
        $this->vagas = $vagas;
        $this->data_hora = $data_hora;
        $this->duracao_minutos = $duracao_minutos;
    }
}