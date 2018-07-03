<?php
/**
 * Created by PhpStorm.
 * User: yong
 * Date: 2018/6/15
 * Time: 11:59
 */

namespace Server;

use FastD\Packet\Json;
use FastD\Servitization\OnWorkerStart;
use FastD\Servitization\Server\TCPServer;
use Registry\Node;
use Runner\Validator\Validator;
use Support\Consumer\Broadcast;
use swoole_server;

class RegistryServer extends TCPServer
{
    use OnWorkerStart;
    /**
     * @var Node $node
     */
    protected $node;

    public function doWork(swoole_server $server, $fd, $data, $from_id)
    {
        //校验格式
        $data = Json::decode($data, true);
        if (!$data || !is_array($data)) {
            return 0;
        }

        try {
            $this->validate($data);
        } catch (RuntimeException $exception) {
            $server->send($fd, "error:{$exception->getMessage()}");
        }
        //生成注册数据
        $this->node = (new Node($data));

        //检查配置是否存在
        if (!config()->has('registry')) {
            return 0;
        }

        //注册配置
        registry()->register($this->node);

        if ($this->isBroadcast()) {
            $this->broadcastUpdateNode();
        }
        $server->send($fd, 'ok');
    }

    /**
     * @param swoole_server $server
     * @param $fd
     * @param $fromId
     */
    public function doClose(swoole_server $server, $fd, $fromId)
    {
        if ($this->node) {
            //服务断开连接，移除注册配置
            registry()->unregister($this->node);

            if ($this->isBroadcast()) {
                $this->broadcastUpdateNode();
            }
        }
        print_r('连接断开' . PHP_EOL);
    }

    /**
     * @param $data
     */
    protected function validate($data)
    {
        $rules = [
            'service_host' => 'required|url',
            'service_name' => 'required|string',
            'service_pid' => 'required|numeric',
        ];

        $validator = new Validator($data, $rules);
        $validator->validate();
    }

    /**
     * 广播到每个代理节点
     */
    protected function broadcastUpdateNode()
    {
        $client = new Broadcast(config()->get('producer_server.host'));
        $client->start();
        print_r('通知广播服务节点更新' . PHP_EOL);
    }

    /**
     * @return bool
     */
    protected function isBroadcast()
    {
        if (config()->has('producer_server.host')) {
            return true;
        }
        return false;
    }
}