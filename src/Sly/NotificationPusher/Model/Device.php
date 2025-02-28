<?php

/*
 * This file is part of NotificationPusher.
 *
 * (c) 2013 Cédric Dugat <cedric@dugat.me>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Sly\NotificationPusher\Model;

/**
 * @author Cédric Dugat <cedric@dugat.me>
 */
class Device extends BaseParameteredModel implements DeviceInterface
{
    /**
     * @var string
     */
    private string $token;

    /**
     * @param string $token Token
     * @param array $parameters Parameters
     */
    public function __construct(string $token, array $parameters = [])
    {
        $this->token = $token;
        $this->parameters = $parameters;
    }

    /**
     * @return string
     */
    public function getToken(): string
    {
        return $this->token;
    }

    /**
     * @param string $token Token
     *
     * @return DeviceInterface
     */
    public function setToken(string $token): DeviceInterface
    {
        $this->token = $token;

        return $this;
    }
}
