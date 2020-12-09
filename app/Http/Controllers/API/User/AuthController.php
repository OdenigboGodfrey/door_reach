<?php

namespace App\Http\Controllers\API\User;

use App\Http\Controllers\Controller;
use App\Models\Admin;
use App\Models\Client;
use App\Utilities\Utility;
use Illuminate\Http\Request;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    private $_500 = 500;
    private $_422 = 422;
    private $date = '';
    /** display positive message to user compulsory **/
    private $positive = 1;
    /** display message to user not compulsory **/
    private $neutral = 0;
    /** display negative message to user compulsory **/
    private $negative = -1;
    /** display error message to user compulsory **/
    private $error = -2;


    public function __construct()
    {
        $this->out = new \Symfony\Component\Console\Output\ConsoleOutput();

        $this->date = Carbon::now();
    }

    public function init_registration(Request $request) {
        /**
            user_types: 0=>customer,1=>client
         **/
        $validator = Utility::validator($request->all(),[
            'first_name' => 'required|string|max:50',
            'last_name' => 'required|string|max:50',
            'email' => 'required|email|unique:users',
            'password' => 'required|min:3',
            'user_type' => 'required|string',
        ]);

        if ($validator['failed']) {
            return \prepare_json(Utility::$negative, ['messages' => $validator['messages']],'',$status_code=Utility::$_422);
        }

        try {
            $data = $request->all();
            
            $password = bcrypt($data['password']);
            $user = User::create([
                'first_name' => $data['first_name'],
                'last_name' => $data['last_name'],
                'email' => $data['email'],
                'password' => $password,
                'user_type' => $data['user_type'],
            ]);



            if ($user) {
                //create access token
                $user['token'] = $user->createToken('new_user_'.$data['user_type'])->accessToken;
                if ($user->user_type == get_api_string('user_type_client')) {
                    $client = Client::create([
                       'user_id' => $user->id,
                        'password' => $password,
                    ]);

                    if(!isset($client)) {
                        return prepare_json(Utility::$negative, [], \get_api_string('client_failed_signup_ok'));
                    }
                    /** get client info from client table */
                    $client['token'] = $client->createToken('new_user_'.$data['user_type'])->accessToken;
                    $user['client'] = $client;
//                    $user['client_token'] =  $client->createToken('new_user_'.$data['user_type'])->accessToken;
                }
                else if ($user->user_type == get_api_string('user_type_admin')) {
                    $admin = Admin::create([
                        'user_id' => $user->id,
                        'password' => $password,
                    ]);

                    if(!isset($admin)) {
                        return prepare_json(Utility::$negative, [], \get_api_string('admin_failed_signup'));
                    }
                    /** get admin info from admin table */
                    $admin['token'] = $admin->createToken('new_user_'.$data['user_type'])->accessToken;
                    $user['admin'] = $admin;
//                    $user['admin_token'] =  $admin->createToken('new_user_'.$data['user_type'])->accessToken;
                }
                return prepare_json(Utility::$positive, $user, \get_api_string('signup_ok'), Utility::$_201);
            }
            else {
                return prepare_json(Utility::$negative, [], \get_api_string('signup_error'));
            }
        }
        catch (\Exception $ex) {
            return \prepare_json(Utility::$error, [],\get_api_string('error_occurred').$ex->getMessage(), Utility::$_500);
        }
    }

    public function login(Request $request)
    {
        /**
         * user_types: 0=>customer,1=>client
         **/
        $validator = Utility::validator($request->all(), [
            'email' => 'required|email',
            'password' => 'required|min:3',

        ]);

        if ($validator['failed']) {
            return \prepare_json(Utility::$negative, ['messages' => $validator['messages']],'',$status_code=Utility::$_422);
        }

        try {
            $data = $request->all();

            $user = User::where("email", $request->email)->first();
            if($user) {
//                if (get_user_type($user->user_type) != $data['user_type']) {
//                    return \prepare_json(Utility::$negative, ['email'=> $user->email]
//                        , \get_api_string('wrong_user_type'));
//                }
                if (Hash::check($data['password'], $user->password)) {
                    $user['token'] =  $user->createToken('new_login_'.$user->user_type)->accessToken;
                    if ($user->user_type == get_api_string('user_type_client')) {
                        /** get client info from client table */
                        $client = $user->client()->first();

                        $client['token'] = $client->createToken('new_login'.$user->user_type)->accessToken;
                        $user['client'] = $client;
//                        $user['client_token'] =  $client->createToken('new_login'.$data['user_type'])->accessToken;
                    }
                    else if ($user->user_type == get_api_string('user_type_admin')) {
                        /** get admin info from admin table */

                        $admin = $user->admin()->first();

                        $admin['token'] = $admin->createToken('new_login'.$user->user_type)->accessToken;
                        $user['admin'] = $admin;
//                        $user['admin_token'] =  $admin->createToken('new_login'.$data['user_type'])->accessToken;
                    }
                    return \prepare_json(Utility::$neutral, $user, 'Login Successful');
                }
                else {
                    return \prepare_json(Utility::$negative, ['email'=> $user->email], \get_api_string('account_not_found'));
                }
            }
            else {
                return \prepare_json(Utility::$negative, ['email'=> $data['email']], \get_api_string('account_not_found'));
            }
        }
        catch (\Exception $ex) {
            return \prepare_json(Utility::$error, [],\get_api_string('error_occurred').$ex->getMessage(), Utility::$_500);
        }
    }

    public function logout(Request $request) {
        try {
            auth()->logout();

            // invalidate user token

            return \prepare_json(Utility::$positive, [], get_api_string('generic_okay'));
        }
        catch (\Exception $ex) {
            return \prepare_json(Utility::$error, [],\get_api_string('error_occurred').$ex->getMessage(), Utility::$_500);
        }
    }


}
