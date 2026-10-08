<?php

declare(strict_types=1);

namespace ITuoiProfessionistiDigitali\Connector\Console;

use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use ITuoiProfessionistiDigitali\Connector\ConnectorConfig;
use ITuoiProfessionistiDigitali\Connector\Enums\OutboxStatus;
use ITuoiProfessionistiDigitali\Connector\Jobs\SendPlatformTransaction;
use ITuoiProfessionistiDigitali\Connector\Models\PlatformTransactionOutbox;

/**
 * Sends again the versions left behind: a system not yet active, a platform down for longer than the retries.
 * Scheduled every five minutes by the service provider.
 */
final class SendOutboxCommand extends Command
{
    protected $signature = 'platform:send-outbox';

    protected $description = 'Rimanda al portale le versioni delle transazioni non ancora confermate';

    public function handle(ConnectorConfig $config): int
    {
        if (!$config->isConnected())
        {
            $this->components->warn('Il sistema non è collegato al portale.');

            return self::SUCCESS;
        }

        $references = PlatformTransactionOutbox::query()
            ->where('status', OutboxStatus::Pending)
            ->where(fn (Builder $query): Builder => $query->whereNull('sent_revision')->orWhereColumn('sent_revision', '!=', 'revision'))
            ->pluck('reference');

        foreach ($references as $reference)
        {
            if (is_string($reference))
            {
                dispatch(new SendPlatformTransaction($reference))->onQueue($config->queue);
            }
        }

        $this->components->info("Transazioni rimesse in coda: {$references->count()}.");

        return self::SUCCESS;
    }
}
