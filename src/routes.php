<?php
declare(strict_types=1);

use Slim\App;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

return function (App $app) {

    // Home (sans formulaire) — sert le fichier statique Bootstrap
    $app->get('/', function (Request $r, Response $res) {
        $html = @file_get_contents(__DIR__ . '/../public/home.html');
        if ($html === false) {
            $res->getBody()->write('home.html manquant');
            return $res->withStatus(500)->withHeader('Content-Type', 'text/plain; charset=utf-8');
        }
        $res->getBody()->write($html);
        return $res->withHeader('Content-Type', 'text/html; charset=utf-8');
    });

    // Contact (POST /contact) -> envoi via provider SMTP/API (SendGrid ou Brevo)
    $app->post('/contact', function (Request $r, Response $res) {
        $data  = (array)($r->getParsedBody() ?? []);
        $email = filter_var($data['email'] ?? '', FILTER_VALIDATE_EMAIL);
        $msg   = trim((string)($data['message'] ?? ''));

        if (!$email || $msg === '') {
            $res->getBody()->write(json_encode(['ok' => false, 'error' => 'invalid'], JSON_UNESCAPED_UNICODE));
            return $res->withStatus(400)->withHeader('Content-Type', 'application/json; charset=utf-8');
        }

        $apiKey = getenv('SENDGRID_API_KEY') ?: getenv('BREVO_API_KEY');
        if (!$apiKey) {
            $res->getBody()->write(json_encode(['ok' => false, 'error' => 'missing_api_key'], JSON_UNESCAPED_UNICODE));
            return $res->withStatus(500)->withHeader('Content-Type', 'application/json; charset=utf-8');
        }

        try {
            // --- SendGrid (par défaut) ---
            $payload = [
                'personalizations' => [[ 'to' => [[ 'email' => 'hello@dion.coach' ]] ]],
                'from'             => [ 'email' => 'noreply@dion.coach' ],
                'subject'          => 'Nouveau message du site',
                'content'          => [[ 'type' => 'text/plain', 'value' => "De: $email\n\n$msg" ]],
            ];

            $client = new \GuzzleHttp\Client(['timeout' => 10]);
            $client->post('https://api.sendgrid.com/v3/mail/send', [
                'headers' => [
                    'Authorization' => "Bearer {$apiKey}",
                    'Content-Type'  => 'application/json',
                ],
                'json' => $payload,
            ]);

            $res->getBody()->write(json_encode(['ok' => true], JSON_UNESCAPED_UNICODE));
            return $res->withHeader('Content-Type', 'application/json; charset=utf-8');

        } catch (\Throwable $e) {
            // Si tu utilises Brevo, remplace l’URL et le format (commentaire ci-dessous)
            // $client->post('https://api.brevo.com/v3/smtp/email', [...]);

            $res->getBody()->write(json_encode([
                'ok'    => false,
                'error' => 'send_failed',
                'hint'  => $e->getMessage(),
            ], JSON_UNESCAPED_UNICODE));
            return $res->withStatus(502)->withHeader('Content-Type', 'application/json; charset=utf-8');
        }
    });

};
