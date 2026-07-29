<?php
/**
 * 依存ライブラリなしのシンプルなSMTPクライアント。
 * PHPMailer等を使わず、fsockopen/stream_socket_clientで直接SMTPプロトコルを話す。
 * 共用ホスティングのmail()関数は迷惑メール判定されやすい/無効化されていることが多いため、
 * Gmail等の外部SMTPサーバーを直接使えるようにしている。
 */

class SmtpMailerException extends Exception {}

/**
 * 自社情報（company_settings）のSMTP設定を使ってメールを送信する。
 * @param string $to 宛先メールアドレス
 * @param string $toName 宛先表示名（任意）
 * @param string $subject 件名
 * @param string $bodyText 本文（プレーンテキスト）
 * @param array $company get_company_settings() の戻り値
 * @throws SmtpMailerException
 */
function smtp_send_mail($to, $toName, $subject, $bodyText, $company) {
    $host = trim($company['smtp_host'] ?? '');
    $port = (int)($company['smtp_port'] ?? 587);
    $encryption = $company['smtp_encryption'] ?? 'tls'; // none / ssl / tls
    $username = trim($company['smtp_username'] ?? '');
    $password = (string)($company['smtp_password'] ?? '');
    $fromEmail = trim($company['smtp_from_email'] ?? '') ?: $username;
    $fromName = trim($company['smtp_from_name'] ?? '') ?: ($company['company_name'] ?? 'COBIS');

    if (!$host || !$fromEmail) {
        throw new SmtpMailerException('SMTP設定が未設定です。自社情報設定からSMTPサーバーを設定してください。');
    }
    if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
        throw new SmtpMailerException('宛先メールアドレスの形式が正しくありません。');
    }

    $connectHost = ($encryption === 'ssl') ? 'ssl://' . $host : $host;
    $errno = 0;
    $errstr = '';
    $fp = @stream_socket_client($connectHost . ':' . $port, $errno, $errstr, 15, STREAM_CLIENT_CONNECT);
    if (!$fp) {
        throw new SmtpMailerException("SMTPサーバーに接続できませんでした: {$errstr} ({$errno})");
    }
    stream_set_timeout($fp, 15);

    try {
        $readResponse = function () use ($fp) {
            $data = '';
            while (($line = fgets($fp, 515)) !== false) {
                $data .= $line;
                // 応答の最終行は "250 " のように4文字目がスペース（複数行応答は "250-"）
                if (!isset($line[3]) || $line[3] === ' ') break;
            }
            if ($data === '') {
                throw new SmtpMailerException('SMTPサーバーからの応答がありませんでした（タイムアウトの可能性があります）。');
            }
            return $data;
        };
        $sendCommand = function ($cmd) use ($fp) {
            fwrite($fp, $cmd . "\r\n");
        };
        $expect = function ($expectedCode) use ($readResponse) {
            $resp = $readResponse();
            $code = substr($resp, 0, 3);
            if ($code !== (string)$expectedCode) {
                throw new SmtpMailerException("SMTPエラー（{$expectedCode}を期待）: " . trim($resp));
            }
            return $resp;
        };
        $localName = $_SERVER['SERVER_NAME'] ?? 'localhost';

        $expect(220);
        $sendCommand('EHLO ' . $localName);
        $expect(250);

        if ($encryption === 'tls') {
            $sendCommand('STARTTLS');
            $expect(220);
            $cryptoMethod = STREAM_CRYPTO_METHOD_TLS_CLIENT;
            if (defined('STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT')) {
                $cryptoMethod |= STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT;
            }
            if (!@stream_socket_enable_crypto($fp, true, $cryptoMethod)) {
                throw new SmtpMailerException('STARTTLSによる暗号化通信の開始に失敗しました。');
            }
            $sendCommand('EHLO ' . $localName);
            $expect(250);
        }

        if ($username !== '') {
            $sendCommand('AUTH LOGIN');
            $expect(334);
            $sendCommand(base64_encode($username));
            $expect(334);
            $sendCommand(base64_encode($password));
            $expect(235);
        }

        $sendCommand('MAIL FROM:<' . $fromEmail . '>');
        $expect(250);
        $sendCommand('RCPT TO:<' . $to . '>');
        $expect(250);
        $sendCommand('DATA');
        $expect(354);

        $headers = [];
        $headers[] = 'From: ' . smtp_encode_header($fromName) . ' <' . $fromEmail . '>';
        $headers[] = 'To: ' . ($toName ? smtp_encode_header($toName) . ' <' . $to . '>' : $to);
        $headers[] = 'Subject: ' . smtp_encode_header($subject);
        $headers[] = 'Date: ' . date('r');
        $headers[] = 'MIME-Version: 1.0';
        $headers[] = 'Content-Type: text/plain; charset=UTF-8';
        $headers[] = 'Content-Transfer-Encoding: 8bit';

        // ドットスタッフィング（本文中の行頭"."をエスケープ）
        $escapedBody = preg_replace('/^\./m', '..', $bodyText);
        $message = implode("\r\n", $headers) . "\r\n\r\n" . $escapedBody . "\r\n.";
        $sendCommand($message);
        $expect(250);

        $sendCommand('QUIT');
    } finally {
        fclose($fp);
    }

    return true;
}

/**
 * ヘッダー中の日本語等をMIMEエンコードする
 */
function smtp_encode_header($str) {
    if (preg_match('/[^\x20-\x7E]/', $str)) {
        return '=?UTF-8?B?' . base64_encode($str) . '?=';
    }
    return $str;
}
