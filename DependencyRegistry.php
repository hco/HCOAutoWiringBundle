<?php
namespace HCO\AutoWiringBundle;

use HCO\AutoWiringBundle\Exception;

class DependencyRegistry
{
    private $storage;

    public function __construct()
    {
        $this->storage = array();
    }

    /**
     *
     *
     * @param $className
     * @param $serviceId
     * @param string $qualifier
     */
    public function register($className, $serviceId, $qualifier = null, $primary = false)
    {
        if (!isset($this->storage[$className])) {
            $this->storage[$className] = array(
                'unqualified' => array(),
                'qualified'   => array(),
            );
        }

        $this->storage[$className]['unqualified'][] = $serviceId;

        if ($qualifier !== null) {
            $this->registerQualified($className, $serviceId, $qualifier);
        }

        if($primary) {
            if(isset($this->storage[$className]['primary'])) {
                throw new Exception(
                    sprintf(
                        'Multiple primary services registered for class %s: %s and %s',
                        $className,
                        $serviceId,
                        $this->storage[$className]['primary']
                    )
                );
            }

            $this->storage[$className]['primary'] = $serviceId;
        }
    }


    public function findUnqualified($className)
    {
        if (!isset($this->storage[$className])) {
            throw new Exception(
                sprintf(
                    'Class %s is not registered with the DependencyRegistry',
                    $className
                )
            );
        }

        if(isset($this->storage[$className]['primary'])) {
            return $this->storage[$className]['primary'];
        }

        if (count($this->storage[$className]['unqualified']) !== 1) {
            throw new Exception(
                sprintf(
                    'There\'s not exactly one unqualified service registered for %s, thus autowiring is impossible.',
                    $className
                )
            );
        }

        return $this->storage[$className]['unqualified'][0];
    }

    public function findQualified($className, $qualifier)
    {
        if (!isset($this->storage[$className]) || !isset($this->storage[$className]['qualified'][$qualifier])) {
            throw new Exception(
                sprintf(
                    'Class %s with qualifier %s is not registered with the DependencyRegistry',
                    $className,
                    $qualifier
                )
            );
        }

        if (count($this->storage[$className]['qualified'][$qualifier]) !== 1) {
            throw new Exception(
                sprintf(
                    'There\'s not exactly one service registered for %s with qualifier %s, thus autowiring is impossible.',
                    $className
                )
            );
        }

        return $this->storage[$className]['qualified'][$qualifier][0];
    }

    private function registerQualified($className, $serviceId, $qualifier)
    {
        if (!isset($this->storage[$className]['qualified'][$qualifier])) {
            $this->storage[$className]['qualified'][$qualifier] = array();
        }

        $this->storage[$className]['qualified'][$qualifier][] = $serviceId;
    }
}
