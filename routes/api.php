<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/

Route::middleware('auth:api')->get('/orig-user', function (Request $request) {
    return $request->user();
});

Route::get('/orig-user1', function (Request $request) {
    return \prepare_json(-1, [], "Hello");
});

Route::namespace('API')->group(function(){
    Route::group(['prefix' => 'user'], function(){
        Route::namespace('User')->group(function(){
            Route::post('/login', 'AuthController@login')->name('api.user.login');

            Route::group(['prefix' => 'profile'], function(){
                Route::post('/edit', 'ProfileController@edit_profile')->name('api.user.profile.edit')->middleware('auth:api-user');
            });

            Route::group(['prefix' => 'register'], function(){
                Route::post('/init', 'AuthController@init_registration')->name('api.user.init_registration');
            });

            Route::group(['prefix' => 'password'], function(){
                Route::post('/change', 'PasswordController@change_password')->name('api.user.password.change')->middleware('auth:api-user');
                Route::post('/init_reset', 'PasswordController@reset_password_token')->name('api.user.password.init_reset');
                Route::post('/validate', 'PasswordController@validate_password_token')->name('api.user.password.validate');
                Route::post('/reset', 'PasswordController@reset_password')->name('api.user.password.reset');
            });

        });
    });
    Route::group(['prefix' => 'order'], function(){
        Route::namespace('Order')->group(function(){
            Route::post('/create', 'OrderController@create_order')->name('api.order.create')->middleware('auth:api-client');
            Route::post('/get', 'OrderController@get_orders')->name('api.order.get')->middleware('auth:api-user');
        });
    });
});
