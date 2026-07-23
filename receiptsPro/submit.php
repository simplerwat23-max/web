<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');

function respond(int $status, bool $success, string $message): never {
    http_response_code($status);
    echo json_encode(['success' => $success, 'message' => $message], JSON_UNESCAPED_UNICODE);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    respond(405, false, 'Метод не поддерживается');
}

// Простая honeypot-защита от автоматических отправок.
if (!empty($_POST['website'])) {
    respond(200, true, 'Заявка принята');
}

$phone = trim((string)($_POST['phone'] ?? ''));
$telegram = trim((string)($_POST['telegram'] ?? ''));
$email = trim((string)($_POST['email'] ?? ''));
$comment = trim((string)($_POST['comment'] ?? ''));
$phoneDigits = preg_replace('/\D+/', '', $phone);

if (!preg_match('/^7\d{10}$/', $phoneDigits)) {
    respond(422, false, 'Укажите корректный номер телефона');
}
if (!filter_var($email, FILTER_VALIDATE_EMAIL) || mb_strlen($email) > 254) {
    respond(422, false, 'Укажите корректный email');
}
if ($telegram !== '' && !preg_match('~^(?:@|https?://(?:t\.me|telegram\.me)/)?[A-Za-z][A-Za-z0-9_]{4,31}/?$~', $telegram)) {
    respond(422, false, 'Укажите корректный Telegram');
}
if (mb_strlen($comment) > 500) {
    respond(422, false, 'Комментарий не должен превышать 500 символов');
}

$dbHost = getenv('DB_HOST') ?: 'localhost';
$dbPort = getenv('DB_PORT') ?: '3306';
$dbName = getenv('DB_NAME') ?: '';
$dbUser = getenv('DB_USER') ?: '';
$dbPass = getenv('DB_PASSWORD') ?: '';
$botToken = getenv('TELEGRAM_BOT_TOKEN') ?: '';
$chatId = getenv('TELEGRAM_CHAT_ID') ?: '';

if ($dbName === '' || $dbUser === '') {
    error_log('Lead form: database environment variables are missing');
    respond(503, false, 'Сервис временно недоступен. Попробуйте позже.');
}

$formattedPhone = sprintf(
    '+7 (%s) %s-%s-%s',
    substr($phoneDigits, 1, 3),
    substr($phoneDigits, 4, 3),
    substr($phoneDigits, 7, 2),
    substr($phoneDigits, 9, 2)
);

try {
    $pdo = new PDO(
        "mysql:host={$dbHost};port={$dbPort};dbname={$dbName};charset=utf8mb4",
        $dbUser,
        $dbPass,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]
    );
    $statement = $pdo->prepare(
        'INSERT INTO leads (phone, telegram, email, comment, created_at)
         VALUES (:phone, :telegram, :email, :comment, NOW())'
    );
    $statement->execute([
        ':phone' => $formattedPhone,
        ':telegram' => $telegram !== '' ? $telegram : null,
        ':email' => $email,
        ':comment' => $comment !== '' ? $comment : null,
    ]);
} catch (Throwable $error) {
    error_log('Lead form DB error: ' . $error->getMessage());
    respond(500, false, 'Не удалось сохранить заявку. Попробуйте позже.');
}

if ($botToken !== '' && $chatId !== '' && function_exists('curl_init')) {
    $time = (new DateTimeImmutable('now'))->format('Y-m-d H:i');
    $message = "📩 Новая заявка!\n"
        . "📞 Телефон: {$formattedPhone}\n"
        . "✈️ Telegram: " . ($telegram ?: 'не указан') . "\n"
        . "📧 Email: {$email}\n"
        . "💬 Комментарий: " . ($comment ?: 'нет') . "\n"
        . "🕒 Время: {$time}";

    $curl = curl_init("https://api.telegram.org/bot{$botToken}/sendMessage");
    curl_setopt_array($curl, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => http_build_query(['chat_id' => $chatId, 'text' => $message]),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CONNECTTIMEOUT => 5,
        CURLOPT_TIMEOUT => 10,
    ]);
    $telegramResponse = curl_exec($curl);
    $telegramStatus = (int)curl_getinfo($curl, CURLINFO_HTTP_CODE);
    curl_close($curl);
    if ($telegramResponse === false || $telegramStatus < 200 || $telegramStatus >= 300) {
        error_log("Lead form Telegram error: HTTP {$telegramStatus}");
    }
} else {
    error_log('Lead form: Telegram configuration or cURL extension is missing');
}

respond(200, true, 'Спасибо! Мы свяжемся с вами в ближайшее время');
