<?php

declare(strict_types=1);

namespace SailConnect\Auth;

use PHPMailer\PHPMailer\PHPMailer;

/** SMTP mail via PHPMailer. Failures are logged and reported as false, never thrown. */
class Mailer
{
    public function __construct(private Config $config)
    {
    }

    public function send(string $to, string $subject, string $html): bool
    {
        try {
            $m = new PHPMailer(true);
            $m->CharSet = 'UTF-8';
            if ($this->config->get('SMTP_HOST') !== '') {
                $m->isSMTP();
                $m->Host = $this->config->get('SMTP_HOST');
                $m->Port = $this->config->int('SMTP_PORT', 587);
                $secure = $this->config->get('SMTP_SECURE', 'tls');
                $m->SMTPSecure = $secure === 'none' ? '' : $secure;
                $m->SMTPAutoTLS = $secure !== 'none';
                if ($this->config->get('SMTP_USERNAME') !== '') {
                    $m->SMTPAuth = true;
                    $m->Username = $this->config->get('SMTP_USERNAME');
                    $m->Password = $this->config->get('SMTP_PASSWORD');
                }
            }
            $m->setFrom($this->config->get('EMAIL_FROM', 'noreply@sailconnect.org'), $this->config->get('EMAIL_FROM_NAME', 'sailConnect'));
            $m->addAddress($to);
            $m->Subject = $subject;
            $m->isHTML(true);
            $m->Body = $html;
            $m->AltBody = trim(html_entity_decode(strip_tags(str_replace(['<br>', '</p>'], "\n", $html))));
            return $m->send();
        } catch (\Throwable $e) {
            error_log('auth.mail.failed to=' . $to . ' error=' . $e->getMessage());
            return false;
        }
    }
}
