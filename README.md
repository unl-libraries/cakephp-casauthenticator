
Provides CAS Authenticator methods for CakePHP 4
Tested with cakePHP 4.3 and phpCAS 1.6.1 

## Installation

You can install this plugin into your CakePHP application using
[composer](https://getcomposer.org):

copy the files into the vendor directory then
```
php composer.phar require unl-libraries/cakephp-casauthenticator
```


## Usage

Within your `src\Application.php` file:

Load the authenticator by calling it with the configuration items

update the userModel if using something other than Users 
```
//try to load the CasAuthenticator
$authenticationService->loadAuthenticator('Authentication.Cas',[
            'hostname'=>'cas hostname',
            'port'=>443,
            'uri'=>'cas uri path',
	    'service_base_url' => '', #include the base url of your service with the protocal such as 'https://server-name.unl.edu'
            'userModel'=>'Users',
            'fields' =>  [
                'username' => ['username','email']
             ],
            'loginUrl' => Router::url('/users/login'),
            'logoutRedirect' => Router::url('/pages/home')
        ]);
```
