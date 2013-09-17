# HCO Auto Wiring Bundle

This bundle eases the usage of the symfony2 DIC by injecting (constructor)
dependencies automatically.

All classes that should be autowired have to have a specific tag within the
DIC.  The Bundle will then iterate over the constructor-parameters and will try
to find a service that satisfies the typehint of every parameter.

If there is not exactly one service that satisfies the typehint, the autowiring will fail.
Qualifiers and Primary Services might help you to solve that issue, though :)

The autowiring process is done during compile time of the service container,
which means that it **should not have any performance impact** in production.

## Qualifiers
A service can have a so called "qualifier", which will allow autowiring of typehints which could be
satisfied by several services.

An example could be your database connection. Imagine, you have two database connections within your service container,
one for write requests and one for readonly requests.
You could now give both services a qualifier, "write" for the write-database connection and "readonly" for the readonly database connection.

If a service now has an unqualified typehint for a database connection, the autowiring will fail.
But you can now qualify a typehint with the string we used before, so that a service will be wired to one specific database connection.

## Primary Services
A service an be tagged to be a so called primary service.
When a dependency should be outwired, and there is exactly one primary service, that will be outwired.
There can still be several non-primary services for a class, but they will be ignored.

If two services are declared to be primary which are of the same type, the compilation of the container will fail.

## Example

Imagine you define the following class with the following service configuration
```php
class ServiceWithDependency
{
    public $stdObject;

    public function __construct(StdClass $stdObject)
    {
        $this->stdObject = $stdObject;
    }
}
```

```xml
<service id="foo" class="ServiceWithDependency">
    <tag name="hco.autowire" />
</service>
<service id="bar" class="StdClass">
</service>
```

This will automatically inject the service *bar* into the service *foo*.

### Example with qualifiers
We're gonna reuse the *ServiceWithDependency* class from above, but modify the service configuration.

```xml
<service id="foo" class="ServiceWithDependency">
    <tag name="hco.autowire" />
</service>
<service id="bar" class="StdClass">
</service>
<service id="baz" class="StdClass">
</service>
```

That will fail, as the autowiring bundle does not know which service should be injected anymore.
This is where qualifiers come into play. We can slightly modify the class and service definition.

```php
use HCO\AutoWiringBundle\Annotation\RequireQualifier;

class ServiceWithDependency
{
    public $stdObject;

    /**
     * @RequireQualifier(param="stdObject", qualifier="readonly")
     */
    public function __construct(StdClass $stdObject)
    {
        $this->stdObject = $stdObject;
    }
}
```

```xml
<service id="foo" class="ServiceWithDependency">
    <tag name="hco.autowire" />
</service>
<service id="bar" class="StdClass">
</service>
<service id="baz" class="StdClass">
    <tag name="hco.autowire.qualifier" qualifier="readonly" />
</service>
```
This will injcet the service *baz* into the service *foobar*, as *baz* is qualified as *readonly*.

### Example with Primary
If we tag the service *baz* as primary, it will be used as the StdClass dependency of our *ServiceWithDependency*.
See the following services.xml as an example.
```xml
<services>
    <service id="foo" class="ServiceWithDependency">
        <tag name="hco.autowire" />
    </service>
    <service id="bar" class="\StdClass">
    </service>
    <service id="baz" class="\StdClass">
        <tag name="hco.autowire.primary" />
    </service>

</services>
```
