<?php

namespace Truonglv\Api\Api\Controller;

use XF\Entity\UserAlert;
use XF\Mvc\ParameterBag;
use XF\Mvc\Entity\Entity;
use XF\Api\Controller\AbstractController;

class Notification extends AbstractController
{
    public function actionGet(ParameterBag $params)
    {
        $this->assertRegisteredUser();

        /** @var UserAlert|null $alert */
        $alert = $this->finder('XF:UserAlert')->whereId($params->alert_id)->fetchOne();
        if ($alert === null || $alert->alerted_user_id !== \XF::visitor()->user_id) {
            return $this->notFound();
        }

        $content = $alert->getContent();
        if ($content === null
            || !\in_array($alert->content_type, \Truonglv\Api\App::getSupportAlertContentTypes(), true)
        ) {
            return $this->notFound();
        }

        if ($alert->view_date <= 0) {
            $alert->view_date = \XF::$time;
            $alert->save();
        }

        return $this->apiResult([
            'notification' => $alert->toApiResult(Entity::VERBOSITY_VERBOSE),
            'content' => $content->toApiResult()
        ]);
    }
}
