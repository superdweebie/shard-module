<?php
/**
 * @link       http://zoopcommerce.github.io/shard-module
 * @package    Zoop
 * @license    MIT
 */
namespace Zoop\ShardModule;

use Zend\EventManager\EventManagerInterface;
use Zend\EventManager\ListenerAggregateInterface;
use Zend\ModuleManager\ModuleEvent;
use Zend\StdLib\ArrayUtils;
use Zoop\Shard\Manifest;

/**
 *
 * @since   1.0
 * @author  Tim Roediger <superdweebie@gmail.com>
 */

class ConfigMergeListener implements ListenerAggregateInterface
{
    /**
     * @var \Zend\Stdlib\CallbackHandler[]
     */
    protected $listeners = array();

    /**
     * Attach to an event manager
     *
     * @param  EventManagerInterface $events
     * @return void
     */
    public function attach(EventManagerInterface $events)
    {
        $this->listeners[] = $events->attach(ModuleEvent::EVENT_MERGE_CONFIG, array($this, 'onConfigMerge'), 1);
    }

    /**
     * Detach all our listeners from the event manager
     *
     * @param  EventManagerInterface $events
     * @return void
     */
    public function detach(EventManagerInterface $events)
    {
        foreach ($this->listeners as $index => $listener) {
            if ($events->detach($listener)) {
                unset($this->listeners[$index]);
            }
        }
    }

    /**
     *
     * @param \Zend\ModuleManager\ModuleEvent $event
     */
    public function onConfigMerge(ModuleEvent $event) {

        $config = $event->getConfigListener()->getMergedConfig(false);

        foreach($config['zoop']['shard']['manifest'] as $name => $manifestConfig){
            if (!isset($manifestConfig['initalized']) || !$manifestConfig['initalized']){
                $manifest = new Manifest($manifestConfig);
                $manifestConfig = $manifest->toArray();
                $config['zoop']['shard']['manifest'][$name] = $manifestConfig;

                //add delegators
                $documentManagerConfig = $config;
                foreach(explode('.', $manifestConfig['document_manager']) as $key){
                    $documentManagerConfig = $documentManagerConfig[$key];
                }

                $delegatorConfig = [
                    'delegators' => [
                        $manifestConfig['document_manager'] => ['shard.' . $name . '.documentManagerDelegatorFactory'],
                        $documentManagerConfig['eventmanager'] => ['shard.' . $name . '.eventManagerDelegatorFactory'],
                        $documentManagerConfig['configuration'] => ['shard.' .$name . '.configurationDelegatorFactory']
                    ]
                ];
                $config['service_manager'] = ArrayUtils::merge($config['service_manager'], $delegatorConfig);
            }
        }

        if (!isset($config['zoop']['shard']['manifest']['default']) ||
            !isset($config['zoop']['shard']['manifest']['default']['extension_configs']['extension.dojo'])
        ) {
            //remove dojo_src.default route if shard.dojo.default is not configured
            unset($config['router']['routes']['dojo.default']);
        }

        if (!isset($config['zoop']['shard']['manifest']['default']) ||
            !isset($config['zoop']['shard']['manifest']['default']['extension_configs']['extension.rest'])
        ) {
            //remove rest.default route if shard.rest.default is not configured
            unset($config['router']['routes']['rest.default']);
        }

        $event->getConfigListener()->setMergedConfig($config);
    }
}