<?php 

namespace Vetruvet\PhpRedis;

use \Redis;

class Database extends \Illuminate\Redis\Database {

    /**
     * Create a new aggregate client supporting sharding.
     *
     * @param  array  $servers
     * @return array
     */
    protected function createAggregateClient(array $servers) {
        $options = array(
        	'lazy_connect' => true,
	        'pconnect'     => false,
	        'timeout'      => 0,
	    );

        $cluster = array();
        foreach ($servers as $key => $server) {
        	if ($key === 'cluster') continue;

        	$host    = empty($server['host'])    ? '127.0.0.1' : $server['host'];
            $port    = empty($server['port'])    ? '6379'      : $server['port'];

            $serializer = Redis::SERIALIZER_NONE;
            if (!empty($server['serializer'])) {
            	if ($server['serializer'] === 'none') {
            		$serializer = Redis::SERIALIZER_PHP;
            	} else if ($server['serializer'] === 'igbinary') {
                    if (defined('Redis::SERIALIZER_IGBINARY')) {
                        $serializer = Redis::SERIALIZER_IGBINARY;
                    } else {
                        $serializer = Redis::SERIALIZER_PHP;
                    }
            	}
            }

            $cluster[$host.':'.$port] = array(
            	'prefix'     => empty($server['prefix'])   ? '' : $server['prefix'],
            	'database'   => empty($server['database']) ? 0  : $server['database'],
            	'serializer' => $serializer,
        	);

            if (isset($server['persistent'])) {
            	$options['pconnect'] = $options['pconnect'] && $server['persistent'];
            } else {
				$options['pconnect'] = false;
            }

            if (!empty($server['timeout'])) {
            	$options['timeout'] = max($options['timeout'], $server['timeout']);
            }
        }

        $ra = new RedisArray(array_keys($cluster), $options);

        foreach ($cluster as $host => $options) {
        	$redis = $ra->_instance($host);
        	$redis->setOption(Redis::OPT_PREFIX, $options['prefix']);
        	$redis->setOption(Redis::OPT_SERIALIZER, $options['serializer']);
        	$redis->select($options['database']);
        }

        return array('default' => $ra);
    }

    /**
     * Create an array of single connection clients.
     *
     * @param  array  $servers
     * @return array
     */
    protected function createSingleClients(array $servers) {
        $clients = array();

        foreach ($servers as $key => $server) {
        	if ($key === 'cluster') continue;

            $redis = new Redis();

            $host    = empty($server['host'])    ? '127.0.0.1' : $server['host'];
            $port    = empty($server['port'])    ? '6379'      : $server['port'];
            $timeout = empty($server['timeout']) ? 0           : $server['timeout'];

            if (isset($server['persistent']) && $server['persistent']) {
            	$redis->pconnect($host, $port, $timeout);
            } else {
            	$redis->connect($host, $port, $timeout);
            }

            if (!empty($server['prefix'])) {
            	$redis->setOption(Redis::OPT_PREFIX, $server['prefix']);
            }

            if (!empty($server['database'])) {
            	$redis->select($server['database']);
            }

            if (!empty($server['serializer'])) {
            	$serializer = Redis::SERIALIZER_NONE;
            	if ($server['serializer'] === 'php') {
            		$serializer = Redis::SERIALIZER_PHP;
            	} else if ($server['serializer'] === 'igbinary') {
            		if (defined('Redis::SERIALIZER_IGBINARY')) {
                        $serializer = Redis::SERIALIZER_IGBINARY;
                    } else {
                        $serializer = Redis::SERIALIZER_PHP;
                    }
            	}
            	$redis->setOption(Redis::OPT_SERIALIZER, $serializer);
            }

            $clients[$key] = $redis;
        }

        return $clients;
    }
}
