<?php
namespace App;

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

class SendMail
{
    public static function envoyer(string $name, string $email, string $message): void
    {
        if (empty($name) || empty($email) || empty($message)) {
            throw new \Exception('Tous les champs sont obligatoires.');
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new \Exception('Adresse email invalide.');
        }

        $mail = new PHPMailer(true);
        $mail->isSMTP();
        $mail->Host       = 'smtp.gmail.com';
        $mail->SMTPAuth   = true;
        $mail->Username   = 'eric@quebecenligne.com';
        $mail->Password   = 'tyyc uqvu grvp xuvz';
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = 587;

        $mail->setFrom('eric@quebecenligne.com', 'Formulaire Dion.coach');
        $mail->addAddress('eric@quebecenligne.com');
        $mail->addReplyTo($email, $name);

        $mail->isHTML(true);
        $mail->Subject = 'Nouveau message du formulaire de contact';

        $mail->Body = sprintf(
            '<p><strong>Nom :</strong> %s</p>
             <p><strong>Email :</strong> %s</p>
             <p><strong>Message :</strong><br>%s</p>',
            htmlspecialchars($name),
            htmlspecialchars($email),
            nl2br(htmlspecialchars($message))
        );

        $mail->send();
    }
}
