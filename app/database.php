<?php
function db(): PDO {
    static $pdo;
    if ($pdo) return $pdo;
    if (env('DB_DRIVER', 'sqlite') === 'mysql') {
        $pdo = new PDO('mysql:host='.env('DB_HOST','localhost').';port='.env('DB_PORT','3306').';dbname='.env('DB_NAME').';charset=utf8mb4', env('DB_USER'), env('DB_PASSWORD'));
    } else {
        $path = env('DB_SQLITE_PATH', 'storage/store.sqlite');
        if (!preg_match('~^(?:[A-Za-z]:|/)~', $path)) $path = ROOT.'/'.$path;
        $pdo = new PDO('sqlite:'.$path);
        $pdo->exec('PRAGMA foreign_keys=ON');
        $pdo->exec('PRAGMA busy_timeout=5000');
        $pdo->exec('PRAGMA journal_mode=WAL');
    }
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    $pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);
    return $pdo;
}
function query(string $sql, array $args=[]): PDOStatement { $stmt=db()->prepare($sql); $stmt->execute($args); return $stmt; }
function rows(string $sql, array $args=[]): array { return query($sql,$args)->fetchAll(); }
function row(string $sql, array $args=[]): ?array { return query($sql,$args)->fetch() ?: null; }
function insert_record(string $table, array $data): void {
    $keys=array_keys($data);
    query('INSERT INTO '.$table.' ('.implode(',',$keys).') VALUES ('.implode(',',array_fill(0,count($keys),'?')).')',array_values($data));
}
function update_record(string $table, string $id, array $data): void {
    query('UPDATE '.$table.' SET '.implode(',',array_map(fn($k)=>$k.'=?',array_keys($data))).' WHERE id=?',[...array_values($data),$id]);
}
