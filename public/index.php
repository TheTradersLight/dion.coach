<?php
declare(strict_types=1);
use Slim\Factory\AppFactory;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

require __DIR__ . '/../vendor/autoload.php';
$app = AppFactory::create();
$app->addRoutingMiddleware();
$app->addErrorMiddleware(true, true, true);

// Home
$app->get('/', function (Request $r, Response $res) {
    $html = '<!doctype html><meta charset="utf-8">
    <title>dion.coach</title>
    <link rel="icon" href="data:," />
    <style>body{font:16px/1.5 system-ui;background:#000;color:#d7b25a;display:grid;place-items:center;height:100vh;margin:0}
    .box{max-width:780px;text-align:center}</style>
    <div class="box"><h1>dion.coach</h1><p>Le Ultimate en profondeur.</p>
    <form id="c" method="post" action="/contact" style="margin-top:2rem">
      <input name="email" type="email" placeholder="Votre e-mail" required style="padding:.5rem;border-radius:.5rem;border:1px solid #3a2b10;background:#111;color:#d7b25a">
      <textarea name="message" placeholder="Votre message" required style="display:block;margin:.5rem auto 1rem;width:320px;height:120px;padding:.5rem;border-radius:.5rem;border:1px solid #3a2b10;background:#111;color:#d7b25a"></textarea>
      <button style="padding:.6rem 1rem;border-radius:.6rem;border:0;background:#d7b25a;color:#111;cursor:pointer">Envoyer</button>
    </form></div>
    <script>
    document.getElementById("c").addEventListener("submit", async (e) => {
      e.preventDefault();
      const fd = new FormData(e.target);
      const r = await fetch("/contact", { method:"POST", body: fd });
      const j = await r.json();
      alert(j.ok ? "Merci!" : "Erreur: " + (j.error||""));
    });
    </script>';
    $res->getBody()->write($html);
    return $res;
});

// Contact (POST /contact) -> envoi via provider SMTP/API (ex: SendGrid)
$app->post('/contact', function (Request $r, Response $res) {
    $data = (array)($r->getParsedBody() ?? []);
    $email = filter_var($data['email'] ?? '', FILTER_VALIDATE_EMAIL);
    $msg   = trim($data['message'] ?? '');
    if (!$email || $msg === '') {
        $res->getBody()->write(json_encode(['ok'=>false,'error'=>'invalid']));
        return $res->withStatus(400)->withHeader('Content-Type','application/json');
    }
    $apiKey = getenv('SENDGRID_API_KEY') ?: getenv('BREVO_API_KEY');
    if (!$apiKey) {
        $res->getBody()->write(json_encode(['ok'=>false,'error'=>'missing_api_key']));
        return $res->withStatus(500)->withHeader('Content-Type','application/json');
    }

    // Exemple SendGrid (changer URL/headers pour Brevo/Postmark si besoin)
    $payload = [
      "personalizations" => [[ "to" => [["email" => "hello@dion.coach"]] ]],
      "from" => ["email" => "noreply@dion.coach"],
      "subject" => "Nouveau message du site",
      "content" => [[ "type" => "text/plain", "value" => "De: $email\n\n$msg" ]]
    ];
    $client = new \GuzzleHttp\Client();
    $client->post('https://api.sendgrid.com/v3/mail/send', [
      'headers' => ['Authorization'=>"Bearer $apiKey", 'Content-Type'=>'application/json'],
      'json' => $payload
    ]);
    $res->getBody()->write(json_encode(['ok'=>true]));
    return $res->withHeader('Content-Type','application/json');
});

$app->run();
