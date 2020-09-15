# Generate mapper class code

受 [mapstruct](https://mapstruct.org/) 项目启发，移植相关功能。

## 快速开始

```bash
composer require --dev winwin/mapper-generator
```

首先在项目中声明 mapper 类：

```php
<?php

use winwin\mapper\annotations\Mapper;

/**
 * @Mapper()
 */
class CustomerMapper 
{
    public function toCustomer(CustomDto $dto): Customer
    {
    }
}
```

```bash
./vendor/bin/mapper-generator src/application/mapper
```

mapper 方法具有以下特征：
- 必须是 public 实例方法 (即不能是 static 方法)
- 函数原型满足：
 1. 一个参数，一个返回值，且都有类型声明。此时参数为转换来源对象，返回值为转换生成对象
 2. 多个参数，一个返回值，参数中有且仅有一个使用 `@MappingSource` 指定为转换来源对象。
 3. 两个参数，都有类型声明，返回值为 void，有且仅有一个使用 `@MapperTarget` 或 `@MappingSource` 指定其中一个参数角色
 4. 多个参数，返回值为 void 有且仅有一个使用 `@MappingSource` 指定转换来源对象, `@MappingTarget` 指定为转换生成对象
 

