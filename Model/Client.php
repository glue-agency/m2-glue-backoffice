<?php

declare(strict_types=1);

namespace GlueAgency\Backoffice\Model;

use Magento\Framework\HTTP\Client\CurlFactory;

class Client
{
    private const TIMEOUT = 30;

    /**
     * @var CurlFactory
     */
    private $curlFactory;

    /**
     * @param CurlFactory $curlFactory
     */
    public function __construct(CurlFactory $curlFactory)
    {
        $this->curlFactory = $curlFactory;
    }

    /**
     * Returns whether the Dashboard accepted the report, plus every error message it sent back.
     *
     * @param string $url
     * @param string $token
     * @param array $payload
     * @return array [bool $success, string[] $errors]
     * @throws \Exception
     */
    public function report(string $url, string $token, array $payload): array
    {
        $curl = $this->curlFactory->create();
        $curl->setTimeout(self::TIMEOUT);
        $curl->addHeader('Authorization', 'Bearer ' . $token);
        $curl->addHeader('Accept', 'application/json');
        $curl->post(rtrim($url, '/') . '/actions/backoffice/report', $payload);

        $status = (int) $curl->getStatus();
        $body = json_decode((string) $curl->getBody(), true);

        if ($status >= 200 && $status < 300 && is_array($body) && ($body['success'] ?? false) === true) {
            return [true, []];
        }

        return [false, self::errors($status, $body, (string) $curl->getBody())];
    }

    /**
     * @param int $status
     * @param mixed $body
     * @param string $raw
     * @return string[]
     */
    public static function errors(int $status, $body, string $raw): array
    {
        $messages = [];

        if (is_array($body) && isset($body['errors']) && is_array($body['errors'])) {
            foreach ($body['errors'] as $field => $fieldMessages) {
                foreach ((array) $fieldMessages as $message) {
                    $messages[] = is_string($field) ? $field . ': ' . $message : (string) $message;
                }
            }
        }

        if (! $messages && is_array($body) && isset($body['message']) && is_string($body['message'])) {
            $messages[] = $body['message'];
        }

        if (! $messages) {
            $messages[] = sprintf('Unexpected response (HTTP %d): %s', $status, mb_substr(trim($raw), 0, 200));
        }

        return $messages;
    }
}
