<?php

use OpenApi\Annotations as OA;

/**
 * @OA\Schema(
 *     schema="Order",
 *     properties={
 *         @OA\Property(property="id", type="integer"),
 *         @OA\Property(property="name", type="string"),
 *     }
 * )
 */
class Order
{
}

?>
-----
<?php

use OpenApi\Attributes as OA;

#[OA\Schema(schema: 'Order', properties: [new OA\Property(property: 'id', type: 'integer'), new OA\Property(property: 'name', type: 'string')])]
class Order
{
}

?>
