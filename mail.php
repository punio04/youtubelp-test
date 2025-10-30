<?php
// ===== 本番用設定：エラー非表示 =====
ini_set('display_errors', 0);
error_reporting(0);

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require __DIR__ . '/vendor/autoload.php';
$config = require __DIR__ . '/env.php';

// ===== バリデーション関数 =====
function val($k)
{
  return isset($_POST[$k]) ? trim($_POST[$k]) : '';
}
function strip_newlines($s)
{
  return str_replace(["\r", "\n"], '', $s);
}

// ===== HoneyPot（スパム防止） =====
if (!empty($_POST['website'])) {
  http_response_code(400);
  exit;
}

// ===== フォーム値の取得 =====
$company = htmlspecialchars(val('貴社名'), ENT_QUOTES, 'UTF-8');
$name    = htmlspecialchars(val('お名前'), ENT_QUOTES, 'UTF-8');
$email   = strip_newlines(val('メールアドレス'));
$phone   = preg_replace('/[^\d]/', '', val('電話番号')); // 数字のみ
$content = htmlspecialchars(val('お問い合わせ内容'), ENT_QUOTES, 'UTF-8');
$agree   = isset($_POST['個人情報同意']) ? '同意する' : '未同意';

// ===== 必須チェック =====
if ($company === '' || $name === '' || $email === '' || $phone === '') {
  http_response_code(422);
  exit;
}
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
  http_response_code(422);
  exit;
}
if ($agree !== '同意する') {
  http_response_code(422);
  exit;
}

// ===== メール送信設定 =====
$mail = new PHPMailer(true);

try {
  // --- 共通設定 ---
  $mail->CharSet  = 'UTF-8';
  $mail->Encoding = 'base64';
  $mail->isSMTP();
  $mail->Host       = 'smtp.gmail.com';
  $mail->SMTPAuth   = true;
  $mail->Username   = $config['GMAIL_USERNAME'];
  $mail->Password   = $config['GMAIL_APP_PASSWORD'];
  $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
  $mail->Port       = 587;

  // --- 管理者宛メール ---
  $mail->setFrom($config['GMAIL_USERNAME'], '=?UTF-8?B?' . base64_encode('YouTube運用代行LP') . '?=');
  $mail->addReplyTo($email, $name);
  $recipients = ['gudeko.0417@gmail.com', 'gude_0417@icloud.com'];
  foreach ($recipients as $rcpt) {
    $mail->addAddress($rcpt);
  }

  $mail->Subject = '=?UTF-8?B?' . base64_encode('【YouTube運用代行LP】お問い合わせがありました') . '?=';
  $body  = "以下の内容でお問い合わせがありました。\n\n";
  $body .= "━━━━━━━━━━━━━━━━━━━\n";
  $body .= "■ 貴社名\n{$company}\n\n";
  $body .= "■ お名前\n{$name}\n\n";
  $body .= "■ メールアドレス\n{$email}\n\n";
  $body .= "■ 電話番号\n{$phone}\n\n";
  $body .= "■ お問い合わせ内容\n{$content}\n";
  $body .= "━━━━━━━━━━━━━━━━━━━\n\n";
  $body .= "送信元ページ：https://" . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'] . "\n";

  $mail->isHTML(false);
  $mail->Body = $body;
  $mail->send();

  // --- 自動返信メール（ユーザー宛） ---
  $autoReply = new PHPMailer(true);
  $autoReply->CharSet  = 'UTF-8';
  $autoReply->Encoding = 'base64';
  $autoReply->isSMTP();
  $autoReply->Host       = 'smtp.gmail.com';
  $autoReply->SMTPAuth   = true;
  $autoReply->Username   = $config['GMAIL_USERNAME'];
  $autoReply->Password   = $config['GMAIL_APP_PASSWORD'];
  $autoReply->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
  $autoReply->Port       = 587;

  $autoReply->setFrom($config['GMAIL_USERNAME'], '=?UTF-8?B?' . base64_encode('YouTube運用代行LP') . '?=');
  $autoReply->addAddress($email, $name);
  $autoReply->Subject = '=?UTF-8?B?' . base64_encode('【YouTube運用代行LP】お問い合わせありがとうございます') . '?=';

  $autoReplyBody  = "{$name} 様\n\n";
  $autoReplyBody .= "この度はお問い合わせいただきありがとうございます。\n";
  $autoReplyBody .= "以下の内容でお問い合わせを受け付けました。\n\n";
  $autoReplyBody .= "━━━━━━━━━━━━━━━━━━━\n";
  $autoReplyBody .= "■ 貴社名\n{$company}\n\n";
  $autoReplyBody .= "■ お名前\n{$name}\n\n";
  $autoReplyBody .= "■ メールアドレス\n{$email}\n\n";
  $autoReplyBody .= "■ 電話番号\n{$phone}\n\n";
  $autoReplyBody .= "■ お問い合わせ内容\n{$content}\n";
  $autoReplyBody .= "━━━━━━━━━━━━━━━━━━━\n\n";
  $autoReplyBody .= "担当者より折り返しご連絡させていただきます。\n";
  $autoReplyBody .= "※本メールは自動送信です。\n\n";
  $autoReplyBody .= "------------------------------------\n";
  $autoReplyBody .= "YouTube運用代行LP お問い合わせ窓口\n";
  $autoReplyBody .= "------------------------------------";

  $autoReply->isHTML(false);
  $autoReply->Body = $autoReplyBody;
  $autoReply->send();

  // --- 完了時処理 ---
  header('Location: thanks.html');
  exit;
} catch (Exception $e) {
  // 詳細はログに記録し、ユーザーには何も出さない
  error_log('Mail Error: ' . $e->getMessage() . ' | ' . $mail->ErrorInfo);
  http_response_code(500);
  exit;
}
?>