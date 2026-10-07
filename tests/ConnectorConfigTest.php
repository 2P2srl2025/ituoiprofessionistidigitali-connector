<?php

declare(strict_types=1);

use ITuoiProfessionistiDigitali\Connector\ConnectorConfig;
use ITuoiProfessionistiDigitali\Connector\Exceptions\PlatformNotConfiguredException;

it('types the configuration of the package', function (): void {
    $config = ConnectorConfig::fromArray([
        'url' => 'https://platform.test/',
        'client_id' => 'id',
        'client_secret' => 'secret',
        'signing_secret' => 'signing',
        'webhook_path' => '',
        'reply_to_pings' => false,
        'queue' => 'platform',
        'timeout' => '5',
        'retries' => 'many',
        'validate_payloads' => false,
        'catalog_ttl' => 60,
    ]);

    expect($config)
        ->signingSecret->toBe('signing')
        ->webhookPath->toBeNull()
        ->replyToPings->toBeFalse()
        ->queue->toBe('platform')
        ->timeout->toBe(5)
        ->retries->toBe(2)
        ->validatePayloads->toBeFalse()
        ->catalogTtl->toBe(60)
        ->and($config->isConnected())->toBeTrue()
        ->and($config->apiUrl('/system'))->toBe('https://platform.test/api/v1/system')
        ->and($config->tokenUrl())->toBe('https://platform.test/oauth/token');
});

it('is not connected without url and credentials', function (array $config): void {
    ConnectorConfig::fromArray($config)->apiUrl('system');
})->with([
    'nothing' => [[]],
    'no secret' => [['url' => 'https://platform.test', 'client_id' => 'id']],
    'no url' => [['client_id' => 'id', 'client_secret' => 'secret']],
])->throws(PlatformNotConfiguredException::class);
