<?php namespace App\Http\Controllers;

use App\Http\Requests;
use App\Http\Controllers\Controller;
use  Mail;
use Illuminate\Http\Request;

class FormPostController extends Controller
{

    public function prayerTaskForceSignup(Request $request)
    {
        $this->validate($request, [
            'email' => 'required|email',
            'name' => 'required',
            'language' => 'required|in:"en","es"'
        ]);
        $email = $request->input('email');
        $name = $request->input('name');
        $language = $request->input('language');

        $data = array('email' => $email,
            'name' => $name,
            'language' => $language == 'es' ? 'Spanish' : 'English');
//        dd($data);
        Mail::send('emails.prayertaskforcenotification',
            $data,
            function ($message) {
                $message->subject('Registration from Network211 Reporter App');
                $message->from('prayerteam@network211.com', "Prayer Force");
//                $message->to('ksorbo@network211.com',"Keith Sorbo");
                $message->to('prayerteam@network211.com', 'Prayer Task Force');

            });

        Mail::send('emails.prayertaskforcethankyou',
            $data,
            function ($message) use ($data) {
//                dd($data);
                $l = $data['language'];
                $subject = ($l == 'English') ? 'Thanks for signing up' : 'Gracias por registrarte';
//                $name = $data['name'];
                $message->subject($subject);
                $message->from('prayerteam@network211.com',"Prayer Team");
                $message->to($data['email'], $data['name']);
            });
        echo json_encode(array('Success. Thanks.'));

    }

}
