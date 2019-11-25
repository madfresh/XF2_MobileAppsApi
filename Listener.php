<?php

namespace Truonglv\Api;

use XF\Container;
use XF\Entity\ApiKey;
use Truonglv\Api\Entity\Log;
use Truonglv\Api\Http\Request;
use Truonglv\Api\Entity\AccessToken;
use XF\Api\Controller\AbstractController;
use XF\Http\ResponseFile;
use XF\Http\ResponseStream;

class Listener
{
    /**
     * @param \XF\Api\App $app
     * @return void
     */
    public static function appApiSetup(\XF\Api\App $app)
    {
        $app->container()->set('request', function (Container $c) {
            $request = new Http\Request($c['inputFilterer']);
            $request->setCookiePrefix($c['config']['cookie']['prefix']);

            return $request;
        });
    }

    /**
     * @param \XF\Http\Request $request
     * @param mixed $result
     * @param mixed $error
     * @param mixed $code
     * @return void
     */
    public static function appApiValidateRequest(\XF\Http\Request $request, &$result, &$error, &$code)
    {
        $ourApiKey = $request->getServer(App::HEADER_KEY_API_KEY);
        if ($ourApiKey === false || !App::isRequestFromApp($request)) {
            return;
        }

        $app = \XF::app();

        $apiKey = $request->getApiKey();
        $ourKey = $app->options()->tApi_apiKey;

        if (count($ourKey) > 0) {
            /** @var ApiKey|null $apiKeyEntity */
            $apiKeyEntity = $app->em()->find('XF:ApiKey', $ourKey['apiKeyId']);
            if (!$apiKeyEntity) {
                return;
            }

            if ($apiKey === $apiKeyEntity->api_key) {
                // DO NOT allow request with api_key in header.
                $error = 'api_error.api_key_not_found';
                $code = 401;
                $result = false;

                return;
            }
        }

        if ($ourKey['key'] !== $ourApiKey) {
            $error = 'api_error.api_key_not_found';
            $code = 401;
            $result = false;

            return;
        }

        /** @var ApiKey|null $apiKeyEntity */
        $apiKeyEntity = $app->em()->find('XF:ApiKey', $ourKey['apiKeyId']);
        if (!$apiKeyEntity) {
            $error = 'api_error.api_key_not_found';
            $code = 401;
            $result = false;

            return;
        }

        /** @var Request $mixed */
        $mixed = $request;
        $mixed->setApiKey($apiKeyEntity->api_key);
        $mixed->setApiUser(0);

        $accessToken = $request->getServer(App::HEADER_KEY_ACCESS_TOKEN);

        /** @var AccessToken|null $token */
        $token = $app->finder('Truonglv\Api:AccessToken')
            ->where('token', $accessToken)
            ->whereOr([
                ['expire_date', '=', 0],
                ['expire_date', '>', \XF::$time]
            ])
            ->fetchOne();

        if ($token !== null) {
            $mixed->setApiUser($token->user_id);
        }
    }

    /**
     * @param \XF\Mvc\Controller $controller
     * @param mixed $action
     * @param \XF\Mvc\ParameterBag $params
     * @return void
     */
    public static function onControllerPreDispatch(\XF\Mvc\Controller $controller, $action, \XF\Mvc\ParameterBag $params)
    {
        if (!$controller instanceof AbstractController) {
            return;
        }

        App::$enableLogging = App::isRequestFromApp($controller->request());
    }

    /**
     * @param \XF\Api\App $app
     * @param \XF\Http\Response $response
     * @throws \XF\PrintableException
     * @return void
     */
    public static function onAppApiComplete(\XF\Api\App $app, \XF\Http\Response &$response)
    {
        if (!App::$enableLogging) {
            return;
        }

        $request = $app->request();
        /** @var \Truonglv\Api\Repository\Log $logRepo */
        $logRepo = $app->repository('Truonglv\Api:Log');

        /** @var Log $log */
        $log = $app->em()->create('Truonglv\Api:Log');
        $log->user_id = \XF::visitor()->user_id;

        $log->app_version = $request->getServer(App::HEADER_KEY_APP_VERSION);

        $log->end_point = $request->getRequestUri();
        $log->method = strtoupper($request->getRequestMethod());

        $post = $_POST;
        if (isset($post['password'])) {
            $post['password'] = '******';
        }

        $log->payload = [
            '_POST' => $post
        ];

        $log->response_code = $response->httpCode();
        $body = $response->body();
        if ($body instanceof ResponseFile
            || $body instanceof ResponseStream
        ) {
            $log->response = '';
        } else {
            $log->response = trim($logRepo->prepareDataForLog($body));
        }

        $log->save();
    }
}
