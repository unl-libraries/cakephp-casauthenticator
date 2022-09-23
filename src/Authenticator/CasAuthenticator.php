<?php
/**
 * Copyright 2021 Stacy Rickel
 */
namespace CasAuthenticator\Authenticator;


use Authentication\Identifier\IdentifierInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use phpCAS;

class CasAuthenticator extends AbstractAuthenticator implements PersistenceInterface
{    

    protected $_defaultConfig = [
        'hostname' => null,
        'port' => 443,
        'uri' => '',
        'sessionKey' => 'phpCAS'
    ];

    /**
     * {@inheritDoc}
     */
    public function __construct(IdentifierInterface $identifier, array $config = [])
    {
        //Configuration params can be set via global Configure::write or via Auth->config
        //Auth->config params override global Configure, so we'll pass them in last
        parent::__construct($identifier, $config);
        //$this->setConfig($config);

        //Get the merged config settings
        $settings = $this->getConfig();
       
    
        if (!empty($settings['debug'])) {
            phpCAS::setDebug(LOGS . 'phpCas.log');
        }

        //The "isInitialized" check isn't necessary during normal use,
        //but during *testing* if Authentication is tested more than once, then
        //the fact that phpCAS uses a static global initialization can
        //cause problems
        if (!phpCAS::isInitialized()) {
           // if(isset($_SESSION)){
                phpCAS::client(CAS_VERSION_2_0, $settings['hostname'], $settings['port'], $settings['uri'],false);
           // }
            //else phpCAS::client(CAS_VERSION_2_0, $settings['hostname'], $settings['port'], $settings['uri']);
        }

        if (!empty($settings['curlopts'])) {
            foreach ($settings['curlopts'] as $key => $val) {
                phpCAS::setExtraCurlOption($key, $val);
            }
        }

        if (empty($settings['cert_path'])) {
            phpCAS::setNoCasServerValidation();
        } else {
            phpCAS::setCasServerCACert($settings['cert_path']);
        }

    }

    /**
     *  @param \Psr\Http\Message\ServerRequestInterface $request request
     *  @return \Authentication\Authenticator\ResultInterface
     */
    public function authenticate(ServerRequestInterface $request): ResultInterface
    {
        phpCAS::handleLogoutRequests(false);
        
        phpCAS::forceAuthentication();
        //If we get here, then phpCAS::forceAuthentication returned
        //successfully and we are thus authenticated
        
        // Persist the user into configured authenticators.
        $user = array_merge(['username'=>phpCAS::getUser()], phpCAS::getAttributes() );
        $user = $this->_identifier->identify($user);
        if (empty($user)) {
            return new Result(null, Result::FAILURE_CREDENTIALS_INVALID);
        }
        return new Result($user, Result::SUCCESS);
    }
    /**
     * @inheritDoc
     */
    public function persistIdentity(ServerRequestInterface $request, ResponseInterface $response, $identity): array
    {
        $sessionKey = $this->getConfig('sessionKey');
        /** @var \Cake\Http\Session $session */
        $session = $request->getSession();
        
        if (!$session->check($sessionKey)) {
          //  $session->renew();
            $session->write($sessionKey, $identity);
        }
        
        return [
            'request' => $request,
            'response' => $response,
        ];
    }
    


    /**
     * Log a user out. Interrupts initial call to AuthComponent logout
     * to handle CAS logout, which happens on separate CAS server
     *
     * @param Event $event Auth.logout event
     *
     * @return void
     */
    public function clearIdentity(ServerRequestInterface $request, ResponseInterface $response): array
    {
       // debug("cas clearIdentity called");
        
        if (phpCAS::isAuthenticated()) {
            //Step 1. When the client clicks logout, this will run.
            //        phpCAS::logout will redirect the client to the CAS server.
            //        The CAS server will, in turn, redirect the client back to
            //        this same logout URL.
            //
            //        phpCAS will stop script execution after it sends the redirect
            //        header, which is a problem because CakePHP still thinks the
            //        user is logged in. See Step 2.
      
            $redirectUrl = $this->getConfig('logoutRedirect');

            if (empty($redirectUrl)) {
                $redirectUrl = '/';
            }
            $redirectUrl = 'https://'.$_SERVER['HTTP_HOST'].$redirectUrl;
           //debug($redirectUrl);
            $session = $request->getSession();
            $session->delete('phpCAS');
            //$session->renew();
            phpCAS::logoutWithRedirectService($redirectUrl);
        }
        //Step 2. We reach this line when the CAS server has redirected the
        //        client back to us. Do nothing in this block; then after this
        //        method returns, CakePHP will do whatever is necessary to log
        //        the user out from its end (destroying the session or whatever).
        return [
            'request'=>$request->withoutAttribute($this->getConfig('identityAttribute')),
            'response'=>$response,
        ];
    }
    /**
     * Get the Controller callbacks this Component is interested in.
     *
     * @return array
     */
    public function implementedEvents(): array
    {
        return [
            'Auth.logout' => 'logout',
            
        ];
    }

}
