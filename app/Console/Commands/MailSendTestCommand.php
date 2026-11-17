<?php

namespace App\Console\Commands;

use App\Mail\TemplatedOutboundMail;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;

class MailSendTestCommand extends Command
{
    protected $signature = 'mail:send-test {email : Recipient address}';

    protected $description = 'Queue a short test email to verify SMTP and the queue worker';

    public function handle(): int
    {
        $email = (string) $this->argument('email');

        Mail::to($email)->queue(new TemplatedOutboundMail(
            subjectLine: config('app.name').' mail test',
            bodyText: 'Restivo queue/mail path OK at '.now('Africa/Cairo')->toDateTimeString().' Africa/Cairo.',
        ));

        $this->info('Queued test mail to '.$email);

        return self::SUCCESS;
    }
}
