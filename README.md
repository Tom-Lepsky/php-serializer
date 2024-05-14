# **PHP Serializer**

Fast, simple and reliable php serializer, allows convert object to string of the specified format (only json and urlencoded  
supported now) and vice versa: creating object from string (json format) - deserialization process  
### Supporting 
* _normalization groups_
* _circular reference_
* _normalization depth_
* _composite keys_
* _array types (via attributes)_
* _enums (include typed)_
* _straight access to properties or via getter/setter_

## **Usage**
- [Basic](#basic)
- [Skipping null values](#skipping-null-values)
- [Using groups](#using-groups)
- [Naming](#naming)
- [Typed arrays](#typed-arrays)
- [Serialization and Deserialization strategy](#serialization-and-deserialization-strategy)

### Basic
Let's imagine that we have a class 
```php
class Order
{
    public string $id;
    public float $price;
    public array $products;
}
```
Create an instance
```php
$order = new Order();
$order->id = 1;
$order->price = 100.5;
```
Now, init the serializer
```php
$serializer = new Serializer([new ObjectNormalizer()], ['json' => new JsonEncoder()]);
```
The last step, serialize to string:
```php
$str = $serializer->serialize($order, 'json');
```
`$str` will be equal json (of course not formatted):
```json
{
   "id": "123-abc",
   "price": 100.5,
   "products": null
}
```
It's possible to convert string to object, just specify the object type:
```php
$order = $serializer->deserialize($str, Order::class, 'json');
// output
// object(Order)#11 (2) { ["id"]=> string(7) "123-abc" ["price"]=> float(100.5) ["products"]=> uninitialized(array) }
  ```
---

### Skipping null values
Helpful option, if you want to omit null values, pass constant SKIP_NULL_VALUES to context like this  
```php
$str = $serializer->serialize($order, 'json', context: [ObjectNormalizer::SKIP_NULL_VALUES]);
```
output
```json
{
   "id": "123-abc",
   "price": 100.5
}
```
---
### Using groups
You can specify what properties should be serialize/deserialize via groups  
Let's add some groups to our `Order` class
```php
#[Groups(['group_1', 'group_2'])]
public string $id;

#[Groups(['group_2', 'group_3'])]
public float $price;
```
Now, if you pass a group to `serialise` method, it will be process properties that match given groups
```php
$str = $serializer->serialize($order, 'json', ['group_1']);
```
will output
```json
{
   "id": "123-abc"
}
```
because only `id` have attribute `#Groups` with group `group_1`,  
but if we pass `group_2`, we already have `id` and `price`
```php
$str = $serializer->serialize($order, 'json', ['group_2']);
```
will output
```json
{
  "id": "123-abc",
  "price": 100.5
}
```
You can pass multiple groups, any property, that have one of passed group name, will be serialized
```php
$str = $serializer->serialize($order, 'json', ['group_1', 'group_3']);
```
will output
```json
{
  "id": "123-abc",
  "price": 100.5
}
```
There is one special group `*`, property will be serialized, if it has at least one any group
```php
$str = $serializer->serialize($order, 'json', ['*']);
```
will output
```json
{
  "id": "123-abc",
  "price": 100.5
}
```
This also good works with denormalization process.

---

### Naming
Sometimes it's necessary to rename a property, you can achieve this by using `Name` attribute
```php
#[Name('orderId')]
public string $id;
```
will output
```json
{
  "orderId": "123-abc"
}
```
Or you want to nest a property? Not a good idea to make several object for this.  
Just pass `SIMPLIFY_COMPOSITE_KEYS` via context, this option will split with dot symbol `.` the specified  
property name into nested object!
```php
// Dont forget to pass SIMPLIFY_COMPOSITE_KEYS!!!
$str = $serializer->serialize($order, 'json', ['group_1'], [ObjectNormalizer::SIMPLIFY_COMPOSITE_KEYS]);
```
will output
```json
{
  "order": {
    "info": {
      "identifier": {
        "primary": "123-abc"
      }
    }
  }
}
```
This also good works with denormalization process.

---

### Typed arrays
Our order have some products
```php
class Product
{
    public string $name;
    public float $price;
}

...

$phone = new Product();
$phone->name = 'Samsung phone';
$phone->price = 100;

$apple = new Product();
$apple->name = 'apple';
$apple->price = 7;

$order->products = [$phone, $apple];
```
Lets try to serialize
```php
$str = $serializer->serialize($order, 'json');
```
output
```json
{
    "id": "123-abc",
    "price": 100.5,
    "products": [
        {
            "name": "Samsung phone",
            "price": 100
        },
        {
            "name": "apple",
            "price": 7
        }
    ]
}
```
Great! It works even with nested objects (all attributes and groups also works with nested object).  
But what with deserialization? If there is an array with simply type like integer or string, there is no problem, 
but we have array of object! Just specify the type you need via `ArrayType` attribute
```php
class Order {
    ...
    #[ArrayType(Product::class)]
    public array $products;
}

...

$order = $serializer->deserialize($str, Order::class, 'json');
//output
//object(Order)#13 (3) { ["id"]=> string(7) "123-abc" ["price"]=> float(100.5) ["products"]=> array(2) { [0]=> object(Product)#17 (2) { ["name"]=> string(13) "Samsung phone" ["price"]=> float(100) } [1]=> object(Product)#21 (2) { ["name"]=> string(5) "apple" ["price"]=> float(7) } } }
```
That's it! You have `Order` object with `$products` array contained `Product` objects!  
Note, if you omit `ArrayType`, you will get simply assoc php array, cause there's no info about array type.

---

### Serialization and Deserialization strategy
You can even specify different behaviour for serialization/deserialization via `NormalizationContext` 
and `DenormalizationContext` by passing attributes into them.
example
```php
class Order {
...

    #[NormalizationContext(
        new Groups(['group_1']),
        new Name('order.full_price')
    )]
    #[DenormalizationContext(
        new Groups(['group_2']),
        new Name('order_price')
    )]
    public float $price;
...
}

...

$str = $serializer->serialize($order, 'json', ['group_1']);
```
output:
```json
{
"id": "123-abc",
"full_price": 100.5
}
```
Lets try to deserialize
```php
$order = $serializer->deserialize('{"id":"123-abc","order_price":100.5}', Order::class, 'json', ['group_2']);
//output
//object(Order)#11 (2) { ["id"]=> string(7) "123-abc" ["price"]=> float(100.5) ["products"]=> uninitialized(array) }
```

---







