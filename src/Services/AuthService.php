<?php

/**
 * This file is part of jurager/exchange1c package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Jurager\Exchange1C\Services;

use Jurager\Exchange1C\Config;
use Jurager\Exchange1C\Exceptions\Exchange1CException;
use Illuminate\Contracts\Session\Session;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\SessionInterface;

/**
 * Class AuthService.
 */
class AuthService
{
    public const SESSION_KEY = 'cml_import';

    /**
     * @var Request
     */
    private $request;
    /**
     * @var Config
     */
    private $config;

    /**
     * @var null|SessionInterface|Session
     */
    private $session;

    private $merchant_id = null;

    /**
     * AuthService constructor.
     *
     * @param Request $request
     * @param Config  $config
     */
    public function __construct(Request $request, Config $config)
    {
        $this->request = $request;
        $this->setSession();
        $this->config = $config;
    }

    /**
     * @throws Exchange1CException
     *
     * @return string
     */
    public function checkAuth()
    {
        if ($service = $this->config->getServiceClass(AuthService::class)) {
            $service = new $service();
            $arr = $service->checkAuth($this->request->server->get('PHP_AUTH_USER'), $this->request->server->get('PHP_AUTH_PW'));
            $auth_valid = $arr->result;
            $this->merchant_id = $arr->merchant_id;
        } else {
            $auth_valid = $this->request->server->get('PHP_AUTH_USER') === $this->config->getLogin() &&
                $this->request->server->get('PHP_AUTH_PW') === $this->config->getPassword();
        }

        if ($auth_valid ) {
            $this->session->save();
            $response = "success\n";
            $response .= $this->config->getSessionName()."\n";
            $response .= $this->session->getId()."\n";
            $response .= 'timestamp='.time();
            if ($this->session instanceof SessionInterface) {
                $this->session->set(self::SESSION_KEY.'_auth', $this->config->getLogin());
                $this->session->set(self::SESSION_KEY.'_merchant', $this->merchant_id);
            } elseif ($this->session instanceof Session) {
                $this->session->put(self::SESSION_KEY.'_auth', $this->config->getLogin());
                $this->session->put(self::SESSION_KEY.'_merchant', $this->merchant_id);
            } else {
                throw new Exchange1CException(sprintf('Session is not insatiable interface %s or %s', SessionInterface::class, Session::class));
            }
        } else {
            $response = "failure\n";
        }

        return $response;
    }

    /**
     * @throws Exchange1CException
     */
    public function auth(): void
    {
        $login = $this->config->getLogin();
        $user = $this->session->get(self::SESSION_KEY.'_auth', null);

        if (!$user || $user != $login) {
            throw new Exchange1CException('auth error');
        }
        $merchant_id = $this->session->get(self::SESSION_KEY.'_merchant', null);
        $this->config->setMerchant($merchant_id);
    }

    private function setSession(): void
    {
        if (!$this->request->getSession()) {
            $session = new \Symfony\Component\HttpFoundation\Session\Session();
            $session->start();
            $this->request->setSession($session);
        }

        $this->session = $this->request->getSession();
    }
}