<?php
/**
 * Created by PhpStorm.
 * User: Rndwiga
 * Date: 2/28/2017
 * Time: 10:34 PM
 */

namespace Tyondo\LaravelNotifications\Helpers;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use App\Notifications\newUserLogin;
use Tyondo\LaravelNotifications\Notifications\ConfirmEmailNotification;
use App\User;
use Carbon\Carbon;

class LaravelRegistrationHelper
{
    protected $db; //activatin
    protected $table = 'user_activations';
    protected $resendAfter = 24;
    /*
      Code for sending email Activation
    */
    /**
     * sends activation code to users upon registration
     *
     * @param  App\User $user
     * @return \Illuminate\Http\Response
     */
    public function sendActivationMail($user)
    {
        if ($user->activated || !$this->shouldSend($user)) {
            return;
        }
        $userToken = $this->createActivation($user);
        //$userToken = User::find($user->id);
        $user->notify(new userAccountActivationNotification($userToken));
    }
    /**
     * sends activation code to users upon registration
     *
     * @param  App\User $user
     * @return data $activation
     */
    private function shouldSend($user)
    {
        $activation = $this->getActivation($user);
        return $activation === null || strtotime($activation->created_at) + 60 * 60 * $this->resendAfter < time();
    }
    public function activateUser($token)
    {
        $activation = $this->getActivationByToken($token);

        if ($activation === null) {
            return null;
        }

        $user = User::find($activation->user_id);

        $user->activated = true;

        $user->save();

        $this->deleteActivation($token);

        return $user;
    }
    /*
      Get the activation token from the activation tb using user_id
    */
    public function getActivation($user)
    {
        return DB::table($this->table)->where('user_id', $user->id)->first();
    }
    public function getActivationByToken($token)
    {
        return DB::table($this->table)->where('token', $token)->first();
    }
    public function deleteActivation($token)
    {
        return DB::table($this->table)->where('token', $token)->delete();
    }
    public function createActivation($user)
    {
        $activation = $this->getActivation($user);
        if (!$activation) {
            return $this->createToken($user);
        }
        return $this->regenerateToken($user);
    }
    protected function getToken()
    {
        return hash_hmac('sha256', str_random(40), config('app.key'));
    }
    private function generateToken($user)
    {
        $token = $this->getToken();
        DB::table($this->table)->where('user_id', $user->id)->update(['token'=> $token]);
        return $token;
    }
    private function regenerateToken($user)
    {
        $token = $this->getToken();
        DB::table($this->table)->where('user_id', $user->id)->update([
            'token' => $token,
            'created_at' => new Carbon()
        ]);
        return $token;
    }
    private function createToken($user)
    {
        $token = $this->getToken();
        DB::table($this->table)->insert([
            'user_id' => $user->id,
            'token' => $token,
        ]);
        return $token;
    }

}