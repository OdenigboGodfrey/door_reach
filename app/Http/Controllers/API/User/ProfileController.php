<?php

namespace App\Http\Controllers\API\User;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Utilities\Utility;
use Illuminate\Http\Request;

class ProfileController extends Controller
{
    public function edit_profile(Request $request) {
        /**
        user_types: 0=>Client,Admin=>1
         **/
        $validator = Utility::validator($request->all(),[
            'first_name' => 'string|max:50',
            'last_name' => 'string|max:50',
            'dob' => 'date',
            'phone' => 'max:20',
            'bio' => 'max:255',
            'gender' => 'max:6',
        ]);

        if ($validator['failed']) {
            return \prepare_json(Utility::$negative, ['messages' => $validator['messages']],'',$status_code=Utility::$_422);
        }

        try {
            $data = $request->all();
            $user = auth()->guard('api-user')->user();
//            $user = User::where('id', $data['user_id'])->first();

            if ($user) {
                $record = [
                    'first_name' => $data['first_name']?? $user->first_name,
                    'last_name' => $data['last_name']?? $user->last_name,
                    'phone' => $data['phone'] ?? $user->phone,
                    'gender' => $data['gender'] ?? $user->gender,
                    'dob' => $data['dob'] ?? $user->dob,
                ];

                $user->update($record);
                if ($user) {
                    /*** record was saved */
                    $user = User::where('id', $data['user_id'])->first();
                    return prepare_json(Utility::$positive, ['user' => $user], \get_api_string('profile_edit_ok', $user->first_name));
                }
                else {
                    /*** record was not saved */
                    return prepare_json(Utility::$negative, [], \get_api_string('profile_edit_not_ok', $user->first_name));
                }
            }
            else {
                return prepare_json(Utility::$negative, [], \get_api_string('no_record', 'User'));
            }
        }
        catch (\Exception $ex) {
            return \prepare_json(Utility::$error, [],\get_api_string('error_occurred').$ex->getMessage(), Utility::$_500);
        }
    }
}
