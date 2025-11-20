<?php

require "vendor/autoload.php";

$redis = new Predis\Client([
    'host'     => getenv("REDIS_HOST") ?: "redis",
    'port'     => getenv("REDIS_PORT") ?: 6379,
    'password' => getenv("REDIS_PASSWORD") ?: null
]);

$webhook = getenv("WEBHOOK_URL");

echo "Worker PHP Plug4Market iniciado...\n";
echo "Webhook: $webhook\n";

while (true) {

    // BLPOP bloqueante — aguarda IDs na fila
    $data = $redis->blpop("plug4_orders", 0);

    $orderId = $data[1];

    echo "📦 Buscando pedido: $orderId\n";

    $token = getenv("PLUG4MARKET_TOKEN");

    $url = "https://api.plug4market.com.br/orders/$orderId";

    $headers = "Authorization: Bearer $token\r\n";

    $opts = [
        "http" => [
            "method" => "GET",
            "header" => $headers
        ]
    ];

    $context = stream_context_create($opts);
    $response = @file_get_contents($url, false, $context);

    if ($response === false) {
        echo "❌ Erro ao consultar o pedido!\n";
        continue;
    }

    $json = json_decode($response, true);

    echo "✅ Pedido recebido. Enviando ao webhook...\n";

    // POST para o webhook
    $post = [
        "orderId" => $orderId,
        "payload" => $json
    ];

    $ch = curl_init($webhook);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($post));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        "Content-Type: application/json"
    ]);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $result = curl_exec($ch);
    $http = curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    curl_close($ch);

    echo "🌐 Webhook HTTP $http\n";
}
