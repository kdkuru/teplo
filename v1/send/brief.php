<?php
/**
 * Приёмщик заявок сайта «ТеплоСервис» (прототип-заглушка с рабочей логикой).
 *
 * Фронтенд уже шлёт сюда AJAX POST (JSON): {type, name, phone, need, msg, text}.
 * При запуске на боевом сайте положить этот файл в корень (нужен PHP-хостинг) —
 * письмо уйдёт на почту компании без дополнительных настроек фронтенда.
 *
 * Если хостинг без PHP или mail() отключён — заменить блок отправки на SMTP
 * (PHPMailer + ящик компании), путь вызова для фронтенда не меняется.
 */

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');

$raw  = file_get_contents('php://input');
$data = json_decode($raw, true);

if (!is_array($data) || empty($data['phone'])) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'bad_request']);
    exit;
}

// простая антиспам-гигиена: срезаем теги и ограничиваем длину
$clean = function ($v) {
    $v = trim(strip_tags((string)$v));
    return mb_substr($v, 0, 2000);
};

$type = ($data['type'] ?? '') === 'lead' ? 'Заявка с формы контактов' : 'Бриф на расчёт с сайта';

$fields = [
    'name'  => 'Имя',
    'phone' => 'Телефон',
    'need'  => 'Задача',
    'msg'   => 'Объект',
    'text'  => 'Бриф',
];
$lines = ["$type", str_repeat('-', 20)];
foreach ($fields as $k => $label) {
    if (!empty($data[$k])) {
        $lines[] = $label . ': ' . $clean($data[$k]);
    }
}
$lines[] = 'Дата: ' . date('d.m.Y H:i');
$lines[] = 'IP: ' . ($_SERVER['REMOTE_ADDR'] ?? '-');

// $to      = 'teploservis2014@yandex.ru';
$to      = 'kdkuru@yandex.ru';
$subject = '=?UTF-8?B?' . base64_encode($type . ' — сайт ТеплоСервис') . '?=';
$body    = implode("\n", $lines);
$host    = $_SERVER['HTTP_HOST'] ?? 'теплосервис63.рф';
$headers = "Content-Type: text/plain; charset=UTF-8\r\n"
         . "From: site@" . preg_replace('/[^\w\.\-]/u', '', $host) . "\r\n";

$ok = @mail($to, $subject, $body, $headers);

http_response_code($ok ? 200 : 500);
echo json_encode(['ok' => $ok], JSON_UNESCAPED_UNICODE);
