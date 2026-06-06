<?php

namespace App\Services;

use Brevo\Client\Api\TransactionalEmailsApi;
use Brevo\Client\Configuration;
use GuzzleHttp\Client;
use Brevo\Client\Model\SendSmtpEmail;

class BrevoMailService
{
    protected $apiInstance;

    public function __construct()
    {
        $config = Configuration::getDefaultConfiguration()
            ->setApiKey('api-key', env('BREVO_API_KEY'));

        $this->apiInstance = new TransactionalEmailsApi(
            new Client(),
            $config
        );
    }

    public function sendWelcomeEmail($user, $password)
    {
        $email = new SendSmtpEmail([
            'to' => [
                [
                    'email' => $user->email,
                    'name' => $user->firstName . ' ' . $user->lastName
                ]
            ],
            'subject' => 'Welcome to NCSR - Your Account Details',
            'htmlContent' => view('emails.welcome', [
                'user' => $user,
                'password' => $password,
                'loginUrl' => config('app.frontend_url') . '/'
            ])->render(),
            'sender' => [
                'name' => config('app.name'),
                'email' => env('MAIL_FROM_ADDRESS')
            ]
        ]);

        return $this->apiInstance->sendTransacEmail($email);
    }
}