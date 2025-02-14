<?php

/*
 * This file is part of NotificationPusher.
 *
 * (c) 2013 Cédric Dugat <cedric@dugat.me>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Sly\NotificationPusher\Adapter;

use InvalidArgumentException;
use Sly\NotificationPusher\Collection\DeviceCollection;
use Sly\NotificationPusher\Exception\PushException;
use Sly\NotificationPusher\Model\DeviceInterface;
use Sly\NotificationPusher\Model\GcmMessage;
use Sly\NotificationPusher\Model\MessageInterface;
use Sly\NotificationPusher\Model\PushInterface;
use Laminas\Http\Client as HttpClient;
use Laminas\Http\Client\Adapter\Socket as HttpSocketAdapter;
use ZendService\Google\Exception\RuntimeException as ServiceRuntimeException;
use ZendService\Google\Fcm\Client as ServiceClient;
use ZendService\Google\Fcm\Message as ServiceMessage;

/**
 * @uses BaseAdapter
 *
 * @author Cédric Dugat <cedric@dugat.me>
 */
class Gcm extends BaseAdapter
{
    /**
     * @var HttpClient
     */
    private HttpClient $httpClient;

    /**
     * @var ServiceClient
     */
    private ServiceClient $openedClient;

    /**
     * {@inheritdoc}
     */
    public function supports(string $token): bool
    {
        return !empty($token);
    }

    /**
     * {@inheritdoc}
     *
     * @throws PushException
     */
    public function push(PushInterface $push): DeviceCollection
    {
        $client = $this->getOpenedClient();
        $pushedDevices = new DeviceCollection();
        $tokens = array_chunk($push->getDevices()->getTokens(), 100);

        foreach ($tokens as $tokensRange) {
            $message = $this->getServiceMessageFromOrigin($tokensRange, $push->getMessage());

            try {

                $response = $client->send($message);
                $responseResults = $response->getResults();

                foreach ($tokensRange as $token) {
                    /** @var DeviceInterface $device */
                    $device = $push->getDevices()->get($token);

                    // map the overall response object
                    // into a per device response
                    $tokenResponse = [];
                    if (isset($responseResults[$token]) && is_array($responseResults[$token])) {
                        $tokenResponse = $responseResults[$token];
                    }

                    $responseData = $response->getResponse();
                    if ($responseData && is_array($responseData)) {
                        $tokenResponse = array_merge(
                            $tokenResponse,
                            array_diff_key($responseData, ['results' => true])
                        );
                    }

                    $push->addResponse($device, $tokenResponse);

                    $pushedDevices->add($device);

                    $this->response->addOriginalResponse($device, $response);
                    $this->response->addParsedResponse($device, $tokenResponse);
                }
            } catch (ServiceRuntimeException $e) {
                throw new PushException($e->getMessage());
            }
        }

        return $pushedDevices;
    }

    /**
     * Get opened client.
     *
     * @return ServiceClient
     */
    public function getOpenedClient(): ServiceClient
    {
        if (!isset($this->openedClient)) {
            $this->openedClient = new ServiceClient();
            $this->openedClient->setApiKey($this->getParameter('apiKey'));

            $newClient = new HttpClient(
                null,
                [
                    'adapter' => 'Zend\Http\Client\Adapter\Socket',
                    'sslverifypeer' => false,
                ]
            );

            $this->openedClient->setHttpClient($newClient);
        }

        return $this->openedClient;
    }

    /**
     * Get service message from origin.
     *
     * @param array $tokens Tokens
     * @param MessageInterface $message Message
     *
     * @return ServiceMessage
     */
    public function getServiceMessageFromOrigin(array $tokens, MessageInterface $message): ServiceMessage
    {
        $data = $message->getOptions();
        $data['message'] = $message->getText();

        $serviceMessage = new ServiceMessage();
        $serviceMessage->setRegistrationIds($tokens);

        if (isset($data['notificationData']) && !empty($data['notificationData'])) {
            $serviceMessage->setNotification($data['notificationData']);
            unset($data['notificationData']);
        }

        if ($message instanceof GcmMessage) {
            $serviceMessage->setNotification($message->getNotificationData());
        }

        $serviceMessage->setData($data);

        $serviceMessage->setCollapseKey($this->getParameter('collapseKey'));
        $serviceMessage->setPriority($this->getParameter('priority', 'normal'));
        $serviceMessage->setRestrictedPackageName($this->getParameter('restrictedPackageName'));
        $serviceMessage->setDelayWhileIdle($this->getParameter('delayWhileIdle', false));
        $serviceMessage->setTimeToLive($this->getParameter('ttl', 600));
        $serviceMessage->setDryRun($this->getParameter('dryRun', false));

        return $serviceMessage;
    }

    /**
     * {@inheritdoc}
     */
    public function getDefinedParameters(): array
    {
        return [
            'collapseKey',
            'priority',
            'delayWhileIdle',
            'ttl',
            'restrictedPackageName',
            'dryRun',
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function getDefaultParameters(): array
    {
        return [];
    }

    /**
     * {@inheritdoc}
     */
    public function getRequiredParameters(): array
    {
        return ['apiKey'];
    }

    /**
     * Get the current Zend Http Client instance.
     *
     * @return HttpClient
     * @noinspection PhpUnused
     */
    public function getHttpClient(): HttpClient
    {
        return $this->httpClient;
    }

    /**
     * Overrides the default Http Client.
     *
     * @param HttpClient $client
     * @noinspection PhpUnused
     */
    public function setHttpClient(HttpClient $client)
    {
        $this->httpClient = $client;
    }

    /**
     * Send custom parameters to the Http Adapter without overriding the Http Client.
     *
     * @param array $config
     *
     * @throws InvalidArgumentException
     * @noinspection PhpUnused
     */
    public function setAdapterParameters(array $config = [])
    {
        if (!is_array($config) || empty($config)) {
            throw new InvalidArgumentException('$config must be an associative array with at least 1 item.');
        }

        /** @noinspection PhpConditionAlreadyCheckedInspection */
        if ($this->httpClient === null) {
            $this->httpClient = new HttpClient();
            $this->httpClient->setAdapter(new HttpSocketAdapter());
        }

        $this->httpClient->getAdapter()->setOptions($config);
    }
}
