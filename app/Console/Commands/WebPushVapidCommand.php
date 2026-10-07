<?php

namespace App\Console\Commands;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Minishlink\WebPush\VAPID;

#[Signature('webpush:vapid')]
#[Description('Generate the VAPID key pair for push notifications, to paste into .env')]
class WebPushVapidCommand extends Command
{
    public function handle(): int
    {
        $keys = VAPID::createVapidKeys();

        $this->line('Add these to .env (keep the private key secret; changing them unsubscribes every device):');
        $this->newLine();
        $this->line("WEBPUSH_PUBLIC_KEY={$keys['publicKey']}");
        $this->line("WEBPUSH_PRIVATE_KEY={$keys['privateKey']}");
        $this->line('WEBPUSH_SUBJECT=mailto:you@example.com');

        return self::SUCCESS;
    }
}
