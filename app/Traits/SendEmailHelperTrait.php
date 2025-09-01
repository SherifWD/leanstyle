<?php

namespace App\Traits;

use App\Mail\SendStudentOtp;
use App\Models\Student;
use Illuminate\Support\Facades\Mail;

trait SendEmailHelperTrait
{

  
   private function sendOtp(Student $student,$otp,$msg)
    {
       
        Mail::to($student->email)->send(new SendStudentOtp($student, $otp));
        
    }
  
}
