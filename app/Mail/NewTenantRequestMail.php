<?php

namespace App\Mail;

use App\Models\TenantRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class NewTenantRequestMail extends Mailable
{
    use Queueable, SerializesModels;

    public $requestModel;

    public function __construct(TenantRequest $req)
    {
        $this->requestModel = $req;
    }

    public function build()
    {
        return $this->subject('New tenant registration request')
            ->view('emails.new_tenant_request')
            ->with(['request' => $this->requestModel]);
    }
}
