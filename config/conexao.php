<?php

class conexao 
{
    public static function conectar()
    {
        try {
            return new PDO(
                "mysql:host=localhost;dbname=festival_viva01;charset=utf8mb4",
                "root",
                "1234",
                [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
            );
        } catch (PDOException $e) {
            die('Erro na conexão: ' . $e->getMessage());
        }
    }
}