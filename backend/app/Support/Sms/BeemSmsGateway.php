<?php

namespace App\Support\Sms;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/**
 * Beem Africa SMS (https://beem.africa).
 *
 * POST {url} with HTTP Basic auth (API key : secret key) and a JSON body of
 * source_addr, encoding, message and recipients[{recipient_id, dest_addr}].
 * A successful submission returns `"successful": true` and a request_id.
 */
final class BeemSmsGateway implements SmsGateway
{
    /**
     * @param  array{api_key: ?string, secret_key: ?string, sender_id: string, url: string, timeout: int}  $config
     */
    public function __construct(private readonly array $config) {}

    public function name(): string
    {
        return 'beem';
    }

    public function send(string $to, string $message): SmsResult
    {
        if (blank($this->config['api_key']) || blank($this->config['secret_key'])) {
            return SmsResult::failed('Beem API key or secret key is not configured.');
        }

        try {
            $response = Http::withBasicAuth($this->config['api_key'], $this->config['secret_key'])
                ->acceptJson()
                ->timeout($this->config['timeout'])
                ->post($this->config['url'], [
                    'source_addr' => $this->config['sender_id'],
                    'encoding' => 0,
                    'message' => $message,
                    'recipients' => [
                        ['recipient_id' => 1, 'dest_addr' => ltrim($to, '+')],
                    ],
                ]);
        } catch (ConnectionException $exception) {
            return SmsResult::failed('Could not reach Beem: '.Str::limit($exception->getMessage(), 200));
        }

        $body = $response->json() ?? [];

        if ($response->successful() && ($body['successful'] ?? false) === true) {
            return SmsResult::accepted(isset($body['request_id']) ? (string) $body['request_id'] : null);
        }

        $reason = $body['message'] ?? $body['data']['message'] ?? $response->reason();

        return SmsResult::failed(Str::limit('Beem '.$response->status().' '.($body['code'] ?? '').': '.$reason, 480));
    }
}
