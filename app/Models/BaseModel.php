<?php
namespace App\Models;

use PDO;

abstract class BaseModel
{
    public function __construct(protected PDO $pdo) {}
}