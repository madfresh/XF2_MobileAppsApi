<?php

namespace Truonglv\Api\Service;

use XF\Entity\User;
use XF\Service\AbstractService;

class Subscription extends AbstractService
{
    protected $user;
    protected $pushToken;

    public function __construct(\XF\App $app, User $user, $pushToken)
    {
        parent::__construct($app);

        $this->user = $user;
        $this->pushToken = $pushToken;
    }

    public function unsubscribe()
    {
        /** @var \Truonglv\Api\Entity\Subscription[] $subscriptions */
        $subscriptions = $this->finder('Truonglv\Api:Subscription')
            ->where('user_id', $this->user->user_id)
            ->where('device_token', $this->pushToken)
            ->fetch();
        foreach ($subscriptions as $subscription) {
            $subscription->delete();
        }
    }

    public function subscribe(array $extra)
    {
        /** @var \Truonglv\Api\Entity\Subscription|null $exists */
        $exists = $this->finder('Truonglv\Api:Subscription')
            ->where('user_id', $this->user->user_id)
            ->where('device_token', $this->pushToken)
            ->fetchOne();

        if ($exists) {
            $subscription = $exists;
        } else {
            /** @var \Truonglv\Api\Entity\Subscription $subscription */
            $subscription = $this->em()->create('Truonglv\Api:Subscription');
            $subscription->user_id = $this->user->user_id;
            $subscription->username = $this->user->username;
            $subscription->device_token = $this->pushToken;
        }

        $subscription->subscribed_date = \XF::$time;
        $subscription->bulkSet($extra);

        try {
            $subscription->save(false);
        } catch (\XF\Db\DuplicateKeyException $e) {
        }
    }
}