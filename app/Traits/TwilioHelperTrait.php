<?php

namespace App\Traits;

use Twilio\Rest\Client;

trait TwilioHelperTrait
{

  
  
      public function sendOtpViaWhatsApp($toPhone, $otp, $msg)
      {
          $message =$msg??
              // 'forget_password' => "Your OTP for password reset is: $otp. It expires in 10 minutes.",
            "Your OTP is: $otp. It expires in 10 minutes.";
          
  
          try {
              $twilio = new Client(env('TWILIO_SID'), env('TWILIO_AUTH_TOKEN'));
              $twilio->messages->create(
                  'whatsapp:' . $toPhone,
                  [
                      'from' => 'whatsapp:' . env('TWILIO_WHATSAPP_FROM'),
                      'body' => $message,
                  ]
              );
              return true;
            } catch (\Exception $e) {
            \Log::error('Twilio error: ' . $e->getMessage());
            throw new \Exception('Failed to send OTP: ' . $e->getMessage());
              return false;
          }
      }
  
  
}
