<?php
/**
 * Created by PhpStorm.
 * User: yong
 * Date: 2018/6/15
 * Time: 14:03
 */

namespace Registry\Adapter;


use Registry\Node;
use Registry\Registry;
use SebastianBergmann\GlobalState\RuntimeException;

class RedisRegistry extends Registry
{
    /**
     * @var \Redis
     */
    protected $redis;

    /**
     * @var
     */
    protected $config = [];

    /**
     * RedisRegistry constructor.
     * @param $config
     */
    public function __construct($config)
    {
        if (null === !extension_loaded('redis') && !class_exists(\Predis\Client::class)) {
            throw new RuntimeException('Cannot find the "redis" extension, and "predis/predis" is not installed');
        }

        $class = extension_loaded('redis') ? \Redis::class : \Predis\Client::class;

        if (is_a($class, \Redis::class, true)) {
            $this->redis = new $class;
            $this->redis->connect($config['host'], $config['port']);
            if (isset($config['auth']) && $config['auth']) {
                $this->redis->auth($config['auth']);
            }
            if (isset($config['dbindex']) && is_int($config['dbindex'])) {
                $this->redis->select(1);
            }
        } else {
            $this->redis = new $class([
                'scheme' => 'tcp',
                'host' => $config['host'],
                'database' => $config['dbindex'] ?? null,
                'password' => $config['auth'] ?? null
            ]);
        }
    }

    /**
     * @param Node $node
     * @return Node
     */
    public function register(Node $node)
    {
        $key = $this->getKey($node->getService());
        $hashKey = $node->getHash();

        if (false !== $this->redis->hSet($key, $hashKey, $node->json())) {
            return $node;
        }

        throw new \RuntimeException($this->redis->getLastError());
    }

    /**
     * @param Node $node
     * @return bool
     */
    public function unregister(Node $node)
    {
        $key = $this->getKey($node->getService());
        $hashKey = $node->getHash();

        return $this->redis->hDel($key, $hashKey);
    }

    /**
     * @return array
     */
    public function list()
    {
        $this->redis->setOption(\Redis::OPT_SCAN, \Redis::SCAN_RETRY);

        $iterator = null;
        $services = [];
        while ($keys = $this->redis->scan($iterator, $this->getPrefix() . '*')) {
            foreach ($keys as $key) {
                $services[] = str_replace($this->getPrefix(), '', $key);
            }
        }
        return $services;
    }

    /**
     * @param $service
     * @return array
     */
    public function show($service)
    {
        $service = $this->getKey($service);

        if (!$this->redis->exists($service)) {
            return [];
        }

        $nodes = $this->redis->hGetAll($service);

        foreach ($nodes as $node => $data) {
            $nodes[$node] = json_decode($data, true);
        }

        return $nodes;
    }
}
