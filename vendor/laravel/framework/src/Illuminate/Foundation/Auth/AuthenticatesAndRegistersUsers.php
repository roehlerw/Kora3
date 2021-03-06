<?php namespace Illuminate\Foundation\Auth;


use ReCaptcha\ReCaptcha;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Contracts\Auth\Guard;
use Illuminate\Contracts\Auth\Registrar;

trait AuthenticatesAndRegistersUsers {

	/**
	 * The Guard implementation.
	 *
	 * @var Guard
	 */
	protected $auth;

	/**
	 * The registrar implementation.
	 *
	 * @var Registrar
	 */
	protected $registrar;

	/**
	 * Show the application registration form.
	 *
	 * @return \Illuminate\Http\Response
	 */
	public function getRegister()
	{
		return view('auth.register');
	}

	/**
	 * Handle a registration request for the application.
	 *
	 * @param  \Illuminate\Http\Request  $request
	 * @return \Illuminate\Http\Response
	 */
	public function postRegister(Request $request)
	{
        $recaptcha = new ReCaptcha(env('RECAPTCHA_PRIVATE_KEY'));
        $resp = $recaptcha->verify($request['g-recaptcha-response'], $_SERVER['REMOTE_ADDR']);

        if($resp->isSuccess());
        else{
            flash()->overlay('ReCAPTCHA incomplete!', 'Whoops.');

            $validator = $this->registrar->validator($request->all());
            $this->throwValidationException($request, $validator);
        }


		$validator = $this->registrar->validator($request->all());

		if ($validator->fails())
		{
			$this->throwValidationException(
				$request, $validator
			);
		}

		$this->auth->login($this->registrar->create($request->all()));

        //This will not error because of the statement above.
        $token = \Auth::user()->token;

        Mail::send('emails.activation', compact('token'), function($message)
        {
            $message->from(env('MAIL_FROM_ADDRESS'));
            $message->to(\Auth::user()->email);
            $message->subject('Kora Account Activation');
        });


		return redirect($this->redirectPath());
	}

	/**
	 * Show the application login form.
	 *
	 * @return \Illuminate\Http\Response
	 */
	public function getLogin()
	{
		return view('auth.login');
	}

	/**
	 * Handle a login request to the application.
	 *
	 * @param  \Illuminate\Http\Request  $request
	 * @return \Illuminate\Http\Response
	 */
	public function postLogin(Request $request)
	{
        $this->validate($request, [
            'email' => 'required', 'password' => 'required'
        ]);

        $credentials = $request->only('email', 'password');
        if (strpos($credentials['email'], '@') == false) {
            //logging in with username not email, so change the column-name
            $credentials['username'] = $credentials['email'];
            unset($credentials['email']);
        }

		if ($this->auth->attempt($credentials, $request->has('remember')))
		{
			return redirect()->intended($this->redirectPath());
		}

		return redirect($this->loginPath())
					->withInput($request->only('email', 'remember'))
					->withErrors([
						'email' => 'These credentials do not match our records.',
					]);
	}

	/**
	 * Log the user out of the application.
	 *
	 * @return \Illuminate\Http\Response
	 */
	public function getLogout()
	{
		$this->auth->logout();

		return redirect('/');
	}

	/**
	 * Get the post register / login redirect path.
	 *
	 * @return string
	 */
	public function redirectPath()
	{
		if (property_exists($this, 'redirectPath'))
		{
			return $this->redirectPath;
		}

		return property_exists($this, 'redirectTo') ? $this->redirectTo : '/';
	}

	/**
	 * Get the path to the login route.
	 *
	 * @return string
	 */
	public function loginPath()
	{
		return property_exists($this, 'loginPath') ? $this->loginPath : '/auth/login';
	}

    /**
     * Creates the regToken for activating users.
     *
     * @return string
     */
    public static function makeRegToken(){
        $valid = 'abcdefghijklmnopqrstuvwxyz';
        $valid .= 'ABCDEFGHIJKLMNOPQRSTUVWXYZ';
        $valid .= '0123456789';

        $token = '';
        for ($i = 0; $i < 10; $i++){
            $token .= $valid[( rand() % 62 )];
        }
        return $token;
    }

}
