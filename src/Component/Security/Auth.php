<?php

namespace bblue\ruby\Component\Security;

use bblue\ruby\Component\EventDispatcher\EventDispatcher;
use bblue\ruby\Component\EventDispatcher\EventDispatcherAwareInterface;
use bblue\ruby\Component\EventDispatcher\EventDispatcherAwareTrait;
use bblue\ruby\Entities\User;
use bblue\ruby\Component\Core\AbstractRequest as Request;
use Psr\Log\LoggerAwareInterface;
use bblue\ruby\Component\Logger\LoggerAwareTrait;

/**
 * The authentication class accepts an auth token and finalizes the authentication process
 *
 * @author Aleksander Lanes
 * @todo finne ut hva som skjer dersom jeg IKKE sletter session storage ved behov. Når jeg skriver dette slettes ikke "gamle" tokens. Dette kan nok skape problemer.
 * @todo burde jeg ikke ha en eksplisitt "handle" et eller nnet sted, slik at jeg definitivt kjører auth? Slik det står nå vil jeg unngå auth dersom jeg bre ikke kaller getUser
 */
final class Auth implements EventDispatcherAwareInterface, LoggerAwareInterface
{
    use EventDispatcherAwareTrait;
    use LoggerAwareTrait;

    /**
     * A user checker
     * @var iUserChecker
     */
    private $userChecker;

    /**
     * An auth token storage system
     * @var iAuthStorage
     */
    private $storage;

    /**
     * The auth token
     * @var iAuthToken
     */
    private $token;

    /**
     * The request made to the site
     * @var Request
     */
    private $request;

    /**
     * @param iUserProvider $userProvider
     * @param iAuthStorage $storage
     * @param EventDispatcher $ed
     */
    public function __construct(iAuthStorage $storage, Request $request, EventDispatcher $ed)
    {
        $this->storage  = $storage;
        $this->setEventDispatcher($ed);
        $this->request = $request;
    }

    /**
     * Retrieves the authenticated user object
     *
     * @return User
     */
    public function getUser()
    {
        if($token = $this->_getToken()) {
            $this->logger->debug('Auth service returning user object');
            return $token->getUser();
        } else {
            throw new \Exception('Unable to retrieve any auth token objects. Unable to retrieve any user objects');
        }
    }

    /**
     * Main method of this class. Handles an auth token.
     *
     * The token and associated user object is checked for validity before the token is stored in the storage mechanism
     *
     * @param iAuthToken $token
     * @throws \Exception
     */
    public function handle(iAuthToken $token)
    {
        $this->logger->info('Auth token received. Trying to authenticate');

        $tokenChecker = new AuthTokenChecker($token, $this->request);

        if($tokenChecker->isValid()) {
            $token->isValid(true);
            $this->logger->notice('Authenticated as '. $token->getUser()->getUsername());

            // Store the token in object memory
            $this->_setToken($token);

            // Store the token in token storage mechanism
            $this->storage->storeToken($token);

            return true;
        } else {
            throw new AuthException(implode("\n", $tokenChecker->getErrors()));
        }
    }

    /**
     * Returns the stored auth token
     *
     * If not value is stored, a dispatcher event is triggered and loading a token is tried once more
     *
     * @throws \Exception
     * @return AuthToken
     */
    private function _getToken()
    {
        // Check if we have already a token in object memory
        if(!$this->token) {
            // Try to load a token from token storage
            if($token = $this->storage->getToken()) {
                $this->logger->info('Auth token found in auth storage');
                $this->handle($token);
            } else {
                $this->logger->info('No auth token stored in system. Sending auth beacon.');
                // As a last attempt, create a signal to allow external parties to add a token //@todo dette er muligens et digert sikkerhetshull. Jeg exposer hele auth systemet
                if($this->eventDispatcher->dispatch(AuthEvent::NO_AUTH_TOKEN, ['auth'=>$this])) {
                    $this->logger->debug('Auth beacon was picked up');
                }
            }
        }

        return $this->token;
    }

    private function _setToken(iAuthToken $token)
    {
        $this->token = $token;
    }

}

final class AuthException extends \RuntimeException {};