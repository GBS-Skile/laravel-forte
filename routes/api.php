<?php

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

Route::prefix('v1/')->middleware(['api.headers'])->group(function () {
    Route::resource('users', 'UserController');
    Route::get('users/{user_id}/xsolla/token', 'UserController@xsollaToken');
    Route::post('users/{user_id}/signin', 'UserController@authentication');

    Route::post('users/{user_id}/discord', 'DiscordController@store');
    Route::put('users/{user_id}/discord', 'DiscordController@update');
    Route::delete('users/{user_id}/discord', 'DiscordController@destroy');
    Route::get('discords', 'DiscordController@index');
    Route::get('discords/{discord_id}', 'DiscordController@show');

    Route::get('items', 'ItemController@index');
    Route::get('items/{item_id}', 'ItemController@show');

    Route::get('users/{user_id}/items', 'UserItemController@index');
    Route::get('users/{user_id}/items/{user_item_id}', 'UserItemController@show');
    Route::post('users/{user_id}/items', 'UserItemController@store');
    Route::put('users/{user_id}/items/{item_id}', 'UserItemController@update');
    Route::delete('users/{user_id}/items/{item_id}', 'UserItemController@destroy');
    Route::post('users/{user_id}/points', 'PointController@store');
    Route::get('users/{user_id}/receipts', 'ReceiptController@index');
    Route::get('users/{user_id}/receipts/{receipt_id}', 'ReceiptController@show');

    Route::get('clients/token', 'ClientController@issue');
});

Route::post('v1/xsolla', 'XsollaWebhookController@index')->middleware('api.xsolla');

// Xsolla Test Case
Route::post('v1/test/xsolla', 'XsollaTestCaseController@index');
