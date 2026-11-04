<?php
declare(strict_types=1);

date_default_timezone_set('Asia/Ho_Chi_Minh');
spl_autoload_register(function (string $class): void {
    if (preg_match('/^[A-Za-z]+$/', $class) && is_file(__DIR__ . "/$class.php")) {
        require __DIR__ . "/$class.php";
    }
});

function db(): PDO
{
    static $db;
    if (!$db) {
        $db = new PDO('mysql:host=' . (getenv('DB_HOST') ?: '127.0.0.1') . ';port=' . (getenv('DB_PORT') ?: '3306') . ';dbname=' . (getenv('DB_DATABASE') ?: 'musicbox') . ';charset=utf8mb4', getenv('DB_USERNAME') ?: 'musicbox', getenv('DB_PASSWORD') ?: '', [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);
        $db->exec("SET time_zone = '+07:00'");
    }
    return $db;
}

function rows(string $sql, array $params = []): array
{
    $q = db()->prepare($sql);
    $q->execute($params);
    return $q->fetchAll();
}

function one(string $sql, array $params = []): ?array
{
    return rows($sql, $params)[0] ?? null;
}

function run(string $sql, array $params = []): void
{
    $q = db()->prepare($sql);
    $q->execute($params);
}

function transaction(callable $work): mixed
{
    db()->beginTransaction();
    try {
        $result = $work();
        db()->commit();
        return $result;
    } catch (Throwable $e) {
        if (db()->inTransaction()) db()->rollBack();
        throw $e;
    }
}

function fail(string $message, int $code = 422): never
{
    throw new AppError($message, $code);
}

function text_field(array $data, string $key, int $max, bool $required = true): string
{
    $value = trim(is_string($data[$key] ?? null) ? $data[$key] : '');
    if (($required && $value === '') || strlen($value) > $max) fail("Thông tin $key không hợp lệ.");
    return $value;
}

function number_field(array $data, string $key, int $min, int $max): int
{
    $v = filter_var($data[$key] ?? null, FILTER_VALIDATE_INT);
    if ($v === false || $v === null || $v < $min || $v > $max) fail("Giá trị $key phải từ $min đến $max.");
    return $v;
}

function local_time(string $value): DateTimeImmutable
{
    $value = str_replace('T', ' ', $value);
    if (strlen($value) === 16) $value .= ':00';
    $time = DateTimeImmutable::createFromFormat('!Y-m-d H:i:s', $value);
    if (!$time || $time->format('Y-m-d H:i:s') !== $value) fail('Ngày giờ không hợp lệ.');
    return $time;
}
