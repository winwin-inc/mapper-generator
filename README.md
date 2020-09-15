# Generate mapper class code

受 [mapstruct](https://mapstruct.org/) 项目启发，移植相关功能。

## 简介
项目中模型包括 DO（Data Object), DTO (Data Transfer Object), VO (View Object) 等，经常需要在模型之间进行转换，模型转换的过程称为 mapping ，包含 mapping 函数的类称为 mapper。编写 mapper 是非常枯燥，而且容易出错的过程。通过代码生成，可以简化 mapper 类的编写。
## 安装
```bash
composer require --dev winwin/mapper-generator
```
## 快速开始
假如我们项目中有如下类定义：
```php
<?php
class CarType extends \kuiper\helper\Enum {
    public const SUV = 'suv';
    public const MINI = 'mini';
}

class Car {
    /**
     * @var string|null
     */
    private $make;
    /**
     * @var int|null
     */
    private $numberOfSeats;
    /**
     * @var CarType|null
     */
    private $type;
 
    //constructor, getters, setters etc.
}

class CarDto {
    /**
     * @var string|null
     */
    private $make;
    /**
     * @var int|null
     */
    private $seatCount;
    /**
     * @var string|null
     */
    private $type;
 
    //constructor, getters, setters etc.
}
```
我们可以定义这样一个 mapper 类转换 `Car` 对象为 `CarDto` 对象：
```php
<?php

use winwin\mapper\annotations\Mapper;
use winwin\mapper\annotations\Mapping;

/**
 * @Mapper
 */
class CarMapper
{
    /**
     * @Mapping(target="seatCount", source="numberOfSeats")
     */
    public function carToCarDto(\Car $car) : \CarDto
    {

    }
}
```
运行命令：
```bash
./vendor/bin/mapper-generator src/CarMapper.php
```
生成代码如下：
```php
<?php

use winwin\mapper\annotations\Mapper;
use winwin\mapper\annotations\Mapping;

/**
 * @Mapper
 */
class CarMapper
{
    /**
     * @Mapping(target="seatCount", source="numberOfSeats")
     */
    public function carToCarDto(\Car $car) : \CarDto
    {
        $carDto = new \CarDto();
        $carDto->setMake($car->getMake());
        $carDto->setSeatCount($car->getNumberOfSeats());
        $carDto->setType($car->getType() === null ? null : $car->getType()->name);
        return $carDto;
    }
}
```
## 生成器原理
只有使用 `@\winwin\mapper\annotations\Mapper` 注解标记的类才会进行代码生成。代码生成过程使用 [PHP Parser](https://github.com/nikic/PHP-Parser) 解析代码为 AST，替换需要 mapping 函数的方法体。可以确保只修改 mapping 函数部分，而其他函数仍保持不变。
这个类中符合以下特征的方法会生成 mapping 函数体：

- 必须是 public 实例方法 (即不能是 static 方法)
- 函数原型满足以下情况：
1. 一个参数，一个返回值，且都有类型声明。此时参数为转换来源对象，返回值为转换生成对象。

例如：
```php
<?php

public function toCarDto(Car $car): CarDto
{
}
```

2. 多个参数，一个返回值，参数中有且仅有一个使用 `@MappingSource` 指定为转换来源对象

例如：
```php
<?php

/**
 * @MappingSource("car")
 */
public function toCarDto(Car $car, $arg1, $arg2): CarDto
{
}
```

3. 两个参数，都有类型声明，返回值为 void，有且仅有一个使用 `@MapperTarget` 或 `@MappingSource` 指定其中一个参数角色

例如：
```php
<?php

/**
 * @MappingTarget("dto")
 */
public function updateCarDto(Car $car, CarDto $dto): void
{
}
```

4. 多个参数，返回值为 void 有且仅有一个使用 `@MappingSource` 指定转换来源对象, `@MappingTarget` 指定为转换生成对象

例如：
```php
<?php

/**
 * @MappingSource("car")
 * @MappingTarget("dto")
 */
public function updateCarDto(Car $car, CarDto $dto, $arg1, $arg2): void
{
}
```
以上四种情况的函数可以提取出没有歧义的 source 对象和 target 对象，将从 source 对象和 target 对象中提取字段进行映射。source 对象字段规则为 public 属性或 getX(), isX(), hasX() 方法；target 对象字段规则为 public 属性或 `setX($value)` 方法。
mapping 生成的代码都是通过对象的 getter, setter 或者公开属性值赋值方式，而不是通过反射，性能上和手写是相同的。
## Mapping 配置
默认字段按同名规则进行映射。如果字段名不一致，可以使用 `@Mapping` 注解指定映射规则，参考前面 `CarMapper` 示例。
如果两个方法需要使用相同映射规则，可以通过 `@InheritConfiguration` 继承，例如：
```php
<?php
class CarMapper
{
    /**
     * @Mapping(target="seatCount", source="numberOfSeats")
     */
    public function carToCarDto(\Car $car) : \CarDto
    {
    }

    /**
     * @InheritConfiguration("carToCarDto")
     */
    public function updateCarDto(\Car $car, \CarDto $carDto): void
    {
    }
}
```
字段名反向映射可以通过 `@InheritInverseConfiguration` 注解实现，例如：
```php
<?php
class CarMapper
{
    /**
     * @Mapping(target="seatCount", source="numberOfSeats")
     */
    public function carToCarDto(\Car $car) : \CarDto
    {
    }

    /**
     * @InheritInverseConfiguration("carToCarDto")
     */
    public function carDtoToCar(\CarDto $carDto): \Car
    {
    }
}
```


字段类型不同情况下将使用以下转换规则：

1. php 原生标量类型（int, bool, float, double, string 等）通过类型强制转换
1. \DateTime 和 string 类型可以相互转换，默认格式为 `Y-m-d H:i:s` ，如果需要使用其他格式，可以在 `@Mapping` 中使用 `dateFormat` 指定
1. \kuiper\helper\Enum 和 int, string 可以相互转换

其他类型不匹配将产生错误。
对于类型无法自动完成转换的情况，可以通过 `@Mapping` 中 `expression` 或 `qualifiedByName` 实现。
`expression` 用于设置 php 表达式，例如：
```php
<?php
class CarMapper
{
    /**
     * @Mapping(target="large", expression="$car->getNumberOfSeats()>20")
     */
    public function carToCarDto(\Car $car) : \CarDto
    {
    }
}
```
`qualifiedByName`用于指定 mapper 类中的方法进行转换，例如：
```php
<?php
class CarMapper
{
    /**
     * @Mapping(target="large", source="numberOfSeats", qualifiedByName="isLarge")
     */
    public function carToCarDto(\Car $car) : \CarDto
    {
    }
  
    private function isLarge(int $numberOfSeats): bool
    {
        return $numberOfSeats > 20;
    }
}
```
`condition` 用于设置一个表达式，当满足表达式值的时候才进行转换，例如：
```php
<?php
class CarMapper
{
    /**
     * @Mapping(target="seatCount", source="numberOfSeats", condition="> 0")
     */
    public function carToCarDto(\Car $car) : \CarDto
    {
    }
}
```
