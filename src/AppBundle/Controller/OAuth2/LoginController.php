<?php

namespace AppBundle\Controller\OAuth2;

use AppBundle\Component\RateLimit\LoginFailRateLimiter;
use AppBundle\Controller\LoginBindController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class LoginController extends LoginBindController
{
    public function mainAction(Request $request)
    {
        $oauthUser = $this->getOauthUser($request);

        return $this->render('oauth2/index.html.twig', array(
            'oauthUser' => $oauthUser,
        ));
    }

    public function bindAccountAction(Request $request)
    {
        $oauthUser = $this->getOauthUser($request);

        $type = $request->request->get('accountType');
        $account = $request->request->get('account');

        $user = $this->getUserByTypeAndAccount($type, $account);
        $oauthUser->accountType = $type;
        $oauthUser->account = $account;

        if ($user) {
            $redirectUrl = $this->generateUrl('oauth2_login_bind_login');
        } else {
            $redirectUrl = $this->generateUrl('oauth2_login_create');
        }

        $request->getSession()->set('oauth_user', $oauthUser);

        return $this->redirect($redirectUrl);
    }

    public function bindLoginAction(Request $request)
    {
        $oauthUser = $this->getOauthUser($request);
        if ('POST' == $request->getMethod()) {
            $password = $request->request->get('password');

            $this->loginAttemptCheck($oauthUser->account, $request);

            $isSuccess = $this->bindUser($oauthUser, $password);

            return $isSuccess ?
                $this->createSuccessJsonResponse(array('url' => $this->generateUrl('oauth2_login_success', array('isCreate' => 0)))) :
                $this->createFailJsonResponse(array('message' => $this->trans('user.settings.security.password_modify.incorrect_password')));
        } else {
            $user = $this->getUserByTypeAndAccount($oauthUser->accountType, $oauthUser->account);

            return $this->render('oauth2/bind-login.html.twig', array(
                'oauthUser' => $oauthUser,
                'esUser' => $user,
            ));
        }
    }

    private function bindUser(OauthUser $oauthUser, $password)
    {
        $user = $this->getUserByTypeAndAccount($oauthUser->accountType, $oauthUser->account);

        $isCorrectPassword = $this->getUserService()->verifyPassword($user['id'], $password);
        if ($isCorrectPassword) {
            $this->getUserService()->bindUser($oauthUser->type, $oauthUser->id, $user['id'], null);

            return true;
        } else {
            return false;
        }
    }

    public function successAction(Request $request)
    {
        $oauthUser = $this->getOauthUser($request);

        $user = $this->getUserByTypeAndAccount($oauthUser->accountType, $oauthUser->account);

        if (!$user) {
            throw new NotFoundHttpException();
        }

        $request->getSession()->set('oauth_user', null);
        $this->authenticateUser($user);

        return $this->render('oauth2/success.html.twig', array(
            'oauthUser' => $oauthUser,
            'isCreate' => $request->query->get('isCreate'),
        ));
    }

    public function createAction(Request $request)
    {
        $oauthUser = $this->getOauthUser($request);

        return $this->render('oauth2/create-account.html.twig', array(
            'oauthUser' => $oauthUser,
        ));
    }

    private function getUserByTypeAndAccount($type, $account)
    {
        $user = null;
        switch ($type) {
            case 'email':
                $user = $this->getUserService()->getUserByEmail($account);
                break;
            case 'mobile':
                $user = $this->getUserService()->getUserByVerifiedMobile($account);
                break;
            default:
                throw new NotFoundHttpException();
        }

        return $user;
    }

    /**
     * @param \Symfony\Component\HttpFoundation\Request $request
     *
     * @return \AppBundle\Controller\OAuth2\OauthUser
     */
    private function getOauthUser(Request $request)
    {
        $oauthUser = $request->getSession()->get('oauth_user');
        if (!$oauthUser) {
            throw new NotFoundHttpException();
        }

        return $oauthUser;
    }

    private function loginAttemptCheck($account, Request $request)
    {
        $limiter = new LoginFailRateLimiter($this->getBiz());
        $request->request->set('username', $account);
        $limiter->handle($request);
    }
}
