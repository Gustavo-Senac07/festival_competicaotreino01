<?php

class Participante
{
    public $id, $nome, $email, $cpf, $telefone;
    
    public function __construct(
        $id = null,
        $nome = '',
        $email = '',
        $cpf = '',
        $telefone = ''
    )
    {
        $this->id = $id;
        $this->nome = $nome;
        $this->email = $email;
        $this->cpf = $cpf;
        $this->telefone = $telefone;
    }
}