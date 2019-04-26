<?php

namespace Common\Factory;

use Zend\ServiceManager\FactoryInterface;
use Zend\ServiceManager\ServiceLocatorInterface;
use Zend\ServiceManager\ServiceManager;

/**
 * Class DbAdapterFactory
 * @package Common\Factory
 */
class DbAdapterFactory implements FactoryInterface
{
    /**
     * Create service
     *
     * @param ServiceLocatorInterface $serviceLocator
     * @return callable|mixed
     * @throws \Exception
     */
    public function createService(ServiceLocatorInterface $serviceLocator)
    {
        $dbParameters = $serviceLocator->get('Config')['db'];
        $adapterFactory = new AdapterServiceFactory($dbParameters, new ServiceManager());
        $adapterType = $dbParameters['is_profiler']
            ? AdapterServiceFactory::ADAPTER_PROFILER
            : null;
        $adapter = $adapterFactory->createService($adapterType);

        return $adapter($serviceLocator);
    }
}