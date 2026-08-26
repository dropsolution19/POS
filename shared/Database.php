<?php

class Database
{
    private static array $instances = [];

    public static function getConnection(string $dbPath): PDO
    {
        $realPath = realpath(dirname($dbPath));
        if ($realPath === false) {
            $dir = dirname($dbPath);
            if (!is_dir($dir)) {
                mkdir($dir, 0777, true);
            }
        }

        if (!isset(self::$instances[$dbPath])) {
            $pdo = new PDO("sqlite:" . $dbPath);
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
            $pdo->exec("PRAGMA foreign_keys = ON;");
            $pdo->exec("PRAGMA journal_mode = WAL;");
            self::$instances[$dbPath] = $pdo;
        }

        return self::$instances[$dbPath];
    }
}
