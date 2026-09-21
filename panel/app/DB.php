<?php
// Database bootstrap using PDO
class DB {
    private static ?PDO $pdo = null;
    public static function conn(): PDO {
        if (self::$pdo === null) {
            $c = require __DIR__ . '/../config/config.php';
            $dsn = "mysql:host={$c['db']['host']};port={$c['db']['port']};dbname={$c['db']['name']};charset=utf8mb4";
            self::$pdo = new PDO($dsn, $c['db']['user'], $c['db']['pass'], [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]);
        }
        return self::$pdo;
    }
    public static function q(string $sql, array $args = []): PDOStatement {
        $s = self::conn()->prepare($sql);
        $s->execute($args);
        return $s;
    }
    public static function one(string $sql, array $args = []): ?array {
        $r = self::q($sql, $args)->fetch();
        return $r ?: null;
    }
    public static function all(string $sql, array $args = []): array {
        return self::q($sql, $args)->fetchAll();
    }
    public static function insert(string $table, array $data): int {
        $cols = implode(',', array_keys($data));
        $ph = implode(',', array_fill(0, count($data), '?'));
        self::q("INSERT INTO {$table} ({$cols}) VALUES ({$ph})", array_values($data));
        return (int)self::conn()->lastInsertId();
    }
}
