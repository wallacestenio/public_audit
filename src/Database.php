<?php
declare(strict_types=1);

namespace Src;

use PDO;

final class Database
{
    private PDO $pdo;

    public function __construct()
    {
        $host = "sql10.freesqldatabase.com";
        $dbname = "sql10821277";
        $user = "sql10821277";
        $pass = "XXQ7FtufPL"; // troque aqui

        $this->pdo = new PDO(
            "mysql:host=$host;dbname=$dbname;charset=utf8mb4",
            $user,
            $pass,
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            ]
        );
    }

    public function pdo(): PDO
    {
        return $this->pdo;
    }
}