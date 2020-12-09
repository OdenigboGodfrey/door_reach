<?php

namespace App\Http\Controllers\API\User;

use App\Http\Controllers\Controller;
use App\Models\Admin;
use App\Models\Client;
use App\Models\User;
use App\Models\UserPasswordReset;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Carbon\Carbon;
use Illuminate\Support\Facades\Hash;
use App\Utilities\Utility;

class PasswordController extends Controller
{
    private $out;
    private $_500 = 500;
    private $_422 = 422;
    private $date = '';
//    /** display positive message to user compulsory **/
//    private $positive = 1;
//    /** display message to user not compulsory **/
//    private $neutral = 0;
//    /** display negative message to user compulsory **/
//    private $negative = -1;
//    /** display error message to user compulsory **/
//    private $error = -2;

    public function __construct()
    {
        $this->out = new \Symfony\Component\Console\Output\ConsoleOutput();
        $this->date = Carbon::now()->toDateString();
    }

    protected function validator(array $data, $fields)
    {
        $validator =  Validator::make($data, $fields);
        if ($validator->fails()) {
            return \validator_result(true, $validator->errors()->all());
        }

        return \validator_result(false);
    }

    public function change_password(Request $request) {
        $validator = $this->validator($request->all(),[
            'old_password' => 'required|string',
            'new_password' => 'required|string',
            'confirm_password' => 'required|string',
        ]);

        if ($validator['failed']) {
            return \prepare_json(Utility::$negative, ['messages' => $validator['messages']],'',$status_code=$this->_422);
        }
        try {
            $data = $request->all();
            $user = auth()->guard('api-user')->user();

            if (!Hash::check($data['old_password'],$user->password)) {
                return \prepare_json(Utility::$negative, [],\get_api_string('old_password_dont_match'));
            }

            if ($data['new_password'] != $data['confirm_password']) {
                return \prepare_json(Utility::$negative, [],\get_api_string('passwords_dont_match'));
            }

            $user = User::findOrFail($user->id);

            $password = bcrypt($data['new_password']);

            $user->password = $password;

            $user->save();

            // update password client/admin table
            if ($user->user_type == get_api_string('user_type_client')) {
                $client = Client::where('user_id', $user->id)->first();
                if ($client) {
                    $client->password = $password;

                    $client->save();
                }
            }
            else if ($user->user_type == get_api_string('user_type_admin')) {
                $admin = Admin::where('user_id', $user->id)->first();
                if ($admin) {
                    $admin->password = $password;

                    $admin->save();
                }
            }

            return \prepare_json(Utility::$positive, [],\get_api_string('password_changed'));
        }
        catch (ModelNotFoundException $ex) {
            return \prepare_json(Utility::$negative, [], \get_api_string('not_found', 'User'), $this->_500);
        }
        catch (\Exception $ex) {
            return \prepare_json(Utility::$error, [],\get_api_string('error_occurred'), $this->_500);
        }
    }

    public function reset_password_token(Request $request) {
        $validator = $this->validator($request->all(),[
            'email' => 'required|string|email',
        ]);

        if ($validator['failed']) {
            return \prepare_json(Utility::$negative, ['messages' => $validator['messages']],'',$status_code=$this->_422);
        }
        try {
            $data = $request->all();
            $user = User::where('email', $data['email'])->first();

            if(!$user) {
                return \prepare_json(Utility::$negative, [],\get_api_string('not_found', 'User'));
            }

            $password_reset = UserPasswordReset::where(['email' => $user->email])->first();
            if ($password_reset) {
                $password_reset->token = generate_random_numbers(6);
                $password_reset->save();
            }
            else {
                $password_reset = UserPasswordReset::create([
                    'email' => $user->email,
                    'token' => generate_random_numbers(6),
                ]);
            }

            if ($user && $password_reset) {
//                $user->sendPasswordResetNotification($password_reset->token);
                if (array_key_exists('resend',$data)) {
                    return \prepare_json(Utility::$positive, ['user' => $user],\get_api_string('code_resent'));
                }

                return \prepare_json(Utility::$positive, ['user' => $user],\get_api_string('enter_reset_code'));
            }
            return \prepare_json(Utility::$negative, [],\get_api_string('error_occurred'));
        }
        catch (\Exception $ex) {
            return \prepare_json(Utility::$error, [],\get_api_string('error_occurred').$ex->getMessage(), $this->_500);
        }
    }

    public function validate_password_token(Request $request) {
        $validator = $this->validator($request->all(),[
            'token' => 'required|numeric',
        ]);

        if ($validator['failed']) {
            return \prepare_json(Utility::$negative, ['messages' => $validator['messages']],'',$status_code=$this->_422);
        }
        try {
            $data = $request->all();


            $password_reset = UserPasswordReset::where('token', $data['token'])
                ->first();
//            $password_reset = UserPasswordReset::all();
//            dd($password_reset);

            if (is_null($password_reset)) {
                return \prepare_json(Utility::$negative, [],\get_api_string('reset_code_wrong'));
            }

            if (Carbon::parse($password_reset->created_at)->addMinutes(720)->isPast()) {
                return \prepare_json(Utility::$negative, [],\get_api_string('invalid_action', 'Token expired'));
            }

            return \prepare_json(Utility::$positive, ['token' => $password_reset],\get_api_string('reset_code_valid'));
        }
        catch (\Exception $ex) {
            return \prepare_json(Utility::$error, [],\get_api_string('error_occurred'), $this->_500);
        }
    }

    public function reset_password(Request $request) {
        $validator = $this->validator($request->all(),[
            'email' => 'required|string|email',
            'new_password' => 'required|string',
            'confirm_password' => 'required|string',
        ]);

        if ($validator['failed']) {
            return \prepare_json(Utility::$negative, ['messages' => $validator['messages']],'',$status_code=$this->_422);
        }
        try {
            $data = $request->all();


            if ($data['new_password'] != $data['confirm_password']) {
                return \prepare_json(Utility::$negative, [],\get_api_string('passwords_dont_match'));
            }

            $user = User::where('email', $data['email'])->first();

            if (!$user) {
                return prepare_json(Utility::$negative, [], get_api_string('not_found', 'User'));
            }

            $password = bcrypt($data['new_password']);

            $user->password = $password;

            $user->save();

            // update password client/admin table
            if ($user->user_type == get_api_string('user_type_client')) {
                $client = Client::where('user_id', $user->id)->first();
                if ($client) {
                    $client->password = $password;

                    $client->save();
                }
            }
            else if ($user->user_type == get_api_string('user_type_admin')) {
                $admin = Admin::where('user_id', $user->id)->first();
                if ($admin) {
                    $admin->password = $password;

                    $admin->save();
                }
            }

            //reset password token
            $password_reset = UserPasswordReset::where(['email' => $user->email])->first();
            $password_reset->token = "";
            $password_reset->save();

            return \prepare_json(Utility::$positive, [],\get_api_string('password_changed'));
        }
        catch (ModelNotFoundException $ex) {
            return \prepare_json(Utility::$negative, [], \get_api_string('not_found', 'User'), $this->_500);
        }
        catch (\Exception $ex) {
            return \prepare_json(Utility::$error, [],\get_api_string('error_occurred'), $this->_500);
        }
    }
}
