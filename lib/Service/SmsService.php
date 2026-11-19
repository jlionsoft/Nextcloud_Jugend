<?php
declare(strict_types=1);
namespace OCA\Jugend\Service;

use OCP\Http\Client\IClientService;
use OCP\IAppConfig;
use RuntimeException;

class SmsService {
    public function __construct(private IClientService $http, private IAppConfig $config) {}

    public function testConnection(): array {
        $gateway = rtrim($this->required('sms_gateway_url'), '/');
        $response = $this->http->newClient()->get($gateway, ['timeout' => 3]);
        if ($response->getStatusCode() >= 500) {
            throw new RuntimeException('SMS-Gateway antwortet mit HTTP ' . $response->getStatusCode() . '.');
        }
        return ['ok' => true, 'message' => 'SMS-Gateway erreichbar (HTTP ' . $response->getStatusCode() . ').'];
    }

    public function send(string $phone, string $message): void {
        $gateway = rtrim($this->required('sms_gateway_url'), '/') . '/rest/tool/sms/send';
        $username = $this->required('sms_username');
        $password = $this->required('sms_password');
        $interface = trim($this->config->getValueString('jugend', 'sms_interface', 'lte1')) ?: 'lte1';
        $parts = $this->split($message);
        foreach ($parts as $i => $part) {
            $payload = ['port' => $interface, 'phone-number' => $phone, 'message' => count($parts) > 1 ? '[' . ($i + 1) . '] ' . $part : $part];
            $response = $this->http->newClient()->post($gateway, [
                'headers' => [
                    'Authorization' => 'Basic ' . base64_encode($username . ':' . $password),
                    'Accept' => 'application/json',
                    'Content-Type' => 'application/json',
                ],
                'body' => json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
                'timeout' => 10,
            ]);
            if ($response->getStatusCode() < 200 || $response->getStatusCode() >= 300) {
                throw new RuntimeException('MikroTik SMS-Gateway antwortete mit HTTP ' . $response->getStatusCode() . '.');
            }
            if ($i < count($parts) - 1) { usleep(2000000); }
        }
    }

    private function required(string $key): string {
        $value = trim($this->config->getValueString('jugend', $key));
        if ($value === '') { throw new RuntimeException('Einstellung fehlt: ' . $key); }
        return $value;
    }

    public function split(string $message): array {
        $basic = <<<'GSM'
@£$¥èéùìòÇØøÅåΔ_ΦΓΛΩΠΨΣΘΞÆæßÉ !"#¤%&'()*+,-./0123456789:;<=>?¡ABCDEFGHIJKLMNOPQRSTUVWXYZÄÖÑÜ§¿abcdefghijklmnopqrstuvwxyzäöñüà
GSM;
        $basic .= "\n\r";
        $extended = "^{}\\[~]|€";
        $chars = preg_split('//u', $message, -1, PREG_SPLIT_NO_EMPTY);
        if ($chars === false) { throw new RuntimeException('Nachricht enthält ungültiges UTF-8.'); }
        $parts = [];
        $current = '';
        $slots = 0;
        foreach ($chars as $char) {
            if (!str_contains($basic, $char) && !str_contains($extended, $char)) {
                throw new RuntimeException('Nicht unterstütztes GSM-7-Zeichen: ' . $char . '. Bitte Nachricht anpassen.');
            }
            $cost = str_contains($extended, $char) ? 2 : 1;
            if ($slots + $cost > 150) {
                $parts[] = trim($current);
                $current = '';
                $slots = 0;
            }
            $current .= $char;
            $slots += $cost;
        }
        if ($current !== '' || $parts === []) { $parts[] = trim($current); }
        return array_values(array_filter($parts, static fn($part) => $part !== ''));
    }
}
