<?php

declare(strict_types=1);

namespace ITuoiProfessionistiDigitali\Connector\Signature;

use ITuoiProfessionistiDigitali\Connector\Contract;
use ITuoiProfessionistiDigitali\Connector\Exceptions\InvalidSignatureException;

/**
 * The signature of the webhooks: `v1=` and the HMAC-SHA256 of `timestamp.body` (rule W3).
 */
final class WebhookSignature
{
    /**
     * The value of X-Platform-Signature: one signature per secret, separated by commas.
     */
    public static function sign(string $body, int $timestamp, string ...$secrets): string
    {
        return implode(',', array_map(
            static fn (string $secret): string => Contract::SIGNATURE_VERSION.'='.self::hash($body, $timestamp, $secret),
            $secrets,
        ));
    }

    /**
     * Accepts the body if any of its signatures matches any of the secrets, and its timestamp is
     * within five minutes of now, in the past or in the future (rule W4).
     *
     * @param  list<string>  $secrets
     *
     * @throws InvalidSignatureException
     */
    public static function verify(string $body, ?string $timestamp, ?string $signatures, array $secrets, int $now): void
    {
        if ($timestamp === null || preg_match('/^\d+$/', $timestamp) !== 1)
        {
            throw new InvalidSignatureException('Timestamp della firma assente o non valido.');
        }

        if (abs($now - (int) $timestamp) > Contract::SIGNATURE_TOLERANCE_SECONDS)
        {
            throw new InvalidSignatureException('Timestamp della firma fuori tolleranza.');
        }

        if ($signatures === null || $signatures === '' || $secrets === [])
        {
            throw new InvalidSignatureException('Firma assente.');
        }

        foreach (explode(',', $signatures) as $signature)
        {
            [$version, $hash] = array_pad(explode('=', mb_trim($signature), 2), 2, '');

            if ($version !== Contract::SIGNATURE_VERSION)
            {
                continue;
            }

            foreach ($secrets as $secret)
            {
                if (hash_equals(self::hash($body, (int) $timestamp, $secret), $hash))
                {
                    return;
                }
            }
        }

        throw new InvalidSignatureException('Firma non valida.');
    }

    private static function hash(string $body, int $timestamp, string $secret): string
    {
        return hash_hmac('sha256', $timestamp.'.'.$body, $secret);
    }
}
