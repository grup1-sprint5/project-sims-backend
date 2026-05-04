<?php

namespace App\Mail;

use App\Models\TenantRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class TenantApprovedMail extends Mailable
{
    use Queueable, SerializesModels;

    public $requestModel;
    public $domain;

    public function __construct(TenantRequest $req, ?string $domain)
    {
        $this->requestModel = $req;
        $this->domain = $domain;
    }

    public function build()
    {
        return $this->subject('Your company has been approved')
            ->view('emails.tenant_approved')
            ->with([
                'request' => $this->requestModel,
                'domain' => $this->domain,
            ]);
    }
}
