<?php

use OpenApi\Annotations as OA;

/**
 * @OA\Schema(
 *     schema="Order",
 *     properties={@OA\Property(property="id", type="integer")},
 * )
 */
class Order
{
}

?>
-----
<?php

use OpenApi\Attributes as OA;

#[OA\Schema(schema: 'Order', properties: [new OA\Property(property: 'id', type: 'integer')])]
class Order
{
}

?>
